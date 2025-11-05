<?php
/**
 * Admin Settings & Payment Handler
 *
 * @package WTE_UPay
 */

class Wte_UPay_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wte_upay_settings', array( $this, 'wte_upay_settings' ) );
        add_action( 'wte_upay_enable', array( $this, 'wte_upay_enable' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_backend_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        
        $wp_travel_engine_settings = get_option( 'wp_travel_engine_settings', true );
        
        add_action( 'add_meta_boxes', array( $this, 'wpte_upay_add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'wp_travel_engine_upay_meta_box_data' ) );
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        // Register gateway
        if ( defined( 'WP_TRAVEL_ENGINE_VERSION' ) && version_compare( WP_TRAVEL_ENGINE_VERSION, '6.0.0', '>=' ) ) {
            add_filter( 'wptravelengine_registering_payment_gateways', array( $this, 'add_upay_checkout' ) );
        } else {
            add_action( 'wp_travel_engine_available_payment_gateways', array( $this, 'upay_gateway_list' ) );
        }

        // Handle payment process
        add_action( 'wte_payment_gateway_upay_enable', array( $this, 'process_upay_payment' ), 10, 3 );
        add_filter( 'wptravelengine_payment_gateways', array( $this, 'add_payment_gateway' ) );
        add_filter( 'wpte_settings_get_global_tabs', array( $this, 'upay_add_settings' ) );

        // Handle callback
        add_action( 'init', array( $this, 'handle_upay_callback' ) );
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

    /**
     * Add to gateway list (WTE < 6.0)
     */
    public function upay_gateway_list( $gateway_list ) {
        $gateway_list['upay_enable'] = array(
            'label'       => __( 'UPay Payment', 'wte-upay' ),
            'input_class' => 'upay',
            'info_text'   => __( 'Pay using Union Bank UPay', 'wte-upay' ),
        );
        return $gateway_list;
    }

    /**
     * Add settings tab
     */
    public function upay_add_settings( $tabs ) {
        $tabs['upay'] = array(
            'label'   => __( 'UPay Settings', 'wte-upay' ),
            'content' => array( $this, 'wte_upay_settings' ),
        );
        return $tabs;
    }
}

new Wte_UPay_Admin();
