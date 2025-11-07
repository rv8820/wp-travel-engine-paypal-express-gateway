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
            
            // Check if callback data is present
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
            }
        }
    }
}
