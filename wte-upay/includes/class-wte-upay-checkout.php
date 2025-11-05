<?php
/**
 * Main Plugin Class for UPay Integration
 *
 * @package WTE_UPay
 */

if ( ! class_exists( 'WTE_UPay_Checkout' ) ) :
    
    class WTE_UPay_Checkout {

        /**
         * Single instance
         *
         * @var WTE_UPay_Checkout
         */
        protected static $instance = null;

        /**
         * Get instance
         *
         * @return WTE_UPay_Checkout
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor
         */
        public function __construct() {
            $this->define_constants();
            add_action( 'admin_notices', array( $this, 'check_dependency' ) );
            
            if ( class_exists( 'WP_Travel_Engine' ) ) {
                $this->init_hooks();
            }
        }

        /**
         * Define plugin constants
         */
        private function define_constants() {
            // UPay API URLs based on documentation
            $upay_url = defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG 
                ? 'https://apiuat.unionbankph.com/ubp/external/upay/payments/v1' 
                : 'https://api.unionbankph.com/ubp/external/upay/payments/v1';
            
            $this->define( 'UPAY_BASE_URL', $upay_url );
            $this->define( 'WP_TRAVEL_ENGINE_UPAY_FILE_PATH', WPTRAVELENGINE_UPAY_FILE__ );
            $this->define( 'WP_TRAVEL_ENGINE_UPAY_BASE_PATH', dirname( WPTRAVELENGINE_UPAY_FILE__ ) );
        }

        /**
         * Define constant if not already defined
         *
         * @param string $name  Constant name.
         * @param mixed  $value Constant value.
         */
        public function define( $name, $value ) {
            if ( ! defined( $name ) ) {
                define( $name, $value );
            }
        }

        /**
         * Include required files
         */
        private function includes() {
            require WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/includes/class-wp-travel-engine-upay-gateway.php';
            require WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/includes/class-wte-upay-request.php';
            require WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/includes/class-wte-upay-api.php';
            require_once WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/includes/wte-upay.php';

            // Include updater if admin
            if ( is_admin() && file_exists( WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/updater/wte-upay-updater.php' ) ) {
                require WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/updater/wte-upay-updater.php';
            }
        }

        /**
         * Initialize hooks
         */
        public function init_hooks() {
            $this->includes();
        }

        /**
         * Check if WP Travel Engine is installed
         */
        public function check_dependency() {
            if ( ! class_exists( 'Wp_Travel_Engine' ) || 
                 ! defined( 'WP_TRAVEL_ENGINE_VERSION' ) || 
                 version_compare( WP_TRAVEL_ENGINE_VERSION, '5.0.0', '<' ) ) {
                
                echo '<div id="message" class="error">';
                echo '<p>' . sprintf(
                    __( '<strong>WP Travel Engine - UPay Gateway</strong> requires WP Travel Engine plugin version 5.0 or higher. Please install and activate WP Travel Engine first. <a href="%s">Plugin will be deactivated now.</a>', 'wte-upay' ),
                    admin_url( 'plugins.php' )
                ) . '</p>';
                echo '</div>';
                
                deactivate_plugins( WPTRAVELENGINE_UPAY_FILE__ );
            }
        }

        /**
         * Execute plugin
         */
        public static function execute() {
            return self::instance();
        }
    }

endif;
