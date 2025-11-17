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
            error_log( 'UPay: process_upay_payment - No payment ID provided' );
            return;
        }

        error_log( 'UPay: process_upay_payment called - Payment ID: ' . $payment_id . ', Type: ' . $type . ', Gateway: ' . $gateway );

        try {
            $booking_id = get_post_meta( $payment_id, 'booking_id', true );
            $booking    = get_post( $booking_id );
            $payable    = get_post_meta( $payment_id, 'payable', true );

            error_log( 'UPay: Booking ID: ' . $booking_id );
            error_log( 'UPay: Payable amount: ' . print_r( $payable, true ) );

            if ( ! $booking || ! $payable ) {
                throw new Exception( __( 'Invalid booking or payment data', 'wte-upay' ) );
            }

            // Get booking details - try multiple meta keys for compatibility
            $booking_meta = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting', true );

            if ( empty( $booking_meta ) ) {
                // Try alternative meta key
                $booking_meta = get_post_meta( $booking_id, 'wp_travel_engine_placeorder_setting', true );
                error_log( 'UPay: Using alternative booking meta key (wp_travel_engine_placeorder_setting)' );
            }

            error_log( 'UPay: Full booking meta: ' . print_r( $booking_meta, true ) );

            // Initialize UPay API
            $upay_api = new WTE_UPay_API();

            // Extract email and phone with multiple fallback options
            $email = '';
            $phone = '';

            // Try different paths for email
            if ( isset( $booking_meta['place_order']['booking']['email'] ) ) {
                $email = $booking_meta['place_order']['booking']['email'];
            } elseif ( isset( $booking_meta['place_order']['email'] ) ) {
                $email = $booking_meta['place_order']['email'];
            } elseif ( isset( $booking_meta['booking']['email'] ) ) {
                $email = $booking_meta['booking']['email'];
            } elseif ( isset( $booking_meta['email'] ) ) {
                $email = $booking_meta['email'];
            }

            // Try different paths for phone
            if ( isset( $booking_meta['place_order']['booking']['phone'] ) ) {
                $phone = $booking_meta['place_order']['booking']['phone'];
            } elseif ( isset( $booking_meta['place_order']['phone'] ) ) {
                $phone = $booking_meta['place_order']['phone'];
            } elseif ( isset( $booking_meta['booking']['phone'] ) ) {
                $phone = $booking_meta['booking']['phone'];
            } elseif ( isset( $booking_meta['phone'] ) ) {
                $phone = $booking_meta['phone'];
            }

            // Additional fallback: try to get from payment meta
            if ( empty( $email ) ) {
                $email = get_post_meta( $payment_id, 'billing_email', true );
                if ( empty( $email ) ) {
                    $email = get_post_meta( $payment_id, 'wp_travel_engine_booking_setting_email', true );
                }
            }

            if ( empty( $phone ) ) {
                $phone = get_post_meta( $payment_id, 'billing_phone', true );
                if ( empty( $phone ) ) {
                    $phone = get_post_meta( $payment_id, 'wp_travel_engine_booking_setting_phone', true );
                }
            }

            error_log( 'UPay: Extracted email: ' . $email );
            error_log( 'UPay: Extracted phone: ' . $phone );

            // Get trip ID for reference
            $trip_id = '';
            if ( isset( $booking_meta['place_order']['tid'] ) ) {
                $trip_id = $booking_meta['place_order']['tid'];
            } elseif ( isset( $booking_meta['tid'] ) ) {
                $trip_id = $booking_meta['tid'];
            }

            // Prepare payment data
            $payment_data = array(
                'order_id'       => $upay_api->generate_sender_ref_id( $payment_id ),
                'email'          => $email,
                'amount'         => $payable['amount'],
                'payment_method' => 'instapay', // or 'UB Online' - can be made configurable
                'mobile'         => $phone,
                'callback_url'   => home_url( '?upay_callback=1&payment_id=' . $payment_id ),
                'references'     => array(
                    array(
                        'index' => 0,
                        'value' => 'Booking #' . $booking_id,
                    ),
                    array(
                        'index' => 1,
                        'value' => ! empty( $trip_id ) ? get_the_title( $trip_id ) : 'Trip Booking',
                    ),
                ),
            );

            error_log( 'UPay: Payment Data: ' . print_r( $payment_data, true ) );

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
        error_log( 'UPay: map_payment_data_to_new_booking_structure called - Payment ID: ' . $payment_id . ', Type: ' . $type . ', Gateway: ' . $gateway );

        if ( $gateway !== 'upay_enable' ) {
            error_log( 'UPay: Gateway mismatch, returning. Expected: upay_enable, Got: ' . $gateway );
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
        error_log( 'UPay: upay_handle_payment_process called with booking data: ' . print_r( $booking_data, true ) );

        // Extract payment ID from booking data
        $payment_id = null;

        // Try different structures for compatibility with various WTE versions
        if ( isset( $booking_data['payment_id'] ) ) {
            $payment_id = $booking_data['payment_id'];
        } elseif ( isset( $booking_data['payment']->ID ) ) {
            $payment_id = $booking_data['payment']->ID;
        } elseif ( isset( $booking_data['pid'] ) ) {
            $payment_id = $booking_data['pid'];
        }

        if ( ! $payment_id ) {
            error_log( 'UPay: Could not extract payment ID from booking data' );
            return;
        }

        // Check if UPay is the selected gateway
        $payment_gateway = get_post_meta( $payment_id, 'wp_travel_engine_payment_gateway', true );

        if ( $payment_gateway !== 'upay_enable' ) {
            error_log( 'UPay: Payment gateway is not UPay (' . $payment_gateway . '), skipping' );
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
