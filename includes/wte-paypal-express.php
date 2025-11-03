<?php
/**
 * Paypal Express Payment Gateway
 *
 * @package WPTravelEngine\PaymentGateways
 * @since 6.0.0
 */

 if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_TRAVEL_ENGINE_VERSION' ) ){
	exit;
}

use WPTravelEngine\PaymentGateways\BaseGateway;
use WPTravelEngine\Core\Booking\BookingProcess;
use WPTravelEngine\Core\Models\Post\Booking;
use WPTravelEngine\Core\Models\Post\Payment;

if ( version_compare( WP_TRAVEL_ENGINE_VERSION, '6.0.0', '>=' ) ) {
/**
 * Paypal Express Payment Gateway
 *
 * @since 6.0.0
 */

Class WTE_Paypal_Express extends BaseGateway{

    /**
     * Gateway ID.
     *
     * @var string
     */
    protected $gateway_id = 'paypalexpress_enable';
    
    /**
     * Get gateway id.
     *
     * @return string
     */
    public function get_gateway_id(): string {
        return 'paypalexpress_enable';
    }

    /**
     * Get label
     *
     * @return string
     */
    public function get_label(): string {
        return __( 'PayPal-Express Checkout', 'wte-paypalexpress' );
    }

    /**
     * Gateway Description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Please check this to enable Paypal Express booking system for trip booking and fill the account info below.', 'wte-paypalexpress' );
    }

    /**
     * Get public label.
     *
     * @return string
     */
    public function get_public_label(): string {
        return __( 'PayPal-Express Checkout', 'wte-paypalexpress' );
    }


    /**
     * Process Payment.
     *
     * @param Booking $booking Booking.
     * @param Payment $payment Payment.
     * @param BookingProcess $booking_instance Booking Process.
     *
     * @return void
     */
    public function process_payment( Booking $booking, Payment $payment, BookingProcess $booking_instance ): void {

        if ( isset( $_POST['wte_paypal_express_payment_details'] ) ) { // phpcs:ignore
            $response = json_decode( wp_unslash( $_POST['wte_paypal_express_payment_details'] ), true ); // phpcs:ignore
			if ( $response && isset( $response['status'] ) ) {
                $payment->set_meta( 'gateway_response', $response );
            
                $payment->set_meta( 'payment_status', strtolower( $response['status'] ) );

				if ( isset( $response['intent'] ) ) {
					$payment->set_meta( 'payment_intent', $response['intent'] );
				}

				if ( isset( $response['purchase_units'][0] ) ) {
                    $amount = (float) $response['purchase_units'][0]['amount']['value'];
					$payment_amount['value']        = $amount;
					$payment_amount['currency']     = $response['purchase_units'][0]['amount']['currency_code'];
                    $payment->set_meta( 'payment_amount', $payment_amount );
                    $booking->set_meta( 'wp_travel_engine_booking_status', 'booked' );
                    $booking->update_paid_amount( (float) $amount );
                    $booking->update_due_amount( (float) $amount );
				}
			}
           
            $payment->save();
            $booking->save();

            wptravelengine_send_booking_emails( $payment->ID, 'order_confirmation', 'all' );

            do_action( 'wptravelengine_process_payment_complete', 'paypalexpress_payment', $booking, $payment );
		}
    }

}
}