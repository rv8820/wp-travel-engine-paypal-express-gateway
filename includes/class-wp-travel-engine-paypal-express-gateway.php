<?php
/**
 * Admin Setings
 */
class Wte_PayPalExpress_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wte_paypalexpress_settings', array( $this, 'wte_paypalexpress_settings' ) );
		add_action( 'wte_paypalexpress_enable', array( $this, 'wte_paypalexpress_enable' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'Wte_enqueue_backend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'Wte_enqueue_frontend_assets' ) );
		$wp_travel_engine_settings = get_option( 'wp_travel_engine_settings', true );
		if ( isset( $wp_travel_engine_settings['paypalexpress_enable'] ) && $wp_travel_engine_settings['paypalexpress_enable'] != '' ) {
			add_action( 'wte_paypalexpress_form', array( $this, 'paypalexpress_form' ) );
		}
		add_action( 'wp_ajax_paypal_button_container', array( $this, 'paypal_button_container' ) );
		add_action( 'wp_ajax_nopriv_paypal_button_container', array( $this, 'paypal_button_container' ) );
		add_action( 'add_meta_boxes', array( $this, 'wpte_paypalexpress_add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'wp_travel_engine_paypalexpress_meta_box_data' ) );
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Add filter for the sortable gateway list.
		if ( defined( 'WP_TRAVEL_ENGINE_VERSION' ) && version_compare( WP_TRAVEL_ENGINE_VERSION, '6.0.0', '>=' ) ) {
			add_filter( 'wptravelengine_registering_payment_gateways', array( $this, 'add_paypal_express_checkout' ) );
		} else {
			add_action( 'wp_travel_engine_available_payment_gateways', array(
				$this,
				'paypal_express_gateway_list',
			) );
		}


		// Handle paymnets with paypalexpress checkout.
		add_action( 'wp_travel_engine_after_booking_process_completed', array( $this, 'paypalexpress_handle_payment_process' ) );

		// Filter - Global settings | New backend UI.
		add_filter( 'wpte_settings_get_global_tabs', array( $this, 'paypalexpress_add_settings' ) );

		add_action( 'wte_payment_gateway_paypalexpress_enable', array( $this, 'map_payment_data_to_new_booking_structure' ), 10, 3 );

		/**
		 * Add filter for the sortable payment gateway list.
		 *
		 * @since 2.2.0
		 */
		add_filter( 'wptravelengine_payment_gateways', array( $this, 'add_payment_gateway' ) );
	}

	/**
	 * Add Paypal Express Payment Gateway to Payment Gateway List.
	 *
	 * @param object $payment_gateways Payment Gateways.
	 *
	 * @since 2.2.0
	 */
	public function add_payment_gateway( $payment_gateways ) {
		$payment_gateways['paypalexpress_enable'] = new \WTE_Payment_Gateway_Paypal_Express();
		return $payment_gateways;
	}

	/**
	 * Map Payment Data to New Booking Structure.
	 *
	 * @param [type] $payment_id Payment ID.
	 * @param [type] $type Payment Type.
	 * @param [type] $gateway Payment Gateway.
	 *
	 * @since 2.1.0
	 */
	public function map_payment_data_to_new_booking_structure( $payment_id, $type = 'full_payment', $gateway = '' ) {
		if ( ! $payment_id ) {
			return;
		}
		$booking = get_post( get_post_meta( $payment_id, 'booking_id', true ) );

        if ( isset( $_POST['wte_paypal_express_payment_details'] ) ) { // phpcs:ignore
            $response = json_decode( wp_unslash( $_POST['wte_paypal_express_payment_details'] ), true ); // phpcs:ignore
			$payment_data = array();
			if ( $response && isset( $response['status'] ) ) {
				$payment_data = array(
					'payment_status' => strtolower( $response['status'] ),
				);

				if ( isset( $response['intent'] ) ) {
					$payment_data['payment_intent'] = $response['intent'];
				}

				if ( isset( $response['purchase_units'][0] ) ) {
					$payment_amount['value']        = $response['purchase_units'][0]['amount']['value'];
					$payment_amount['currency']     = $response['purchase_units'][0]['amount']['currency_code'];
					$payment_data['payment_amount'] = $payment_amount;
				}
			}
			$payment_data['gateway_response'] = $response;

			WTE_Booking::update_booking(
				$payment_id,
				array(
					'meta_input' => $payment_data,
				)
			);

			if ( in_array( $payment_data['payment_status'], array( 'completed', 'success', 'captured' ), false ) ) {
				$amount = $payment_data['payment_amount']['value'];
				$booking_meta['wp_travel_engine_booking_status'] = 'booked';
				$booking_meta['paid_amount']                     = +$booking->paid_amount + +$amount;
				$booking_meta['due_amount']                      = +$booking->due_amount - +$amount;
				WTE_Booking::update_booking( $booking->ID, array( 'meta_input' => $booking_meta ) );
				WTE_Booking::send_emails( $payment_id, 'order_confirmation', 'all' );
			}
		}
	}

	/**
	 * Load Plugin TextDomain.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'wte-paypalexpress',
			false,
			WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_BASE_PATH . '/languages/'
		);
	}

	/**
	 * Payment Details.
	 *
	 * @since 1.0
	 */
	public function wpte_paypalexpress_add_meta_boxes() {
		$screens = array( 'booking' );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'paypalexpress_id',
				__( 'PayPal-Express Payment Details', 'wte-paypalexpress' ),
				array( $this, 'wte_paypalexpress_metabox_callback' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	// Tab for notice listing and settings.
	public function wte_paypalexpress_metabox_callback() {
			include WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_BASE_PATH . '/admin/includes/backend/paypalexpress.php';
	}

	/**
	 * Add Global Tabs.
	 *
	 * @param  [type] $global_tabs Global Tabs.
	 */
	public function paypalexpress_add_settings( $global_tabs ) {
		if ( isset( $global_tabs['wpte-payment'] ) ) {
			$global_tabs['wpte-payment']['sub_tabs']['wte-paypal-express'] = array(
				'label'        => __( 'PayPal Express Checkout', 'wte-paypalexpress' ),
				'content_path' => plugin_dir_path( WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_FILE_PATH ) . 'admin/includes/backend/global-settings.php',
				'current'      => false,
			);
		}
		return $global_tabs;
	}

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function wp_travel_engine_paypalexpress_meta_box_data( $post_id ) {
		/*
			* We need to verify this came from our screen and with proper authorization,
			* because the save_post action can be triggered at other times.
			*/
		// Sanitize user input.
		if ( isset( $_POST['wp_travel_engine_booking_setting'] ) ) {
			$settings = $_POST['wp_travel_engine_booking_setting'];
			update_post_meta( $post_id, 'wp_travel_engine_booking_setting', $settings );
		}
	}

	// Enqueue required assets for admin.
	function Wte_enqueue_backend_assets() {
		wp_enqueue_script( 'Wte-PayPal-Express', plugin_dir_url( WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_FILE_PATH ) . 'assets/admin.js', array( 'jquery' ), WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_VERSION, true );
		wp_enqueue_style( 'Wte-PayPal-Express', plugin_dir_url( WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_FILE_PATH ) . 'assets/admin.css', array(), WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_VERSION, 'all' );
	}

	// Enqueue required assets for admin.
	function Wte_enqueue_frontend_assets() {

		global $wte_cart;

		$wte_options = get_option( 'wp_travel_engine_settings', true );

		wp_enqueue_style( 'Wte-PayPal-Express1', plugin_dir_url( WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_FILE_PATH ) . 'assets/public.css', array(), WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_VERSION, 'all' );
		wp_register_script( 'Wte-PayPal-Express', plugin_dir_url( WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_FILE_PATH ) . 'assets/public.js', array( 'jquery' ), WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_VERSION, true );

		if ( self::is_enabled() ) :
			$translation_array = array(
				'env'       => defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ? 'sandbox' : 'production',
				'partial'   => ! empty( $wte_cart ) && wp_travel_engine_is_cart_partially_payable() ? true : false,
				'client_id' => esc_attr( $wte_options['paypalexpress_client_id'] ),
				'error'     => __( 'Your request could not be processed at the moment. Thank you.', 'wte-paypalexpress' ),
			);
			wp_localize_script( 'Wte-PayPal-Express', 'paypalexpress', $translation_array );

			wp_enqueue_script( 'parsley-min-js', plugin_dir_url( WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_FILE_PATH ) . 'assets/parsley.min.js', array( 'jquery' ), '2.9.2', true );

		endif;

		if ( ( defined( 'WTE_USE_OLD_BOOKING_PROCESS' ) && WTE_USE_OLD_BOOKING_PROCESS ) || version_compare( '3.0.0', WP_TRAVEL_ENGINE_VERSION, '>' ) ) :
			wp_enqueue_script( 'Wte-PayPal-Express-Checkout', plugin_dir_url( WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_FILE_PATH ) . 'assets/checkout.js', array( 'jquery' ), WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_VERSION, true );
		else :

			if ( isset( $wte_options['paypalexpress_client_id'] ) && ! empty( $wte_options['paypalexpress_client_id'] ) ) {
				if ( isset( $wte_options['paypalexpress_payment_method'] ) && ! empty( $wte_options['paypalexpress_payment_method'] ) ) {
					$disable_funding_list = join( ',', $wte_options['paypalexpress_payment_method'] );
					$disable_funding      = '"&disable-funding=' . $disable_funding_list . '"';
				} elseif ( ! isset( $wte_options['paypalexpress_payment_method'] ) ) {
					$disable_funding = '"&disable-funding=card"';
				} else {
					$disable_funding = '""';
				}
				$currency_code = wp_travel_engine_get_currency_code( true );
				if ( wp_travel_engine_paypal_supported_currencies( $currency_code ) == true ) {
					wp_enqueue_script( 'Wte-PayPal-Express-Checkout', 'https://www.paypal.com/sdk/js?currency="' . $currency_code . '"' . $disable_funding . '&client-id=' . esc_attr( $wte_options['paypalexpress_client_id'] ), array( 'jquery' ), null, true );
				}
			}

		endif;

		wp_enqueue_script( 'Wte-PayPal-Express' );

	}

	function wte_paypalexpress_settings() {
		$wp_travel_engine_settings = get_option( 'wp_travel_engine_settings', true );
		?>
		<div class="wte-paypalexpress-form settings">
			<h4><?php _e( 'PayPal-Express Settings', 'wte-paypalexpress' ); ?></h4>
			<label for="wp_travel_engine_settings[paypalexpress_client_id]"><?php _e( 'Client ID : ', 'wte-paypalexpress' ); ?></label>
			<input type="text" id="wp_travel_engine_settings[paypalexpress_client_id]" name="wp_travel_engine_settings[paypalexpress_client_id]" value="<?php echo isset( $wp_travel_engine_settings['paypalexpress_client_id'] ) ? esc_attr( $wp_travel_engine_settings['paypalexpress_client_id'] ) : ''; ?>">
			<div class="settings-note"><?php _e( 'Enter a valid Client ID from PayPal-Express account. All payments will go to this account.', 'wte-paypalexpress' ); ?></div>
			<label for="wp_travel_engine_settings[paypalexpress_secret]"><?php _e( 'PayPal Secret : ', 'wte-paypalexpress' ); ?></label>
			<input type="text" id="wp_travel_engine_settings[paypalexpress_secret]" name="wp_travel_engine_settings[paypalexpress_secret]" value="<?php echo isset( $wp_travel_engine_settings['paypalexpress_secret'] ) ? esc_attr( $wp_travel_engine_settings['paypalexpress_secret'] ) : ''; ?>">
			<div class="settings-note"><?php _e( 'Enter a valid Secret Key from PayPal-Express account.', 'wte-paypalexpress' ); ?></div>
		</div>
		<?php
	}

	// Admin enable settings.
	function wte_paypalexpress_enable() {
		$wp_travel_engine_settings = get_option( 'wp_travel_engine_settings', true );
		?>
		<div class="wte-paypalexpress-form wp-travel-engine-settings">
			<label for="wp_travel_engine_settings[paypalexpress_enable]"><?php _e( 'PayPal-Express: ', 'wte-paypalexpress' ); ?></label>
			<input type="checkbox" id="wp_travel_engine_settings[paypalexpress_enable]" class="paypalexpress" name="wp_travel_engine_settings[paypalexpress_enable]" value="1"
			<?php
			if ( isset( $wp_travel_engine_settings['paypalexpress_enable'] ) && $wp_travel_engine_settings['paypalexpress_enable'] != '' ) {
				echo 'checked';}
			?>
			>
			<label for="wp_travel_engine_settings[paypalexpress_enable]" class="checkbox-label"></label>
			<div class="settings-note"><?php _e( 'Please check this to enable PayPal-Express booking system for trip booking and fill the account info below.', 'wte-paypalexpress' ); ?></div>
		</div>
		<?php
	}

	/**
	 * PayPal Express Form.
	 *
	 * @return void
	 */
	public function paypalexpress_form() {
			ob_start();
			require WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_BASE_PATH . '/paypalExpress.php';
			$options                            = get_option( 'wp_travel_engine_settings', true );
			$wp_travel_engine_confirmation_page = isset( $options['pages']['wp_travel_engine_confirmation_page'] ) ? esc_attr( $options['pages']['wp_travel_engine_confirmation_page'] ) : '';
			$wp_travel_engine_confirmation_page = get_permalink( $options['pages']['wp_travel_engine_confirmation_page'] );
		?>
			<div class="paypal-express"><?php esc_html_e( 'Or, Confirm your booking with PayPal Express', 'wte-paypalexpress' ); ?></div>
				<div id="paypal-button-container"></div>
				<script>
					paypal.Button.render({
						env: '<?php echo defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ? 'sandbox' : 'production'; ?>',
						// PayPal Client IDs - replace with your own
						// Create a PayPal app: https://developer.paypal.com/developer/applications/create
						client: {
							production:  <?php echo "'" . esc_attr( $options['paypalexpress_client_id'] ) . "'"; ?>,
							sandbox: <?php echo "'" . esc_attr( $options['paypalexpress_client_id'] ) . "'"; ?>,
						},

						// Show the buyer a 'Pay Now' button in the checkout flow
						commit: true,

						// payment() is called when the button is clicked
						payment: function(data, actions) {
							// Make a call to the REST api to create the payment
							return actions.payment.create({
								payment: {
									transactions: [
										{
											amount: {
												total: <?php echo "'" . $_SESSION['trip-cost'] . "'"; ?>,
												currency:  <?php echo "'" . esc_attr( $options['currency_code'] ) . "'"; ?>
											},
										}
									]
								}
							});
						},
						validate: function(actions) {

						},
						// onAuthorize() is called when the buyer approves the payment
						onAuthorize: function(data, actions) {
							// Make a call to the REST api to execute the payment
							return actions.payment.execute().then(function() {
									window.location = '<?php echo $wp_travel_engine_confirmation_page; ?>?paymentid='+data.paymentID+"&payerID="+data.payerID+"&token="+data.paymentToken+"&pid="+<?php echo esc_attr( $_SESSION['travelers'] ); ?>;
							});
						},
						onCancel: function (data) {
							console.log('checkout.js payment cancelled', JSON.stringify(data, 0, 2));
						},
						onError: function (err) {
							alert(paypalexpress.error);
						}
					}, '#paypal-button-container');
				</script>
			<?php
			$data = ob_get_clean();
			echo $data;
	}

	/**
	 * Check if paypal express checkout is enabled.
	 *
	 * @return boolean
	 */
	public static function is_enabled() {

		$wte_options = get_option( 'wp_travel_engine_settings', true );

		return isset( $wte_options['paypalexpress_enable'] ) && $wte_options['paypalexpress_enable'] != '';

	}

	/**
	 * Check if checkout process uses paypal express checkout.
	 *
	 * @return void
	 */
	public static function checkout_uses_paypalexpress() {

		return 'POST' === $_SERVER['REQUEST_METHOD'] && array_key_exists( 'wpte_checkout_paymnet_method', $_REQUEST ) && 'paypalexpress_enable' === $_REQUEST['wpte_checkout_paymnet_method'];

	}

	/**
	 * Handle paypal express checkout paymnet request.
	 *
	 * @param [type] $booking_id
	 * @return void
	 */
	public static function paypalexpress_handle_payment_process( $booking_id ) {

		if ( ! self::is_enabled() ) {
			return;
		}

		if ( ! self::checkout_uses_paypalexpress() ) {
			return;
		}

		if ( ! $booking_id ) {
			return;
		}

		if ( isset( $_REQUEST[ 'wte_paypal_express_payment_details' ] ) && ! empty( $_REQUEST[ 'wte_paypal_express_payment_details' ] ) ) :

			do_action( 'wp_travel_engine_before_payment_process', $booking_id );

			$seriliazed_paydata = sanitize_text_field( wp_unslash( $_REQUEST['wte_paypal_express_payment_details'] ) );
			$payment_details    = json_decode( $seriliazed_paydata );

			$paymentID = $payment_details->id;
			$payerID   = $payment_details->payer->payer_id;
			$token     = $_REQUEST['wte_paypal_express_payment_token'];

			$booking_metas = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting', true );

			// payment completed.
			// Update booking status and Payment args.
			$booking_metas[ 'place_order' ][ 'payment' ][ 'paymentid' ] = $paymentID;
			$booking_metas[ 'place_order' ][ 'payment' ][ 'payerid' ]   = $payerID;
			$booking_metas[ 'place_order' ][ 'payment' ][ 'token' ]     = $token;

			update_post_meta( $booking_id, 'wp_travel_engine_booking_setting', $booking_metas );

			// TODO: For future implementation
			$payment_method = 'paypal_express_checkout';

			update_post_meta( $booking_id, sprintf( '_%s_details', $payment_method ), $_REQUEST['wte_paypal_express_payment_details'] );

		endif;

	}

	/**
	 * Add Paypal Express Checkout.
	 *
	 * @param array $payment_methods Payment methods.
	 * @return array
	 */
		public function add_paypal_express_checkout( $payment_methods ) {
			$payment_methods['paypal_express'] = new WTE_Paypal_Express();
			return $payment_methods;
		}

	/**
	 * Add paypal express checkout to paymnet gateways list.
	 *
	 * @param [type] $gateway_list
	 * @return void
	 */
	public function paypal_express_gateway_list( $gateway_list ) {
		$gateway_list['paypalexpress_enable'] = array(
			'label'       => __( 'PayPal-Express Checkout', 'wte-paypalexpress' ),
			'input_class' => 'paypalexpress',
			'info_text'   => __( 'Please check this to enable PayPal-Express booking system for trip booking and fill the account info below.', 'wte-paypalexpress' ),
		);
		return $gateway_list;
	}

}
new Wte_PayPalExpress_Admin();
