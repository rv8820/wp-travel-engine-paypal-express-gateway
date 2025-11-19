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
     * Partner ID
     *
     * @var string
     */
    protected $partner_id;

    /**
     * Partner Username
     *
     * @var string
     */
    protected $partner_username;

    /**
     * Partner Password
     *
     * @var string
     */
    protected $partner_password;

    /**
     * OAuth Username (for password grant)
     *
     * @var string
     */
    protected $oauth_username;

    /**
     * OAuth Password (for password grant)
     *
     * @var string
     */
    protected $oauth_password;

    /**
     * OAuth Scope
     *
     * @var string
     */
    protected $oauth_scope;

    /**
     * Biller UUID
     *
     * @var string
     */
    protected $biller_uuid;

    /**
     * Biller Reference
     *
     * @var string
     */
    protected $biller_ref;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option( 'wp_travel_engine_settings', array() );

        // Get API credentials from settings
        $this->client_id         = isset( $settings['upay_settings']['client_id'] ) ? $settings['upay_settings']['client_id'] : '';
        $this->client_secret     = isset( $settings['upay_settings']['client_secret'] ) ? $settings['upay_settings']['client_secret'] : '';
        $this->partner_id        = isset( $settings['upay_settings']['partner_id'] ) ? $settings['upay_settings']['partner_id'] : '';
        $this->oauth_username    = isset( $settings['upay_settings']['oauth_username'] ) ? $settings['upay_settings']['oauth_username'] : '';
        $this->oauth_password    = isset( $settings['upay_settings']['oauth_password'] ) ? $settings['upay_settings']['oauth_password'] : '';
        $this->oauth_scope       = isset( $settings['upay_settings']['oauth_scope'] ) ? $settings['upay_settings']['oauth_scope'] : 'upay_payments';
        $this->biller_uuid       = isset( $settings['upay_settings']['biller_uuid'] ) ? $settings['upay_settings']['biller_uuid'] : '';
        $this->biller_ref        = isset( $settings['upay_settings']['biller_ref'] ) ? $settings['upay_settings']['biller_ref'] : '';

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
            error_log( 'UPay: Requesting new access token using PASSWORD GRANT flow...' );
            error_log( 'UPay OAuth2 Config Check:' );
            error_log( '  - Client ID: ' . ( !empty( $this->client_id ) ? 'Set (' . strlen( $this->client_id ) . ' chars)' : 'MISSING' ) );
            error_log( '  - OAuth Username: ' . ( !empty( $this->oauth_username ) ? 'Set (' . strlen( $this->oauth_username ) . ' chars)' : 'MISSING' ) );
            error_log( '  - OAuth Password: ' . ( !empty( $this->oauth_password ) ? 'Set (' . strlen( $this->oauth_password ) . ' chars)' : 'MISSING' ) );
            error_log( '  - OAuth Scope: ' . ( !empty( $this->oauth_scope ) ? $this->oauth_scope : 'MISSING' ) );
        }

        // Determine OAuth endpoint based on environment
        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            $token_url = 'https://api-uat.unionbankph.com/partners/sb/partners/v1/oauth2/token';
        } else {
            $token_url = 'https://api.unionbankph.com/partners/sb/partners/v1/oauth2/token';
        }

        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            error_log( '  - Token URL: ' . $token_url );
        }

        // Prepare OAuth2 request using PASSWORD grant flow
        // Based on UPay API documentation curl example:
        // grant_type=password&client_id=<CLIENT_ID>&username=<USERNAME>&password=<PASSWORD>&scope=<SCOPE>
        $response = wp_remote_post( $token_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ),
            'body' => array(
                'grant_type' => 'password',
                'client_id'  => $this->client_id,
                'username'   => $this->oauth_username,
                'password'   => $this->oauth_password,
                'scope'      => $this->oauth_scope,
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

        // Check if biller UUID is configured
        if ( empty( $this->biller_uuid ) ) {
            return new WP_Error( 'upay_missing_biller_uuid', __( 'Biller UUID is not configured', 'wte-upay' ) );
        }

        // Sanitize mobile number - must be exactly 10 digits (PH format without country code)
        // Example: 9287653827 (no +63, no 0 prefix)
        $mobile = preg_replace( '/[^0-9]/', '', $payment_data['mobile'] );

        if ( ! empty( $mobile ) ) {
            // Remove country code if present (63)
            if ( substr( $mobile, 0, 2 ) === '63' && strlen( $mobile ) === 12 ) {
                $mobile = substr( $mobile, 2 ); // 639171234567 -> 9171234567
            }
            // Remove leading 0 if present
            elseif ( substr( $mobile, 0, 1 ) === '0' && strlen( $mobile ) === 11 ) {
                $mobile = substr( $mobile, 1 ); // 09171234567 -> 9171234567
            }

            // Ensure we have exactly 10 digits
            if ( strlen( $mobile ) !== 10 ) {
                error_log( 'UPay Error: Mobile number must be exactly 10 digits. Got: ' . strlen( $mobile ) . ' digits (' . $mobile . ')' );
            }
        }

        // Sanitize references - keep only alphanumeric and spaces (no special chars at all)
        $references = array();
        foreach ( $payment_data['references'] as $ref ) {
            $references[] = array(
                'index' => (string) $ref['index'],
                // Keep only letters, numbers, and spaces
                'value' => preg_replace( '/[^a-zA-Z0-9\s]/', '', $ref['value'] )
            );
        }

        // Prepare request body according to UPay API specification
        $request_body = array(
            'senderRefId'     => $payment_data['order_id'],
            'tranRequestDate' => $this->get_formatted_date(),
            'billerUuid'      => $this->biller_uuid, // Required field
            'emailAddress'    => $payment_data['email'],
            'amount'          => number_format( (float) $payment_data['amount'], 2, '.', '' ),
            'paymentMethod'   => $payment_data['payment_method'], // 'instapay' or 'UB Online'
            'mobileNumber'    => $mobile,
            'callbackUrl'     => $payment_data['callback_url'], // Will be properly encoded by wp_remote_request
            'references'      => $references,
        );

        // Debug logging for validation
        if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
            error_log( 'UPay Mobile Number (should be 10 digits): ' . $mobile . ' (length: ' . strlen( $mobile ) . ')' );
            error_log( 'UPay Callback URL: ' . $payment_data['callback_url'] );
        }

        // Make API request
        $response = $this->make_request( 'POST', $endpoint, $request_body );

        return $response;
    }

    /**
     * Check transaction status
     *
     * @param string $transaction_id Transaction ID (optional).
     * @param string $biller_ref     Biller reference (optional).
     * @return array|WP_Error
     */
    public function check_status( $transaction_id = '', $biller_ref = '' ) {
        // Check if biller UUID is configured
        if ( empty( $this->biller_uuid ) ) {
            return new WP_Error( 'upay_missing_biller_uuid', __( 'Biller UUID is not configured', 'wte-upay' ) );
        }

        // Use instance biller_ref if not provided
        if ( empty( $biller_ref ) ) {
            $biller_ref = $this->biller_ref;
        }

        if ( empty( $biller_ref ) ) {
            return new WP_Error( 'upay_missing_biller_ref', __( 'Biller reference not configured', 'wte-upay' ) );
        }

        // Endpoint format: /transactions/{billerUuid}/status
        $endpoint = '/transactions/' . $this->biller_uuid . '/status';

        // Query parameters
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
            'Content-Type'        => 'application/json',
            'Accept'              => 'application/json',
            'Authorization'       => 'Bearer ' . $this->access_token,
            'x-ibm-client-id'     => $this->client_id,
            'x-ibm-client-secret' => $this->client_secret,
            'x-partner-id'        => $this->partner_id,
        );

        // Prepare request arguments
        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30,
        );

        // Add body for POST requests
        if ( 'POST' === $method && $body ) {
            // Use JSON_UNESCAPED_SLASHES and JSON_UNESCAPED_UNICODE to prevent URL encoding issues
            $args['body'] = json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
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
     * Format: YYYY-MM-DDTHH:MM:SS.SSS (exactly 23 characters, no timezone)
     *
     * @return string
     */
    protected function get_formatted_date() {
        $date = new DateTime( 'now', new DateTimeZone( 'Asia/Manila' ) );
        // Format: 2025-11-17T18:07:51.636 (exactly 23 characters)
        return $date->format( 'Y-m-d\TH:i:s.v' );
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
