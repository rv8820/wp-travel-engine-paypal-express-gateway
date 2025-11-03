<?php

/**
 * Srinivas Tamada.
 * http://www.9lessons.info
 */

/**
 * PayPal Experss Class
 */
class paypalExpress {

	public function paypalCheck( $paymentID, $pid, $payerID, $paymentToken ) {
		if ( ! empty( $_GET['paymentid'] ) && ! empty( $_GET['payerID'] ) && ! empty( $_GET['token'] ) && ! empty( $_GET['pid'] ) ) {
			$paymentID = $_GET['paymentid'];
			$payerID   = $_GET['payerID'];
			$token     = $_GET['token'];
			$pid       = $_GET['pid'];

			$ch      = curl_init();
			$options = get_option( 'wp_travel_engine_settings', true );

			$clientId = isset( $options['paypalexpress_client_id'] ) ? esc_attr( $options['paypalexpress_client_id'] ) : '';
			$secret   = isset( $options['paypalexpress_secret'] ) ? esc_attr( $options['paypalexpress_secret'] ) : '';
			curl_setopt( $ch, CURLOPT_URL, PayPal_BASE_URL . 'oauth2/token' );
			curl_setopt( $ch, CURLOPT_HEADER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_USERPWD, $clientId . ':' . $secret );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials' );
			$result      = curl_exec( $ch );
			$accessToken = null;

			if ( empty( $result ) ) {
				return false;
			} else {
				$json        = json_decode( $result );
				$accessToken = $json->access_token;
				$curl        = curl_init( PayPal_BASE_URL . 'payments/payment/' . $paymentID );
				curl_setopt( $curl, CURLOPT_POST, false );
				curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $curl, CURLOPT_HEADER, false );
				curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
				curl_setopt(
					$curl,
					CURLOPT_HTTPHEADER,
					array(
						'Authorization: Bearer ' . $accessToken,
						'Accept: application/json',
						'Content-Type: application/xml',
					)
				);
				$response = curl_exec( $curl );
				$result   = json_decode( $response );

				$state          = $result->state;
				$total          = $result->transactions[0]->amount->total;
				$currency       = $result->transactions[0]->amount->currency;
				$subtotal       = $result->transactions[0]->amount->details->subtotal;
				$recipient_name = $result->transactions[0]->item_list->shipping_address->recipient_name;
				curl_close( $ch );
				curl_close( $curl );

				if ( $state == 'approved' ) {
					$cost        = $_SESSION['trip-cost'];
					$cost        = str_replace( ',', '', $cost );
					$obj         = new Wp_Travel_Engine_Functions();
					$country     = $obj->Wte_countryCodeToName( $result->payer->payer_info->shipping_address->country_code );
					$order_metas =
					array(
						'place_order' => array(
							'traveler' => $_SESSION['travelers'],
							'cost'     => $result->transactions[0]->amount->total,
							'due'      => $_SESSION['due'],
							'tid'      => $_SESSION['trip-id'],
							'tname'    => get_the_title( $_SESSION['trip-id'] ),
							'datetime' => $_SESSION['trip-date'],
							'booking'  => array(
								'fname'   => esc_attr( $result->payer->payer_info->first_name ),
								'lname'   => esc_attr( $result->payer->payer_info->last_name ),
								'email'   => esc_attr( $result->payer->payer_info->email ),
								'address' => esc_attr( $result->payer->payer_info->shipping_address->state ),
								'city'    => esc_attr( $result->payer->payer_info->shipping_address->city ),
								'country' => esc_attr( $country ),
							),
							'payment'  => array(
								'paymentid' => $_GET['paymentid'],
								'payerid'   => $_GET['payerID'],
								'token'     => $_GET['token'],
							),
						),
					);
					return $order_metas;
				}
			}
		}
	}
}

