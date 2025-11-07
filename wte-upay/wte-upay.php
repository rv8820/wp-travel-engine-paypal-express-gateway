<?php
/**
 * Plugin Name:       WP Travel Engine - UPay Gateway
 * Plugin URI:        https://wptravelengine.com/
 * Description:       Accept payments through Union Bank's UPay payment gateway for WP Travel Engine bookings
 * Version:           1.0.0
 * Author:            TM Digital Consulting
 * Author URI:        https://tmdigitalconsulting.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wte-upay
 * Domain Path:       /languages
 * WTE requires at least: 5.0
 * WTE tested up to: 6.3.1
 * WTE:               XXXXX:wte_upay_license_key
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const WP_TRAVEL_ENGINE_UPAY_VERSION = '1.0.0';
const WPTRAVELENGINE_UPAY_FILE__ = __FILE__;

// Include the autoloader if using composer.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize plugin
add_action( 'plugins_loaded', function () {
    // Check if WP Travel Engine is active
    if ( ! class_exists( 'WP_Travel_Engine' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>';
            echo __( '<strong>WP Travel Engine - UPay Gateway</strong> requires WP Travel Engine plugin to be installed and activated.', 'wte-upay' );
            echo '</p></div>';
        });
        return;
    }

    // Load debug page
    if ( is_admin() && file_exists( __DIR__ . '/debug-upay.php' ) ) {
        require_once __DIR__ . '/debug-upay.php';
    }

    // Direct initialization
    require_once __DIR__ . '/includes/class-wte-upay-checkout.php';
    WTE_UPay_Checkout::execute();
}, 9 );