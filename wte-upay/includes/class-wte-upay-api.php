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
     * Partner ID (X-Partner-Id)
     *
     * @var string
     */
    protected $partner_id;

    /**
     * Biller UUID
     *
     * @var string
     */
    protected $biller_uuid;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option( 'wp_travel_engine_settings', array() );
        
        // Get credentials from settings
        $this->client_id     = isset( $settings['upay_client_id'] ) ? $settings['upay_client_id'] : '';
        $this->client_secret = isset( $settings['upay_client_secret'] ) ? $settings['upay_client_secret'] : '';
        $this->partner_id    = isset( $settings['upay_partner_id'] ) ? $settings['upay_partner_id'] : '';
        $this->biller_uuid   = isset( $settings['upay_biller_uuid'] ) ? $settings['upay_biller_uuid'] : '';
        
        // Set API URL
        $this->api_url = defined( 'UPAY_BASE_URL' ) ? UPAY_BASE_URL : 'https://api.unionbankph.com/ubp/external/upay/payments/v1';
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
            'billerUuid'      => $this->biller_uuid,
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
     * @param string $transaction_id Transaction UUID.
     * @return array|WP_Error
     */
    public function check_status( $transaction_id ) {
        $endpoint = '/transactions/' . $this->biller_uuid . '/status';
        
        $query_params = array(
            'transactionId' => $transaction_id,
        );

        $response = $this->make_request( 'GET', $endpoint, null, $query_params );

        return $response;
    }

    /**
     * Get biller details
     *
     * @return array|WP_Error
     */
    public function get_biller_details() {
        $endpoint = '/billers/' . $this->biller_uuid;
        
        $response = $this->make_request( 'GET', $endpoint );

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

        // Prepare headers according to UPay API specification
        $headers = array(
            'Content-Type'        => 'application/json',
            'Accept'              => 'application/json',
            'X-IBM-Client-Id'     => $this->client_id,
            'X-IBM-Client-Secret' => $this->client_secret,
            'X-Partner-Id'        => $this->partner_id,
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
        return $date->format( 'Y-m-d\TH:i:s.v\Z' );
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
