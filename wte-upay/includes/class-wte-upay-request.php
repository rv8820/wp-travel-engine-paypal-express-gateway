<?php
/**
 * UPay Payment Gateway Request Class
 * For WTE versions < 6.0
 *
 * @package WTE_UPay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( '\WPTravelEngine\Payments\Payment_Gateway' ) ) {
    
    /**
     * WTE_Payment_Gateway_UPay class
     */
    class WTE_Payment_Gateway_UPay extends \WPTravelEngine\Payments\Payment_Gateway {
        
        /**
         * Gateway ID
         *
         * @var string
         */
        public $id = 'upay_enable';

        /**
         * Get label
         *
         * @return string
         */
        protected function get_label() {
            return __( 'UPay', 'wte-upay' );
        }

        /**
         * Get input class
         *
         * @return string
         */
        protected function get_input_class() {
            return 'upay_enable';
        }

        /**
         * Get info text
         *
         * @return string
         */
        protected function get_info_text() {
            return __( 'Pay using Union Bank UPay', 'wte-upay' );
        }

        /**
         * Process payment
         *
         * @param object $booking Booking object.
         * @param object $payment Payment object.
         * @return array
         */
        public function process_payment( $booking, $payment ) {
            // Get payment type
            $payment_type = isset( $_POST['wp_travel_engine_payment_mode'] ) 
                ? sanitize_text_field( wp_unslash( $_POST['wp_travel_engine_payment_mode'] ) ) 
                : 'full_payment';

            // Get payment gateway
            $payment_gateway = ( ! empty( $_POST['wp_travel_engine_payment_method'] ) ) 
                ? sanitize_text_field( wp_unslash( $_POST['wp_travel_engine_payment_method'] ) ) 
                : '';

            /**
             * Trigger payment processing action
             */
            do_action( 'wte_payment_gateway_upay_enable', $payment->ID, $payment_type, $payment_gateway );

            // Get redirect URL
            $redirect_uri = $this->get_return_url( $booking, $payment );

            // Redirect if not AJAX
            if ( ! wp_doing_ajax() ) {
                wp_safe_redirect( $redirect_uri );
            }

            return array(
                'result'   => 'success',
                'redirect' => $redirect_uri,
            );
        }
    }
}
