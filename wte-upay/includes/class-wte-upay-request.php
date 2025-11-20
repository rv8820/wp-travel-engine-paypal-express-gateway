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
         * Display icon URL
         *
         * @var string
         */
        public $display_icon = '';

        /**
         * Constructor
         */
        public function __construct() {
            // Set custom icon if available
            $settings = get_option( 'wp_travel_engine_settings', array() );
            $custom_icon = isset( $settings['upay_settings']['icon_url'] ) ? $settings['upay_settings']['icon_url'] : '';

            if ( ! empty( $custom_icon ) ) {
                $this->display_icon = esc_url( $custom_icon );
            }

            parent::__construct();
        }

        /**
         * Get label
         *
         * @return string
         */
        protected function get_label() {
            return __( 'UPay Payment', 'wte-upay' );
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
         * Get icon - CRITICAL for display in sortable list
         *
         * @return string
         */
        public function get_icon() {
            return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.42z" fill="currentColor"/></svg>';
        }

        /**
         * Get display (Checkout) icon.
         *
         * @return string
         */
        public function get_display_icon(): string {
            // Return custom icon if set
            if ( ! empty( $this->display_icon ) ) {
                return $this->display_icon;
            }

            // Return default icon URL
            return plugin_dir_url( WPTRAVELENGINE_UPAY_FILE__ ) . 'assets/images/upay-logo.png';
        }

        /**
         * Get public label - what customers see
         *
         * @return string
         */
        public function get_public_label() {
            return __( 'UPay Payment', 'wte-upay' );
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