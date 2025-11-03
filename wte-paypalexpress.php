<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://wptravelengine.com/
 * @since             1.0.1
 * @package           WP_Travel_Engine
 *
 * @wordpress-plugin
 * Plugin Name:       WP Travel Engine - PayPal Express Gateway
 * Plugin URI:        https://wptravelengine.com/
 * Description:       An extension of WP Travel Engine plugin to accept credit cards directly on your website through your paypal account.
 * Version:           2.4.0
 * Author:            WP Travel Engine
 * Author URI:        https://wptravelengine.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wte-paypalexpress
 * Domain Path:       /languages
 * WTE requires at least: 5.0
 * WTE tested up to: 6.3.1
 * WTE: 7093:wte_paypal_express_license_key
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_VERSION = '2.4.0';
const WPTRAVELENGINE_PAYPAL_EXPRESS_FILE__ = __FILE__;

// Include the autoloader.
require_once __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', function () {
	wptravelengine_pro_config( __FILE__, array(
		'id'           => 7093,
		'slug'         => 'wp-travel-engine-paypal-express-gateway',
		'plugin_name'  => 'PayPal Express Gateway',
		'file_path'    => __FILE__,
		'version'      => WP_TRAVEL_ENGINE_PAYPAL_EXPRESS_VERSION,
		'dependencies' => [
			'requires' => [
				'/includes/class-wte-paypal-express-checkout'
			]
		],
		'execute'      => 'WTE_Paypal_Express_Checkout',
	) );
}, 9 );
