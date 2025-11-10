<?php
/**
 * UPay API Handler
 * Handles all API communication with Union Bank UPay
 *
 * @package WTE_UPay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WTE_UPay_API {

    /**
     * API Base URL
     *
     * @var string
     */
    protected $api_url;

    /**
     * Client ID (X-IBM-Client-Id)
     *
     * @var string
     */
    protected $client_id;

    /**
     * Client Secret (X-IBM-Client-Secret)
     *
     * @var string
     */
    protected $client_secret;

    /**
     * OAuth2 Access Token
     *
     * @var string
     */
    protected $access_token;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option( 'wp_travel_engine_settings', array() );

        // Get credentials from settings - Only Client ID and Client Secret required
        $this->client_id     = isset( $settings['upay_settings']['client_id'] ) ? $settings['upay_settings']['client_id'] : '';
        $this->client_secret = isset( $settings['upay_settings']['client_secret'] ) ? $settings['upay_settings']['client_secret'] : '';

        // Set API URL based on debug/test mode
        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            // UAT/Test environment
            $this->api_url = defined( 'UPAY_BASE_URL' ) ? UPAY_BASE_URL : 'https://api-uat.unionbankph.com/partners/sb/upay/payments/v1';
        } else {
            // Production environment
            $this->api_url = defined( 'UPAY_BASE_URL' ) ? UPAY_BASE_URL : 'https://api.unionbankph.com/partners/sb/upay/payments/v1';
        }

        // Get or refresh access token
        $this->access_token = $this->get_access_token();
    }

    /**
     * Get OAuth2 access token (with caching)
     *
     * @return string|WP_Error Access token or error
     */
    protected function get_access_token() {
        // Check if we have a cached token
        $cached_token = get_transient( 'upay_access_token' );
        if ( $cached_token ) {
            if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                error_log( 'UPay: Using cached access token' );
            }
            return $cached_token;
        }

        // No cached token, request a new one
        return $this->refresh_access_token();
    }

    /**
     * Request new OAuth2 access token from Union Bank
     *
     * @return string|WP_Error Access token or error
     */
    protected function refresh_access_token() {
        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            error_log( 'UPay: Requesting new access token...' );
        }

        // Determine OAuth endpoint based on environment
        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            $token_url = 'https://api-uat.unionbankph.com/partners/sb/partners/v1/oauth2/token';
        } else {
            $token_url = 'https://api.unionbankph.com/partners/sb/partners/v1/oauth2/token';
        }

        // Prepare OAuth2 request
        $response = wp_remote_post( $token_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope'         => 'upay_payments',
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'UPay OAuth2 Error: ' . $response->get_error_message() );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $token_data = json_decode( $response_body, true );

        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            error_log( 'UPay OAuth2 Response Code: ' . $response_code );
            error_log( 'UPay OAuth2 Response: ' . $response_body );
        }

        if ( $response_code !== 200 || ! isset( $token_data['access_token'] ) ) {
            $error_message = isset( $token_data['error_description'] ) ? $token_data['error_description'] : 'Failed to obtain access token';
            error_log( 'UPay OAuth2 Error: ' . $error_message );
            return new WP_Error( 'upay_auth_error', $error_message );
        }

        $access_token = $token_data['access_token'];
        $expires_in = isset( $token_data['expires_in'] ) ? (int) $token_data['expires_in'] : 3600;

        // Cache the token for slightly less than its expiration time (90% of expires_in)
        $cache_duration = floor( $expires_in * 0.9 );
        set_transient( 'upay_access_token', $access_token, $cache_duration );

        // Store refresh token if provided
        if ( isset( $token_data['refresh_token'] ) ) {
            $refresh_expires = isset( $token_data['refresh_token_expires_in'] ) ? (int) $token_data['refresh_token_expires_in'] : 2592000;
            set_transient( 'upay_refresh_token', $token_data['refresh_token'], $refresh_expires );
        }

        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            error_log( 'UPay: New access token obtained and cached for ' . $cache_duration . ' seconds' );
        }

        return $access_token;
    }

    /**
     * Create payment transaction
     *
     * @param array $payment_data Payment data.
     * @return array|WP_Error
     */
    public function create_transaction( $payment_data ) {
        $endpoint = '/transactions';

        // Prepare request body according to UPay API specification
        $request_body = array(
            'senderRefId'     => $payment_data['order_id'],
            'tranRequestDate' => $this->get_formatted_date(),
            'emailAddress'    => $payment_data['email'],
            'amount'          => number_format( (float) $payment_data['amount'], 2, '.', '' ),
            'paymentMethod'   => $payment_data['payment_method'], // 'instapay' or 'UB Online'
            'mobileNumber'    => $payment_data['mobile'],
            'callbackUrl'     => $payment_data['callback_url'],
            'references'      => $payment_data['references'], // Array of index/value pairs
        );

        // Make API request
        $response = $this->make_request( 'POST', $endpoint, $request_body );

        return $response;
    }

    /**
     * Check transaction status
     *
     * @param string $transaction_id Transaction ID.
     * @param string $biller_ref     Biller reference.
     * @return array|WP_Error
     */
    public function check_status( $transaction_id, $biller_ref = '' ) {
        // Get billerRef from settings if not provided
        if ( empty( $biller_ref ) ) {
            $settings = get_option( 'wp_travel_engine_settings', array() );
            $biller_ref = isset( $settings['upay_settings']['biller_ref'] ) ? $settings['upay_settings']['biller_ref'] : '';
        }

        if ( empty( $biller_ref ) ) {
            return new WP_Error( 'upay_missing_biller_ref', __( 'Biller reference not configured', 'wte-upay' ) );
        }

        $endpoint = '/transactions/' . $biller_ref . '/status';

        $query_params = array(
            'billerRef' => $biller_ref,
        );

        $response = $this->make_request( 'GET', $endpoint, null, $query_params );

        return $response;
    }

    /**
     * Make API request to UPay
     *
     * @param string $method       HTTP method (GET, POST).
     * @param string $endpoint     API endpoint.
     * @param array  $body         Request body (for POST).
     * @param array  $query_params Query parameters (for GET).
     * @return array|WP_Error
     */
    protected function make_request( $method, $endpoint, $body = null, $query_params = array() ) {
        $url = $this->api_url . $endpoint;

        // Add query parameters if any
        if ( ! empty( $query_params ) ) {
            $url = add_query_arg( $query_params, $url );
        }

        // Check if we have a valid access token
        if ( is_wp_error( $this->access_token ) ) {
            return $this->access_token;
        }

        // Prepare headers with OAuth2 Bearer token
        $headers = array(
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->access_token,
        );

        // Prepare request arguments
        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30,
        );

        // Add body for POST requests
        if ( 'POST' === $method && $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        // Log request in debug mode
        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            error_log( 'UPay API Request: ' . $method . ' ' . $url );
            error_log( 'UPay API Request Body: ' . wp_json_encode( $body ) );
        }

        // Make the request
        $response = wp_remote_request( $url, $args );

        // Handle errors
        if ( is_wp_error( $response ) ) {
            error_log( 'UPay API Error: ' . $response->get_error_message() );
            return $response;
        }

        // Get response body
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        // Log response in debug mode
        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            error_log( 'UPay API Response Code: ' . $response_code );
            error_log( 'UPay API Response: ' . $response_body );
        }

        // Check for API errors
        if ( $response_code >= 400 ) {
            $error_message = isset( $response_data['errors'][0]['description'] ) 
                ? $response_data['errors'][0]['description'] 
                : 'Unknown error occurred';
            
            return new WP_Error( 'upay_api_error', $error_message, array( 'response' => $response_data ) );
        }

        return $response_data;
    }

    /**
     * Get formatted date for UPay API
     * Format: YYYY-MM-DDTHH:MM:SS.sss+08:00
     *
     * @return string
     */
    protected function get_formatted_date() {
        $date = new DateTime( 'now', new DateTimeZone( 'Asia/Manila' ) );
        return $date->format( 'Y-m-d\TH:i:s.vP' );
    }

    /**
     * Generate unique sender reference ID
     *
     * @param int $payment_id Payment ID.
     * @return string
     */
    public function generate_sender_ref_id( $payment_id ) {
        // Max 20 characters according to API spec
        $prefix = 'WTE';
        $timestamp = time();
        return substr( $prefix . $payment_id . $timestamp, 0, 20 );
    }
}
