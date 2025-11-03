<?php
/**
 * Main Plugin File.
 */

if ( ! class_exists( 'WTE_Paypal_Express_Checkout' ) ) :
	/**
	 * Class WTE_Paypal_Express_Checkout.
	 *
	 * @package WTE Trips Embedder.
	 */
	class WTE_Paypal_Express_Checkout {

		/**
		 * Plugin Name.
		 *
		 * @var string
		 */
		public $plugin_name = 'wte-paypalexpress';

		/**
		 * Version.
		 *
		 * @var string
		 */
		public $version = WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_VERSION;

		/**
		 * The single instance of the class.
		 *
		 * @var string $_instance
		 */
		protected static $instance = null;

		/**
		 * Main WTE_Paypal_Express_Checkout Instance.
		 * Ensures only one instance of WTE_Paypal_Express_Checkout is loaded or can be loaded.
		 *
		 * @return WTE_Paypal_Express_Checkout - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Class Constructor.
		 */
		public function __construct() {
			$this->define_constants();
			add_action( 'admin_notices', array( $this, 'check_dependency' ) );
			if ( class_exists( 'WP_Travel_Engine' ) ) {
				$this->init_hooks();
			}
		}

		/**
		 * Plugin constants.
		 */
		private function define_constants() {

			$paypal_url = defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ? 'https://api.sandbox.paypal.com/v1/' : 'https://api.paypal.com/v1/';
			$this->define( 'PayPal_BASE_URL', $paypal_url );
			$this->define( 'WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_FILE_PATH', WPTRAVELENGINE_PAYPAL_EXPRESS_FILE__ );
			$this->define( 'WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_BASE_PATH', dirname( WPTRAVELENGINE_PAYPAL_EXPRESS_FILE__ ) );

		}

		/**
		 * Define constants.
		 *
		 * @param string $name Constant name.
		 * @param string $value Constant value.
		 */
		public function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Include  required function/ class files.
		 */
		private function includes() {

			require WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_BASE_PATH . '/includes/class-wp-travel-engine-paypal-express-gateway.php';

			require WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_BASE_PATH . '/includes/class-wte-paypal-express-request.php';

			require_once WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_BASE_PATH . '/includes/wte-paypal-express.php';

			/**
			 * The class responsible for updating the add-on from EDD.
			 */
			if ( is_admin() ) {

				require WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_BASE_PATH . '/updater/wte-paypalexpress-updater.php';
			}
		}

		/**
		 * Hooks to init in start.
		 */
		public function init_hooks() {

			$this->includes();

		}

		/**
		 * This will uninstall this plugin if parent WP Travel plugin not found.
		 */
		public function check_dependency() {

			if ( ! class_exists( 'Wp_Travel_Engine' ) || ! $this->meets_requirements() ) {
				echo '<div class="error">';
				echo wp_kses_post( '<p><strong>WP Travel Engine Paypal Express Checkout</strong> requires the <a href="https://wptravelengine.com" target="__blank">WP Travel Engine</a> to work. Please install and activate the latest WP Travel Engine plugin first. <b>WP Travel Engine Paypal Express Checkout will now be deactivated now.</b></p>' );
				echo '</div>';

				// Deactivate Plugins.
				deactivate_plugins( plugin_basename( __FILE__ ) );
			}
		}

		/**
		 * Check if all plugin requirements are met.
		 *
		 * @since 1.0.0
		 *
		 * @return bool True if requirements are met, otherwise false.
		 */
		private function meets_requirements() {
			return ( class_exists( 'WP_Travel_Engine' ) && defined( 'WP_TRAVEL_ENGINE_VERSION' ) && version_compare( WP_TRAVEL_ENGINE_VERSION, '4.3.0', '>=' ) );
		}

		/**
		 * Execute Plugin.
		 *
		 * @return void
		 */
		public static function execute() {
			WTE_Paypal_Express_Checkout::instance();
		}
	}

endif;
