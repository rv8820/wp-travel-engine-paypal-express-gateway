<?php
/**
 * PayPal Express Payment Gateway
 *
 * @package WP_Travel_Engine/includes/payment-gateways
 * @author WP Travel Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\WPTravelEngine\Payments\Payment_Gateway' ) ) {
	/**
	 * Class WTE_Payment_Gateway_Paypal_Express
	 *
	 * @since 2.2.0
	 */
	class WTE_Payment_Gateway_Paypal_Express extends \WPTravelEngine\Payments\Payment_Gateway {
		/**
		 * ID
		 *
		 * @var string
		 */
		public $id = 'paypalexpress_enable';

		/**
		 * Get Label
		 */
		protected function get_label() {
			return __( 'PayPal Express', 'wte-paypalexpress' );
		}

		/**
		 * Get Input Class
		 */
		protected function get_input_class() {
			return 'paypalexpress_enable';
		}

		/**
		 * Get Info Text
		 */
		protected function get_info_text() {
			return __( 'Pay using PayPal Express', 'wte-paypalexpress' );
		}

		/**
		 * Get display (Checkout) icon.
		 *
		 * @return string
		 */
		public function get_display_icon(): string {
			// PayPal Express doesn't currently support custom icons
			// Return empty string to maintain compatibility
			return '';
		}

		/**
		 * Process Payment
		 *
		 * @param  [type] $booking Booking Object.
		 * @param  [type] $payment Payment Object.
		 */
		public function process_payment( $booking, $payment ) {
			// Get Payment Type.
			$payment_type = isset( $_POST['wp_travel_engine_payment_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_travel_engine_payment_mode'] ) ) : 'full_payment';

			// Get Payment Gateway.
			$payment_gateway = ( ! empty( $_POST['wp_travel_engine_payment_method'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wp_travel_engine_payment_method'] ) ) : '';

			/**
			 * Apply action to update payment.
			 */
			do_action( 'wte_payment_gateway_paypalexpress_enable', $payment->ID, $payment_type, $payment_gateway );

			$redirect_uri = $this->get_return_url( $booking, $payment );

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
