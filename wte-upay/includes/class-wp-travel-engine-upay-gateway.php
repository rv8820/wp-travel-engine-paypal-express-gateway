<?php
/**
 * Admin Settings & Payment Handler
 *
 * @package WTE_UPay
 */

class Wte_UPay_Admin {
    
    public function __construct() {
        add_action( 'wte_upay_settings', array( $this, 'wte_upay_settings' ) );
        add_action( 'wte_upay_enable', array( $this, 'wte_upay_enable' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_backend_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        add_action( 'add_meta_boxes', array( $this, 'wpte_upay_add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'wp_travel_engine_upay_meta_box_data' ) );
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
    
        // **CRITICAL: Add filter for the BaseGateway registration (WTE 6.0+)**
        if ( defined( 'WP_TRAVEL_ENGINE_VERSION' ) && version_compare( WP_TRAVEL_ENGINE_VERSION, '6.0.0', '>=' ) ) {
            add_filter( 'wptravelengine_registering_payment_gateways', array( $this, 'add_upay_checkout' ) );
        } else {
            add_action( 'wp_travel_engine_available_payment_gateways', array( $this, 'upay_gateway_list' ) );
        }
    
        // **CRITICAL: Add filter for the SORTABLE payment gateway list**
        add_filter( 'wptravelengine_payment_gateways', array( $this, 'add_payment_gateway' ) );
    
        // Handle payments
        add_action( 'wp_travel_engine_after_booking_process_completed', array( $this, 'upay_handle_payment_process' ) );
    
        // Filter - Global settings
        add_filter( 'wpte_settings_get_global_tabs', array( $this, 'add_upay_tab' ) );
    
        add_action( 'wte_payment_gateway_upay_enable', array( $this, 'map_payment_data_to_new_booking_structure' ), 10, 3 );

        // Handle UPay callback
        add_action( 'init', array( $this, 'handle_upay_callback' ) );

        // Handle QR code display
        add_action( 'template_redirect', array( $this, 'display_upay_qr_code' ) );

        // AJAX handler for checking payment status
        add_action( 'wp_ajax_upay_check_status', array( $this, 'ajax_check_payment_status' ) );
        add_action( 'wp_ajax_nopriv_upay_check_status', array( $this, 'ajax_check_payment_status' ) );

        // Add this filter to include UPay in REST API settings
        add_filter( 'wptravelengine_rest_payment_gateways', array( $this, 'add_upay_to_rest_settings' ), 10, 2 );
    }

    /**
     * Load plugin text domain
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wte-upay',
            false,
            dirname( dirname( plugin_basename( WPTRAVELENGINE_UPAY_FILE__ ) ) ) . '/languages/'
        );
    }

    /**
     * Add payment gateway to list
     *
     * @param array $payment_gateways Payment gateways.
     * @return array
     */
    public function add_payment_gateway( $payment_gateways ) {
        $payment_gateways['upay_enable'] = new \WTE_Payment_Gateway_UPay();
        return $payment_gateways;
    }

    /**
     * Process UPay payment
     *
     * @param int    $payment_id Payment ID.
     * @param string $type       Payment type.
     * @param string $gateway    Gateway.
     */
    public function process_upay_payment( $payment_id, $type = 'full_payment', $gateway = '' ) {
        if ( ! $payment_id ) {
            return;
        }

        try {
            $booking_id = get_post_meta( $payment_id, 'booking_id', true );
            $booking    = get_post( $booking_id );
            $payable    = get_post_meta( $payment_id, 'payable', true );

            if ( ! $booking || ! $payable ) {
                throw new Exception( __( 'Invalid booking or payment data', 'wte-upay' ) );
            }

            // Get booking details
            $booking_meta = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting', true );
            
            // Initialize UPay API
            $upay_api = new WTE_UPay_API();

            // Prepare payment data
            $payment_data = array(
                'order_id'       => $upay_api->generate_sender_ref_id( $payment_id ),
                'email'          => isset( $booking_meta['place_order']['booking']['email'] ) ? $booking_meta['place_order']['booking']['email'] : '',
                'amount'         => $payable['amount'],
                'payment_method' => 'instapay', // or 'UB Online' - can be made configurable
                'mobile'         => isset( $booking_meta['place_order']['booking']['phone'] ) ? $booking_meta['place_order']['booking']['phone'] : '',
                'callback_url'   => home_url( '?upay_callback=1&payment_id=' . $payment_id ),
                'references'     => array(
                    array(
                        'index' => 0,
                        'value' => 'Booking #' . $booking_id,
                    ),
                    array(
                        'index' => 1,
                        'value' => get_the_title( $booking_meta['place_order']['tid'] ),
                    ),
                ),
            );

            // Store payment data for verification
            update_post_meta( $payment_id, 'upay_sender_ref_id', $payment_data['order_id'] );

            // Create transaction
            $response = $upay_api->create_transaction( $payment_data );

            if ( is_wp_error( $response ) ) {
                throw new Exception( $response->get_error_message() );
            }

            // Store transaction details
            if ( isset( $response['transactionId'] ) ) {
                update_post_meta( $payment_id, 'upay_transaction_id', $response['transactionId'] );
            }
            if ( isset( $response['uuid'] ) ) {
                update_post_meta( $payment_id, 'upay_uuid', $response['uuid'] );
            }

            // Store full response
            update_post_meta( $payment_id, 'upay_response', $response );

            // For instapay, display QR code
            if ( isset( $response['qrCode'] ) && ! empty( $response['qrCode'] ) ) {
                // Store QR code
                update_post_meta( $payment_id, 'upay_qr_code', $response['qrCode'] );
                
                // Redirect to QR code display page
                $redirect_url = add_query_arg(
                    array(
                        'payment_id' => $payment_id,
                        'action'     => 'upay_qr',
                    ),
                    home_url()
                );
            } else {
                // For other payment methods, might get a direct payment URL
                $redirect_url = isset( $response['paymentUrl'] ) ? $response['paymentUrl'] : home_url();
            }

            wp_safe_redirect( $redirect_url );
            exit;

        } catch ( Exception $e ) {
            // Log error
            error_log( 'UPay Payment Error: ' . $e->getMessage() );
            
            // Set error in session
            $session = WTE()->session;
            $errors  = $session->get( 'wp_travel_engine_errors' );
            if ( ! is_array( $errors ) ) {
                $errors = array();
            }
            $errors[] = $e->getMessage();
            $session->set( 'wp_travel_engine_errors', $errors );

            // Redirect back to checkout
            wp_safe_redirect( wp_travel_engine_get_checkout_url() );
            exit;
        }
    }

    /**
     * Map payment data for new booking structure (WTE 6.0+)
     * Triggered by 'wte_payment_gateway_upay_enable' action
     *
     * @param int    $payment_id Payment ID.
     * @param string $type       Payment type (full_payment/partial_payment).
     * @param string $gateway    Gateway ID.
     */
    public function map_payment_data_to_new_booking_structure( $payment_id, $type = 'full_payment', $gateway = '' ) {
        if ( $gateway !== 'upay_enable' ) {
            return;
        }
        $this->process_upay_payment( $payment_id, $type, $gateway );
    }

    /**
     * Handle UPay payment process after booking completed
     * Triggered by 'wp_travel_engine_after_booking_process_completed' action
     *
     * @param array $booking_data Booking data array.
     */
    public function upay_handle_payment_process( $booking_data ) {
        // Extract payment ID from booking data
        $payment_id = null;

        if ( isset( $booking_data['payment_id'] ) ) {
            $payment_id = $booking_data['payment_id'];
        } elseif ( isset( $booking_data['payment']->ID ) ) {
            $payment_id = $booking_data['payment']->ID;
        } elseif ( isset( $booking_data['pid'] ) ) {
            $payment_id = $booking_data['pid'];
        }

        if ( ! $payment_id ) {
            return;
        }

        // Check if UPay is the selected gateway
        $payment_gateway = get_post_meta( $payment_id, 'wp_travel_engine_payment_gateway', true );
        if ( $payment_gateway !== 'upay_enable' ) {
            return;
        }

        $payment_type = isset( $booking_data['payment_type'] ) ? $booking_data['payment_type'] : 'full_payment';
        $this->process_upay_payment( $payment_id, $payment_type, $payment_gateway );
    }

    /**
     * Handle UPay callback
     */
    public function handle_upay_callback() {
        if ( ! isset( $_GET['upay_callback'] ) || ! isset( $_GET['payment_id'] ) ) {
            return;
        }

        $payment_id = absint( $_GET['payment_id'] );
        $booking_id = get_post_meta( $payment_id, 'booking_id', true );

        if ( ! $payment_id || ! $booking_id ) {
            return;
        }

        // Get UPay response from callback
        $transaction_id = get_post_meta( $payment_id, 'upay_transaction_id', true );

        if ( $transaction_id ) {
            // Check transaction status
            $upay_api = new WTE_UPay_API();
            $status_response = $upay_api->check_status( $transaction_id );

            if ( ! is_wp_error( $status_response ) && isset( $status_response['state'] ) ) {
                $state = strtolower( $status_response['state'] );

                // Store status response
                update_post_meta( $payment_id, 'upay_status_response', $status_response );

                // Check if payment is successful
                if ( in_array( $state, array( 'success', 'completed', 'paid' ), true ) ) {
                    // Update booking status
                    $booking = get_post( $booking_id );
                    $amount  = isset( $status_response['amount'] ) ? $status_response['amount'] : 0;

                    $booking_meta = array(
                        'wp_travel_engine_booking_status' => 'booked',
                        'paid_amount'                     => +$booking->paid_amount + +$amount,
                        'due_amount'                      => +$booking->due_amount - +$amount,
                    );

                    WTE_Booking::update_booking( $booking_id, array( 'meta_input' => $booking_meta ) );

                    // Update payment status
                    update_post_meta( $payment_id, 'payment_status', 'completed' );

                    // Send confirmation emails
                    WTE_Booking::send_emails( $payment_id, 'order_confirmation', 'all' );

                    // Redirect to thank you page
                    $payment_key = wptravelengine_generate_key( $payment_id );
                    $redirect_url = add_query_arg(
                        array( 'payment_key' => $payment_key ),
                        wp_travel_engine_get_booking_confirm_url()
                    );
                } else {
                    // Payment failed or cancelled
                    update_post_meta( $payment_id, 'payment_status', 'failed' );
                    $redirect_url = wp_travel_engine_get_checkout_url();
                }

                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }

    /**
     * Display UPay QR code page
     */
    public function display_upay_qr_code() {
        // Check if this is a QR code display request
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'upay_qr' || ! isset( $_GET['payment_id'] ) ) {
            return;
        }

        $payment_id = absint( $_GET['payment_id'] );

        if ( ! $payment_id ) {
            return;
        }

        // Get QR code from payment meta
        // Try WTE 6.0+ first
        if ( class_exists( '\WPTravelEngine\Core\Models\Post\Payment' ) ) {
            $payment = \WPTravelEngine\Core\Models\Post\Payment::make_from_id( $payment_id );
            $qr_code = $payment ? $payment->get_meta( 'upay_qr_code' ) : '';
            $transaction_id = $payment ? $payment->get_meta( 'upay_transaction_id' ) : '';
            $booking_id = $payment ? $payment->get_meta( 'booking_id' ) : '';
        } else {
            // Fallback to legacy post meta
            $qr_code = get_post_meta( $payment_id, 'upay_qr_code', true );
            $transaction_id = get_post_meta( $payment_id, 'upay_transaction_id', true );
            $booking_id = get_post_meta( $payment_id, 'booking_id', true );
        }

        if ( empty( $qr_code ) ) {
            wp_die( __( 'QR code not found. Please try again or contact support.', 'wte-upay' ) );
        }

        // Poll URL for checking payment status
        $poll_url = add_query_arg(
            array(
                'action' => 'upay_check_status',
                'payment_id' => $payment_id,
            ),
            admin_url( 'admin-ajax.php' )
        );

        // Return URL after payment
        $return_url = home_url();

        // Display QR code page
        $this->render_qr_code_page( $qr_code, $transaction_id, $booking_id, $poll_url, $return_url );
        exit;
    }

    /**
     * Render QR code page
     *
     * @param string $qr_code QR code data (base64 or URL).
     * @param string $transaction_id Transaction ID.
     * @param int    $booking_id Booking ID.
     * @param string $poll_url URL to poll for payment status.
     * @param string $return_url URL to return after payment.
     */
    private function render_qr_code_page( $qr_code, $transaction_id, $booking_id, $poll_url, $return_url ) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - <?php esc_html_e( 'UPay Payment', 'wte-upay' ); ?></title>
            <?php wp_head(); ?>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: #f5f5f5;
                    margin: 0;
                    padding: 20px;
                }
                .upay-qr-container {
                    max-width: 600px;
                    margin: 50px auto;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    padding: 40px;
                    text-align: center;
                }
                .upay-qr-container h1 {
                    color: #333;
                    margin-bottom: 10px;
                    font-size: 28px;
                }
                .upay-qr-container .subtitle {
                    color: #666;
                    margin-bottom: 30px;
                    font-size: 16px;
                }
                .qr-code-wrapper {
                    background: #fff;
                    padding: 20px;
                    border: 2px solid #e0e0e0;
                    border-radius: 8px;
                    display: inline-block;
                    margin: 20px 0;
                }
                .qr-code-wrapper img {
                    max-width: 300px;
                    height: auto;
                    display: block;
                }
                .instructions {
                    background: #f9f9f9;
                    border-left: 4px solid #0073aa;
                    padding: 20px;
                    margin: 30px 0;
                    text-align: left;
                }
                .instructions h3 {
                    margin-top: 0;
                    color: #0073aa;
                }
                .instructions ol {
                    margin: 15px 0;
                    padding-left: 25px;
                }
                .instructions li {
                    margin: 10px 0;
                    line-height: 1.6;
                }
                .transaction-info {
                    background: #fff;
                    border: 1px solid #e0e0e0;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 20px 0;
                    font-size: 14px;
                }
                .transaction-info p {
                    margin: 5px 0;
                    color: #666;
                }
                .transaction-info strong {
                    color: #333;
                }
                .status-checking {
                    display: none;
                    background: #fff3cd;
                    border: 1px solid #ffc107;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 20px 0;
                }
                .status-checking.active {
                    display: block;
                }
                .spinner {
                    border: 3px solid #f3f3f3;
                    border-top: 3px solid #0073aa;
                    border-radius: 50%;
                    width: 20px;
                    height: 20px;
                    animation: spin 1s linear infinite;
                    display: inline-block;
                    margin-right: 10px;
                    vertical-align: middle;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .return-button {
                    display: inline-block;
                    background: #0073aa;
                    color: white;
                    padding: 12px 30px;
                    border-radius: 4px;
                    text-decoration: none;
                    margin-top: 20px;
                    transition: background 0.3s;
                }
                .return-button:hover {
                    background: #005a87;
                    color: white;
                }
            </style>
        </head>
        <body>
            <div class="upay-qr-container">
                <h1><?php esc_html_e( 'Complete Your Payment', 'wte-upay' ); ?></h1>
                <p class="subtitle"><?php esc_html_e( 'Scan the QR code below using your mobile banking app', 'wte-upay' ); ?></p>

                <div class="qr-code-wrapper">
                    <?php if ( strpos( $qr_code, 'data:image' ) === 0 || strpos( $qr_code, 'http' ) === 0 ) : ?>
                        <img src="<?php echo esc_url( $qr_code ); ?>" alt="<?php esc_attr_e( 'UPay QR Code', 'wte-upay' ); ?>" />
                    <?php else : ?>
                        <img src="data:image/png;base64,<?php echo esc_attr( $qr_code ); ?>" alt="<?php esc_attr_e( 'UPay QR Code', 'wte-upay' ); ?>" />
                    <?php endif; ?>
                </div>

                <?php if ( $transaction_id || $booking_id ) : ?>
                <div class="transaction-info">
                    <?php if ( $booking_id ) : ?>
                    <p><strong><?php esc_html_e( 'Booking ID:', 'wte-upay' ); ?></strong> #<?php echo esc_html( $booking_id ); ?></p>
                    <?php endif; ?>
                    <?php if ( $transaction_id ) : ?>
                    <p><strong><?php esc_html_e( 'Transaction ID:', 'wte-upay' ); ?></strong> <?php echo esc_html( $transaction_id ); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="instructions">
                    <h3><?php esc_html_e( 'How to Pay:', 'wte-upay' ); ?></h3>
                    <ol>
                        <li><?php esc_html_e( 'Open your mobile banking app (UnionBank Online, InstaPay-enabled apps, or other supported payment apps)', 'wte-upay' ); ?></li>
                        <li><?php esc_html_e( 'Select "Scan QR Code" or "Pay with QR"', 'wte-upay' ); ?></li>
                        <li><?php esc_html_e( 'Scan the QR code shown above', 'wte-upay' ); ?></li>
                        <li><?php esc_html_e( 'Confirm the payment amount and complete the transaction', 'wte-upay' ); ?></li>
                        <li><?php esc_html_e( 'Wait for confirmation - you will be automatically redirected once payment is confirmed', 'wte-upay' ); ?></li>
                    </ol>
                </div>

                <div class="status-checking" id="statusChecking">
                    <div class="spinner"></div>
                    <?php esc_html_e( 'Waiting for payment confirmation...', 'wte-upay' ); ?>
                </div>

                <a href="<?php echo esc_url( $return_url ); ?>" class="return-button">
                    <?php esc_html_e( 'Cancel and Return', 'wte-upay' ); ?>
                </a>
            </div>

            <script>
            // Poll for payment status every 5 seconds
            let pollInterval;
            let pollCount = 0;
            const maxPolls = 120; // 10 minutes (120 * 5 seconds)

            function checkPaymentStatus() {
                pollCount++;

                if (pollCount > maxPolls) {
                    clearInterval(pollInterval);
                    document.getElementById('statusChecking').innerHTML = '<?php esc_html_e( 'Payment confirmation timeout. Please check your email for booking confirmation or contact support.', 'wte-upay' ); ?>';
                    return;
                }

                fetch('<?php echo esc_url( $poll_url ); ?>', {
                    method: 'POST',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.status === 'completed') {
                        clearInterval(pollInterval);
                        document.getElementById('statusChecking').innerHTML = '<?php esc_html_e( 'Payment confirmed! Redirecting...', 'wte-upay' ); ?>';
                        window.location.href = data.data.redirect_url;
                    }
                })
                .catch(error => {
                    console.error('Error checking payment status:', error);
                });
            }

            // Start polling after 5 seconds
            setTimeout(function() {
                document.getElementById('statusChecking').classList.add('active');
                checkPaymentStatus();
                pollInterval = setInterval(checkPaymentStatus, 5000);
            }, 5000);
            </script>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * AJAX handler to check payment status
     */
    public function ajax_check_payment_status() {
        if ( ! isset( $_POST['payment_id'] ) && ! isset( $_GET['payment_id'] ) ) {
            wp_send_json_error( array( 'message' => 'Payment ID missing' ) );
        }

        $payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : absint( $_GET['payment_id'] );

        if ( ! $payment_id ) {
            wp_send_json_error( array( 'message' => 'Invalid payment ID' ) );
        }

        // Get payment status from meta
        // Try WTE 6.0+ first
        if ( class_exists( '\WPTravelEngine\Core\Models\Post\Payment' ) ) {
            $payment = \WPTravelEngine\Core\Models\Post\Payment::make_from_id( $payment_id );
            $payment_status = $payment ? $payment->get_meta( 'payment_status' ) : '';
            $transaction_id = $payment ? $payment->get_meta( 'upay_transaction_id' ) : '';
            $booking_id = $payment ? $payment->get_meta( 'booking_id' ) : '';
        } else {
            // Fallback to legacy post meta
            $payment_status = get_post_meta( $payment_id, 'payment_status', true );
            $transaction_id = get_post_meta( $payment_id, 'upay_transaction_id', true );
            $booking_id = get_post_meta( $payment_id, 'booking_id', true );
        }

        // If payment is still pending and we have a transaction ID, check with UPay API
        if ( ( empty( $payment_status ) || $payment_status === 'pending' ) && ! empty( $transaction_id ) ) {
            $upay_api = new WTE_UPay_API();
            $status_response = $upay_api->check_status( $transaction_id );

            if ( ! is_wp_error( $status_response ) && isset( $status_response['state'] ) ) {
                $state = strtolower( $status_response['state'] );

                // Update payment meta
                if ( class_exists( '\WPTravelEngine\Core\Models\Post\Payment' ) && $payment ) {
                    $payment->set_meta( 'upay_status_response', $status_response );
                    $payment->set_meta( 'payment_status', $state );

                    if ( in_array( $state, array( 'success', 'completed', 'paid' ), true ) ) {
                        // Update booking
                        $booking = \WPTravelEngine\Core\Models\Post\Booking::make_from_id( $booking_id );
                        if ( $booking ) {
                            $amount = isset( $status_response['amount'] ) ? (float) $status_response['amount'] : 0;

                            $booking->set_meta( 'wp_travel_engine_booking_status', 'booked' );
                            $booking->update_paid_amount( $amount );
                            $booking->update_due_amount( $amount );
                            $booking->save();

                            // Send confirmation emails
                            wptravelengine_send_booking_emails( $payment_id, 'order_confirmation', 'all' );
                        }
                    }

                    $payment->save();
                } else {
                    // Legacy update
                    update_post_meta( $payment_id, 'upay_status_response', $status_response );
                    update_post_meta( $payment_id, 'payment_status', $state );

                    if ( in_array( $state, array( 'success', 'completed', 'paid' ), true ) ) {
                        $booking = get_post( $booking_id );
                        $amount = isset( $status_response['amount'] ) ? (float) $status_response['amount'] : 0;

                        $booking_meta = array(
                            'wp_travel_engine_booking_status' => 'booked',
                            'paid_amount'                     => +$booking->paid_amount + +$amount,
                            'due_amount'                      => +$booking->due_amount - +$amount,
                        );

                        WTE_Booking::update_booking( $booking_id, array( 'meta_input' => $booking_meta ) );
                        WTE_Booking::send_emails( $payment_id, 'order_confirmation', 'all' );
                    }
                }

                $payment_status = $state;
            }
        }

        // Check if payment is completed
        if ( in_array( $payment_status, array( 'success', 'completed', 'paid' ), true ) ) {
            // Generate redirect URL to thank you page
            $payment_key = function_exists( 'wptravelengine_generate_key' )
                ? wptravelengine_generate_key( $payment_id )
                : wp_generate_password( 20, false );

            $redirect_url = add_query_arg(
                array( 'payment_key' => $payment_key ),
                function_exists( 'wp_travel_engine_get_booking_confirm_url' )
                    ? wp_travel_engine_get_booking_confirm_url()
                    : home_url( '/booking-confirmed' )
            );

            wp_send_json_success( array(
                'status' => 'completed',
                'redirect_url' => $redirect_url,
            ) );
        } else {
            // Payment still pending
            wp_send_json_success( array(
                'status' => 'pending',
            ) );
        }
    }

    /**
     * Display UPay settings
     */
    public function wte_upay_settings() {
        $wp_travel_engine_settings = get_option( 'wp_travel_engine_settings', true );
        ?>
        <div class="wte-upay-form settings">
            <h4><?php esc_html_e( 'UPay Settings', 'wte-upay' ); ?></h4>
            
            <label for="wp_travel_engine_settings[upay_client_id]">
                <?php esc_html_e( 'Client ID (X-IBM-Client-Id):', 'wte-upay' ); ?>
            </label>
            <input type="text" 
                   id="wp_travel_engine_settings[upay_client_id]" 
                   name="wp_travel_engine_settings[upay_client_id]" 
                   value="<?php echo isset( $wp_travel_engine_settings['upay_client_id'] ) ? esc_attr( $wp_travel_engine_settings['upay_client_id'] ) : ''; ?>">
            <div class="settings-note">
                <?php esc_html_e( 'Enter your Union Bank UPay Client ID from Developer Portal', 'wte-upay' ); ?>
            </div>
            
            <label for="wp_travel_engine_settings[upay_client_secret]">
                <?php esc_html_e( 'Client Secret (X-IBM-Client-Secret):', 'wte-upay' ); ?>
            </label>
            <input type="password" 
                   id="wp_travel_engine_settings[upay_client_secret]" 
                   name="wp_travel_engine_settings[upay_client_secret]" 
                   value="<?php echo isset( $wp_travel_engine_settings['upay_client_secret'] ) ? esc_attr( $wp_travel_engine_settings['upay_client_secret'] ) : ''; ?>">
            <div class="settings-note">
                <?php esc_html_e( 'Enter your Union Bank UPay Client Secret from Developer Portal', 'wte-upay' ); ?>
            </div>

            <label for="wp_travel_engine_settings[upay_partner_id]">
                <?php esc_html_e( 'Partner ID (X-Partner-Id):', 'wte-upay' ); ?>
            </label>
            <input type="text" 
                   id="wp_travel_engine_settings[upay_partner_id]" 
                   name="wp_travel_engine_settings[upay_partner_id]" 
                   value="<?php echo isset( $wp_travel_engine_settings['upay_partner_id'] ) ? esc_attr( $wp_travel_engine_settings['upay_partner_id'] ) : ''; ?>">
            <div class="settings-note">
                <?php esc_html_e( 'Enter the Partner ID generated by UnionBank', 'wte-upay' ); ?>
            </div>

            <label for="wp_travel_engine_settings[upay_biller_uuid]">
                <?php esc_html_e( 'Biller UUID:', 'wte-upay' ); ?>
            </label>
            <input type="text" 
                   id="wp_travel_engine_settings[upay_biller_uuid]" 
                   name="wp_travel_engine_settings[upay_biller_uuid]" 
                   value="<?php echo isset( $wp_travel_engine_settings['upay_biller_uuid'] ) ? esc_attr( $wp_travel_engine_settings['upay_biller_uuid'] ) : ''; ?>">
            <div class="settings-note">
                <?php esc_html_e( 'Enter your Biller UUID from UnionBank UPay', 'wte-upay' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Enable/disable checkbox
     */
    public function wte_upay_enable() {
        $wp_travel_engine_settings = get_option( 'wp_travel_engine_settings', true );
        ?>
        <div class="wte-upay-form wp-travel-engine-settings">
            <label for="wp_travel_engine_settings[upay_enable]">
                <?php esc_html_e( 'UPay Payment:', 'wte-upay' ); ?>
                <span class="tooltip" title="<?php esc_attr_e( 'Enable UPay payment gateway for trip bookings', 'wte-upay' ); ?>">
                    <i class="fas fa-question-circle"></i>
                </span>
            </label>
            <input type="checkbox" 
                   id="wp_travel_engine_settings[upay_enable]" 
                   class="upay" 
                   name="wp_travel_engine_settings[upay_enable]" 
                   value="1"
                   <?php checked( isset( $wp_travel_engine_settings['upay_enable'] ) && $wp_travel_engine_settings['upay_enable'] != '' ); ?>>
            <label for="wp_travel_engine_settings[upay_enable]" class="checkbox-label"></label>
        </div>
        <?php
    }

    /**
     * Add meta box for payment details
     */
    public function wpte_upay_add_meta_boxes() {
        $screens = array( 'booking' );
        foreach ( $screens as $screen ) {
            add_meta_box(
                'upay_id',
                __( 'UPay Payment Details', 'wte-upay' ),
                array( $this, 'wte_upay_metabox_callback' ),
                $screen,
                'side',
                'high'
            );
        }
    }

    /**
     * Meta box callback
     */
    public function wte_upay_metabox_callback() {
        include WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/admin/includes/backend/upay.php';
    }

    /**
     * Save meta box data
     */
    public function wp_travel_engine_upay_meta_box_data( $post_id ) {
        if ( isset( $_POST['wp_travel_engine_booking_setting'] ) ) {
            $settings = $_POST['wp_travel_engine_booking_setting'];
            update_post_meta( $post_id, 'wp_travel_engine_booking_setting', $settings );
        }
    }

    /**
     * Enqueue backend assets
     */
    public function enqueue_backend_assets() {
        // Add admin styles if needed
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Add frontend styles/scripts if needed
    }

    /**
     * Add to gateway list (WTE 6.0+)
     */
    public function add_upay_checkout( $payment_methods ) {
        $payment_methods['upay'] = new WTE_UPay();
        return $payment_methods;
    }
    
    public function upay_gateway_list( $gateway_list ) {
        $gateway_list['upay_enable'] = array(
            'label'        => __( 'UPay Payment', 'wte-upay' ),
            'input_class'  => 'upay_enable',
            'info_text'    => __( 'Pay using Union Bank UPay', 'wte-upay' ),
            'public_label' => __( 'UPay Payment', 'wte-upay' ),
            'gateway_id'   => 'upay_enable',
            'icon_url'     => plugin_dir_url( WPTRAVELENGINE_UPAY_FILE__ ) . 'assets/images/upay-logo.png',
        );
        
        return $gateway_list;
    }

    /**
     * Add UPay tab to payment settings
     *
     * @param array $tabs Payment tabs.
     * @return array
     */
    public function _add_upay_tab( $tabs ) {
        $tabs['upay'] = array(
            'label'    => __( 'UPay', 'wte-upay' ),
            'callback' => array( $this, 'render_upay_settings_page' ),
        );
        return $tabs;
    }
    
    public function add_upay_tab( $global_tabs ) {
        if ( isset( $global_tabs['wpte-payment'] ) ) {
            $global_tabs['wpte-payment']['sub_tabs']['wte-upay'] = array(
                'label'        => __( 'UPay Settings', 'wte-upay' ),
                'content_path' => plugin_dir_path( WPTRAVELENGINE_UPAY_FILE__ ) . 'admin/includes/backend/global-settings.php',
                'current'      => false,
            );
        }
        return $global_tabs;
    }
    
    /**
     * Render UPay settings page
     */
    public function render_upay_settings_page() {
        // Clear cache once
        if ( ! get_option( 'upay_cache_cleared' ) ) {
            delete_option( 'wp_travel_engine_sorted_gateways' );
            delete_transient( 'wte_payment_gateways' );
            wp_cache_delete( 'payment_gateways', 'wp-travel-engine' );
            update_option( 'upay_cache_cleared', '1' );
        }
    
        ?>
        <div class="wpte-field wpte-block-wrap">
            <div class="wpte-field wpte-checkbox">
                <label class="wpte-field-label" for="wp_travel_engine_settings[upay_enable]">
                    <?php esc_html_e( 'Enable UPay Payment', 'wte-upay' ); ?>
                </label>
                <?php
                $settings = get_option( 'wp_travel_engine_settings', array() );
                $enabled = isset( $settings['upay_enable'] ) && $settings['upay_enable'] == '1';
                ?>
                <div class="wpte-checkbox-wrap">
                    <input type="checkbox" 
                           id="wp_travel_engine_settings[upay_enable]" 
                           name="wp_travel_engine_settings[upay_enable]" 
                           value="1"
                           <?php checked( $enabled ); ?>>
                    <label for="wp_travel_engine_settings[upay_enable]"></label>
                </div>
                <span class="wpte-tooltip">
                    <?php esc_html_e( 'Check this to enable UPay payment gateway for trip bookings', 'wte-upay' ); ?>
                </span>
            </div>
    
            <?php $this->wte_upay_settings(); ?>
        </div>
        <?php
    }
    
    /**
     * Make sure UPay appears in sorted gateway list
     *
     * @param array $gateways Sorted gateways.
     * @return array
     */
    public function add_to_sorted_gateways( $gateways ) {
        // Add UPay if it's not already there
        if ( ! isset( $gateways['upay_enable'] ) ) {
            $gateways['upay_enable'] = array(
                'label'       => __( 'UPay Payment', 'wte-upay' ),
                'input_class' => 'upay_enable',
                'info_text'   => __( 'Pay using Union Bank UPay', 'wte-upay' ),
                'icon_url'     => plugin_dir_url( WPTRAVELENGINE_UPAY_FILE__ ) . 'assets/images/upay-logo.png',
            );
        }
        return $gateways;
    }
    
    /**
     * Add UPay icon
     *
     * @param string $icon Icon URL.
     * @param string $gateway_id Gateway ID.
     * @return string
     */
    public function add_upay_icon( $icon, $gateway_id ) {
        if ( $gateway_id === 'upay_enable' ) {
            // Return SVG as data URI
            return 'data:image/svg+xml;base64,' . base64_encode('<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.42z" fill="currentColor"/></svg>');
        }
        return $icon;
    }
    
    /**
     * Add UPay to REST API settings response
     *
     * @param array $payment_gateways Payment gateways list.
     * @param object $plugin_settings Plugin settings.
     * @return array
     */
    public function add_upay_to_rest_settings( $payment_gateways, $plugin_settings ) {
        $payment_gateways[] = array(
            'id'     => 'upay_enable',
            'name'   => 'UPay Payment',
            'enable' => wptravelengine_toggled( $plugin_settings->get( 'upay_enable' ) ),
            'icon'   => plugin_dir_url( WPTRAVELENGINE_UPAY_FILE__ ) . 'assets/images/upay-logo.png',
        );
        
        // Add UPay settings
        $settings['upay'] = array(
            'client_id'     => (string) $plugin_settings->get( 'upay_settings.client_id', '' ),
            'client_secret' => (string) $plugin_settings->get( 'upay_settings.client_secret', '' ),
            'partner_id'    => (string) $plugin_settings->get( 'upay_settings.partner_id', '' ),
            'biller_uuid'   => (string) $plugin_settings->get( 'upay_settings.biller_uuid', '' ),
        );
        
        return $payment_gateways;
    }
    
}

new Wte_UPay_Admin();

add_action( 'admin_footer', function() {
    if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'class-wp-travel-engine-admin' ) !== false ) {
        $sorted = wp_travel_engine_get_sorted_payment_gateways();
        echo '<script>console.log("Sorted gateways:", ' . json_encode( array_keys( $sorted ) ) . ');</script>';
    }
});

add_action( 'admin_footer', function() {
    if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'class-wp-travel-engine-admin' ) !== false ) {
        $sorted = wp_travel_engine_get_sorted_payment_gateways();
        echo '<script>console.log("Full gateway data:", ' . json_encode( $sorted ) . ');</script>';
    }
});
