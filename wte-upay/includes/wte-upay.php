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
