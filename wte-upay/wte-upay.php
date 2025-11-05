<?php
/**
 * Plugin Name:       WP Travel Engine - UPay Gateway
 * Plugin URI:        https://wptravelengine.com/
 * Description:       Accept payments through Union Bank's UPay payment gateway for WP Travel Engine bookings
 * Version:           1.0.0
 * Author:            WP Travel Engine
 * Author URI:        https://wptravelengine.com/
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

add_action( 'plugins_loaded', function () {
    wptravelengine_pro_config( __FILE__, array(
        'id'           => 99999, // Replace with actual ID from WP Travel Engine
        'slug'         => 'wp-travel-engine-upay-gateway',
        'plugin_name'  => 'UPay Gateway',
        'file_path'    => __FILE__,
        'version'      => WP_TRAVEL_ENGINE_UPAY_VERSION,
        'dependencies' => [
            'requires' => [
                '/includes/class-wte-upay-checkout'
            ]
        ],
        'execute'      => 'WTE_UPay_Checkout',
    ) );
}, 9 );
