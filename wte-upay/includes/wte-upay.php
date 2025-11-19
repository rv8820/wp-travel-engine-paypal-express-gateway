<?php
/**
 * UPay Payment Gateway for WTE 6.0+
 *
 * @package WTE_UPay
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_TRAVEL_ENGINE_VERSION' ) ) {
    exit;
}

use WPTravelEngine\PaymentGateways\BaseGateway;
use WPTravelEngine\Core\Booking\BookingProcess;
use WPTravelEngine\Core\Models\Post\Booking;
use WPTravelEngine\Core\Models\Post\Payment;

if ( version_compare( WP_TRAVEL_ENGINE_VERSION, '6.0.0', '>=' ) ) {
    /**
     * UPay Payment Gateway class
     *
     * @since 1.0.0
     */
    class WTE_UPay extends BaseGateway {

        /**
         * Gateway ID
         *
         * @var string
         */
        protected $gateway_id = 'upay_enable';
        
        /**
         * Get gateway ID
         *
         * @return string
         */
        public function get_gateway_id(): string {
            return 'upay_enable';
        }

        /**
         * Get label
         *
         * @return string
         */
        public function get_label(): string {
            return __( 'UPay Payment', 'wte-upay' );
        }
        
        /**
         * Get info text
         *
         * @return string
         */
        public function get_info_text(): string{
            return __( 'Pay using Union Bank UPay - supports InstaPay, UB Online, and other payment channels' );
        }

        /**
         * Gateway description
         *
         * @return string
         */
        public function get_description(): string {
            return __( 'Pay using Union Bank UPay - supports InstaPay, UB Online, and other payment channels', 'wte-upay' );
        }

        /**
         * Get public label
         *
         * @return string
         */
        public function get_public_label(): string {
            return __( 'UPay Payment', 'wte-upay' );
        }
        
        /**
         * Get gateway icon
         *
         * @return string
         */
        public function get_icon(): string {
            // Check for custom icon in settings
            $settings = get_option( 'wp_travel_engine_settings', array() );
            $custom_icon = isset( $settings['upay_settings']['icon_url'] ) ? $settings['upay_settings']['icon_url'] : '';

            // Return custom icon if available
            if ( ! empty( $custom_icon ) ) {
                return '<img src="' . esc_url( $custom_icon ) . '" alt="UPay Payment" style="max-height: 30px; width: auto;" />';
            }

            // Return default SVG icon
            return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.42z" fill="currentColor"/></svg>';
        }
        
        /**
         * Get input class
         *
         * @return string
         */
        public function get_input_class(): string {
            return 'upay_enable';
        }

        /**
         * Process payment
         *
         * @param Booking        $booking          Booking object.
         * @param Payment        $payment          Payment object.
         * @param BookingProcess $booking_instance Booking process instance.
         *
         * @return void
         */
        public function process_payment( Booking $booking, Payment $payment, BookingProcess $booking_instance ): void {

            // Check if this is a callback from UPay (payment confirmation)
            if ( isset( $_POST['wte_upay_payment_details'] ) ) {
                $response = json_decode( wp_unslash( $_POST['wte_upay_payment_details'] ), true );

                if ( $response && isset( $response['state'] ) ) {
                    // Store gateway response
                    $payment->set_meta( 'gateway_response', $response );

                    // Set payment status
                    $payment->set_meta( 'payment_status', strtolower( $response['state'] ) );

                    // Store transaction ID
                    if ( isset( $response['transactionId'] ) ) {
                        $payment->set_meta( 'upay_transaction_id', $response['transactionId'] );
                    }

                    // Store UUID
                    if ( isset( $response['uuid'] ) ) {
                        $payment->set_meta( 'upay_uuid', $response['uuid'] );
                    }

                    // Process successful payment
                    if ( isset( $response['amount'] ) && in_array( strtolower( $response['state'] ), array( 'success', 'completed', 'paid' ), true ) ) {
                        $amount = (float) $response['amount'];

                        // Set payment amount
                        $payment_amount = array(
                            'value'    => $amount,
                            'currency' => isset( $response['currency'] ) ? $response['currency'] : 'PHP',
                        );
                        $payment->set_meta( 'payment_amount', $payment_amount );

                        // Update booking status
                        $booking->set_meta( 'wp_travel_engine_booking_status', 'booked' );
                        $booking->update_paid_amount( $amount );
                        $booking->update_due_amount( $amount );
                    }
                }

                // Save payment and booking
                $payment->save();
                $booking->save();

                // Send confirmation emails
                wptravelengine_send_booking_emails( $payment->ID, 'order_confirmation', 'all' );

                // Fire completion action
                do_action( 'wptravelengine_process_payment_complete', 'upay_payment', $booking, $payment );

                return;
            }

            // Initial payment request - redirect to UPay
            try {
                // Log start of payment processing
                if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                    error_log( '=== UPay Payment Processing Started ===' );
                    error_log( 'Payment ID: ' . $payment->ID );
                    error_log( 'Booking ID: ' . $booking->ID );
                }

                // Get payment amount
                $payment_amount = $payment->get_meta( 'payable' );
                $amount = isset( $payment_amount['amount'] ) ? $payment_amount['amount'] : 0;

                if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                    error_log( 'Payment Amount Data: ' . print_r( $payment_amount, true ) );
                    error_log( 'Amount: ' . $amount );
                }

                if ( empty( $amount ) || $amount <= 0 ) {
                    throw new Exception( __( 'Invalid payment amount. Please try again.', 'wte-upay' ) );
                }

                // Get booking details
                $booking_meta = $booking->get_all_meta();

                if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                    error_log( 'Booking Meta Keys: ' . print_r( array_keys( $booking_meta ), true ) );
                    error_log( 'Full Booking Meta: ' . print_r( $booking_meta, true ) );
                }

                // Extract email - check multiple possible locations
                $email = '';

                // Try wptravelengine_billing_details first (unserialized from index 0)
                if ( isset( $booking_meta['wptravelengine_billing_details'][0] ) ) {
                    $billing_details = maybe_unserialize( $booking_meta['wptravelengine_billing_details'][0] );
                    if ( is_array( $billing_details ) && isset( $billing_details['email'] ) ) {
                        $email = $billing_details['email'];
                    }
                }

                // Fallback to billing_info
                if ( empty( $email ) && isset( $booking_meta['billing_info'][0] ) ) {
                    $billing_info = maybe_unserialize( $booking_meta['billing_info'][0] );
                    if ( is_array( $billing_info ) && isset( $billing_info['email'] ) ) {
                        $email = $billing_info['email'];
                    }
                }

                // Fallback to travelers details
                if ( empty( $email ) && isset( $booking_meta['wptravelengine_travelers_details'][0] ) ) {
                    $travelers_details = maybe_unserialize( $booking_meta['wptravelengine_travelers_details'][0] );
                    if ( is_array( $travelers_details ) && isset( $travelers_details[0]['email'] ) ) {
                        $email = $travelers_details[0]['email'];
                    }
                }

                // Last fallback to booking settings
                if ( empty( $email ) && isset( $booking_meta['wp_travel_engine_booking_setting'][0] ) ) {
                    $booking_setting = maybe_unserialize( $booking_meta['wp_travel_engine_booking_setting'][0] );
                    if ( is_array( $booking_setting ) && isset( $booking_setting['place_order']['booking']['email'] ) ) {
                        $email = $booking_setting['place_order']['booking']['email'];
                    }
                }

                // Extract phone - check multiple possible locations
                $phone = '';

                // Try travelers details first (has phone)
                if ( isset( $booking_meta['wptravelengine_travelers_details'][0] ) ) {
                    $travelers_details = maybe_unserialize( $booking_meta['wptravelengine_travelers_details'][0] );
                    if ( is_array( $travelers_details ) && isset( $travelers_details[0]['phone'] ) ) {
                        $phone = $travelers_details[0]['phone'];
                    }
                }

                // Fallback to billing details
                if ( empty( $phone ) && isset( $booking_meta['wptravelengine_billing_details'][0] ) ) {
                    $billing_details = maybe_unserialize( $booking_meta['wptravelengine_billing_details'][0] );
                    if ( is_array( $billing_details ) && isset( $billing_details['phone'] ) ) {
                        $phone = $billing_details['phone'];
                    }
                }

                // Fallback to billing_info
                if ( empty( $phone ) && isset( $booking_meta['billing_info'][0] ) ) {
                    $billing_info = maybe_unserialize( $booking_meta['billing_info'][0] );
                    if ( is_array( $billing_info ) && isset( $billing_info['phone'] ) ) {
                        $phone = $billing_info['phone'];
                    }
                }

                // Last fallback to booking settings
                if ( empty( $phone ) && isset( $booking_meta['wp_travel_engine_booking_setting'][0] ) ) {
                    $booking_setting = maybe_unserialize( $booking_meta['wp_travel_engine_booking_setting'][0] );
                    if ( is_array( $booking_setting ) && isset( $booking_setting['place_order']['booking']['phone'] ) ) {
                        $phone = $booking_setting['place_order']['booking']['phone'];
                    }
                }

                // Extract trip ID - check cart_info or order_trips
                $trip_id = 0;
                $trip_name = 'Trip Booking';
                if ( isset( $booking_meta['order_trips'] ) && is_array( $booking_meta['order_trips'] ) ) {
                    $order_trips = $booking_meta['order_trips'];
                    if ( ! empty( $order_trips ) ) {
                        $first_trip = reset( $order_trips );
                        if ( isset( $first_trip['ID'] ) ) {
                            $trip_id = $first_trip['ID'];
                            $trip_name = get_the_title( $trip_id );
                        }
                    }
                } elseif ( isset( $booking_meta['cart_info']['items'] ) && is_array( $booking_meta['cart_info']['items'] ) ) {
                    $items = $booking_meta['cart_info']['items'];
                    if ( ! empty( $items ) ) {
                        $first_item = reset( $items );
                        if ( isset( $first_item['trip_id'] ) ) {
                            $trip_id = $first_item['trip_id'];
                            $trip_name = get_the_title( $trip_id );
                        }
                    }
                } elseif ( isset( $booking_meta['wp_travel_engine_booking_setting']['place_order']['tid'] ) ) {
                    $trip_id = $booking_meta['wp_travel_engine_booking_setting']['place_order']['tid'];
                    $trip_name = get_the_title( $trip_id );
                }

                if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                    error_log( 'Email: ' . $email );
                    error_log( 'Phone: ' . $phone );
                    error_log( 'Trip ID: ' . $trip_id );
                    error_log( 'Trip Name: ' . $trip_name );
                }

                // Initialize UPay API
                $upay_api = new WTE_UPay_API();

                // Check if credentials are configured
                $settings = get_option( 'wp_travel_engine_settings', array() );
                $client_id = isset( $settings['upay_settings']['client_id'] ) ? $settings['upay_settings']['client_id'] : '';
                $client_secret = isset( $settings['upay_settings']['client_secret'] ) ? $settings['upay_settings']['client_secret'] : '';

                if ( empty( $client_id ) || empty( $client_secret ) ) {
                    throw new Exception( __( 'UPay payment gateway is not configured. Please contact the site administrator.', 'wte-upay' ) );
                }

                if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                    error_log( 'UPay Credentials Check: Client ID = ' . ( ! empty( $client_id ) ? 'Set' : 'Not Set' ) );
                    error_log( 'UPay Credentials Check: Client Secret = ' . ( ! empty( $client_secret ) ? 'Set' : 'Not Set' ) );
                }

                // Generate unique order ID
                $order_id = $upay_api->generate_sender_ref_id( $payment->ID );

                // Prepare payment data
                $payment_data = array(
                    'order_id'       => $order_id,
                    'email'          => $email,
                    'amount'         => $amount,
                    'payment_method' => 'instapay', // Default to InstaPay
                    'mobile'         => $phone,
                    'callback_url'   => home_url( '?upay_callback=1&payment_id=' . $payment->ID ),
                    'references'     => array(
                        array(
                            'index' => 0,
                            'value' => 'Booking #' . $booking->ID,
                        ),
                        array(
                            'index' => 1,
                            'value' => $trip_name,
                        ),
                    ),
                );

                if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                    error_log( 'Payment Data: ' . print_r( $payment_data, true ) );
                }

                // Store order ID
                $payment->set_meta( 'upay_sender_ref_id', $order_id );
                $payment->set_meta( 'payment_status', 'pending' );
                $payment->save();

                // Set booking status to pending
                $booking->set_meta( 'wp_travel_engine_booking_status', 'pending' );
                $booking->save();

                if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                    error_log( 'Creating UPay transaction via API...' );
                }

                // Create transaction via UPay API
                $response = $upay_api->create_transaction( $payment_data );

                if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                    error_log( 'UPay API Response: ' . print_r( $response, true ) );
                }

                if ( is_wp_error( $response ) ) {
                    throw new Exception( $response->get_error_message() );
                }

                // Store transaction details
                if ( isset( $response['transactionId'] ) ) {
                    $payment->set_meta( 'upay_transaction_id', $response['transactionId'] );
                }
                if ( isset( $response['uuid'] ) ) {
                    $payment->set_meta( 'upay_uuid', $response['uuid'] );
                }

                // Store full response
                $payment->set_meta( 'upay_response', $response );
                $payment->save();

                // Redirect based on payment method
                if ( isset( $response['qrCode'] ) && ! empty( $response['qrCode'] ) ) {
                    // InstaPay - show QR code
                    $payment->set_meta( 'upay_qr_code', $response['qrCode'] );
                    $payment->save();

                    $redirect_url = add_query_arg(
                        array(
                            'payment_id' => $payment->ID,
                            'action'     => 'upay_qr',
                        ),
                        home_url()
                    );

                    if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                        error_log( 'Redirecting to QR code page: ' . $redirect_url );
                    }
                } else {
                    // Other methods - redirect to payment URL if provided
                    $redirect_url = isset( $response['paymentUrl'] ) ? $response['paymentUrl'] : home_url();

                    if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ) {
                        error_log( 'Redirecting to payment URL: ' . $redirect_url );
                    }
                }

                // Perform redirect
                wp_safe_redirect( $redirect_url );
                exit;

            } catch ( Exception $e ) {
                // Log error
                error_log( '=== UPay Payment Error ===' );
                error_log( 'Error Message: ' . $e->getMessage() );
                error_log( 'Error Trace: ' . $e->getTraceAsString() );

                // Set error in session for display to user
                if ( function_exists( 'WTE' ) && WTE()->session ) {
                    $session = WTE()->session;
                    $errors  = $session->get( 'wp_travel_engine_errors' );
                    if ( ! is_array( $errors ) ) {
                        $errors = array();
                    }
                    $errors[] = sprintf(
                        __( 'UPay Payment Error: %s', 'wte-upay' ),
                        $e->getMessage()
                    );
                    $session->set( 'wp_travel_engine_errors', $errors );
                }

                // Set payment as failed
                $payment->set_meta( 'payment_status', 'failed' );
                $payment->set_meta( 'payment_error', $e->getMessage() );
                $payment->save();

                // Redirect back to checkout
                wp_safe_redirect( wp_travel_engine_get_checkout_url() );
                exit;
            }
        }
    }
}
