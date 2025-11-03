<?php
/**
 * Pro config helper functions.
 *
 * @since 1.0.0
 */

use CodewingSolutions\WPTravelEnginePro\Controllers\PluginsAPI;
use CodewingSolutions\WPTravelEnginePro\ExtensionsListTable;
use WPTravelEnginePro\ExtensionLoader;

if ( ! function_exists( 'wptravelengine_pro_config' ) ) {
	/**
	 * @param string $plugin_file Full path of the main plugin file.
	 * @param array $args An associative array with the following keys:
	 * - id (int): The product ID.
	 * - plugin_name (string): The plugin name.
	 * - dependencies (array): The plugin dependencies.
	 *    - requires (array): The required dependencies.
	 *    - includes (array): The recommended dependencies.
	 * - execute (string): The plugin execution function.
	 * - callbacks (array): The plugin callbacks, to be run in sequence after plugin load.
	 * - hooks (array): The plugin hooks to be hooked.
	 * - load_condition_callback (callable): The callback to check if the plugin should be loaded.
	 *
	 * @return true|WP_Error|ExtensionLoader
	 */
	function wptravelengine_pro_config( string $plugin_file, array $args ) {
		if ( ! did_action( 'plugins_loaded' ) ) {
			return add_action( 'plugins_loaded', function () use ( $plugin_file, $args ) {
				wptravelengine_pro_config( $plugin_file, $args );
			}, 9 );
		}

		if ( ! defined( 'WP_TRAVEL_ENGINE_VERSION' ) ) {
			return new WP_Error( 'wptravelengine_pro_not_active', 'WP Travel Engine is not active.' );
		}

		if ( $loader = apply_filters( 'wptravelengine_pro_extension_loader', false ) ) {
			$args[ 'file_path' ] = $plugin_file;

			return call_user_func( $loader, $args );
		}

		PluginsAPI::instance();

		if ( ! has_action( 'current_screen', 'wptravelengine_pro_config_installer_page' ) ) {
			add_action( 'current_screen', 'wptravelengine_pro_config_installer_page' );
		}

		add_action(
			'admin_notices', function () use ( $args ) {
			wptravelengine_pro_config_show_admin_notice( $args );
		} );

		return new \WP_Error( 'wptravelengine_pro_not_active', 'WP Travel Engine Pro is not active.' );
	}
}

if ( ! function_exists( 'wptravelengine_pro_config_show_admin_notice' ) ) {
	/**
	 * Show admin notice.
	 *
	 * @return void
	 */
	function wptravelengine_pro_config_show_admin_notice( $args ) {
		$install_url = add_query_arg( array( 'tab' => 'wptravelengine' ), admin_url( 'plugin-install.php' ) );
		?>
        <div class="wptravelengine-pro notice notice-error">
            <p><?php printf(
					'%1$s requires %2$s plugin to work. Please %3$s and activate the %2$s plugin first.',
					'<strong>' . $args[ 'plugin_name' ] . '</strong>',
					'<strong>WP Travel Engine PRO</strong>',
					sprintf( '<a href="' . esc_url( $install_url ) . '">%s</a>', __( 'install', 'wte-paypalexpress' ) ),
				);
				?></p>
        </div>
		<?php
	}
}

if ( ! function_exists( 'wptravelengine_pro_config_installer_page' ) ) {
	/**
	 * Redirect to the installer page.
	 *
	 * @param WP_Screen $screen
	 *
	 * @return void
	 */
	function wptravelengine_pro_config_installer_page( WP_Screen $screen ) {
		if ( get_option( 'wptravelengine_pro_installer_page_shown', 'no' ) === 'yes' ) {
			return;
		}

		if ( 'plugin-install' !== $screen->id || 'wptravelengine' !== ( $_GET[ 'tab' ] ?? false ) ) {
			wp_redirect(
				add_query_arg(
					array( 'tab' => 'wptravelengine' ),
					admin_url( 'plugin-install.php' )
				)
			);
			exit;
		}
	}
}

if ( ! function_exists( 'wptravelengine_pro_config_view' ) ) {
	/**
	 * @param string $view
	 * @param array $args
	 *
	 * @return void
	 */
	function wptravelengine_pro_config_view( string $view, array $args = array() ) {
		$view_path = dirname( __DIR__ ) . "/views/{$view}.php";

		if ( file_exists( $view_path ) ) {
			extract( $args );

			include $view_path;
		}
	}
}

if ( ! function_exists( 'wptravelengine_pro_config_get_list_table' ) ) {
	function wptravelengine_pro_config_get_list_table( $args = array() ): ExtensionsListTable {
		return new ExtensionsListTable( $args );
	}
}

if ( ! function_exists( 'wptravelengine_pro_config_get_products_from_store' ) ) {
	function wptravelengine_pro_config_get_products_from_store( $type = 'addons', $args = array(), $store_url = '', $refresh = false ) {

		if ( empty( $store_url ) ) {
			$store_url = WP_TRAVEL_ENGINE_STORE_URL;
		}

		$links_by_type = (object) array(
			'addons'   => 'add-ons',
			'themes'   => 'travel-wordpress-themes',
			'services' => 'services',
		);

		$args = wp_parse_args(
			$args,
			array(
				'category' => $links_by_type->{$type} ?? $type,
				'number'   => '10',
				'orderby'  => 'menu_order',
				'order'    => 'asc',
			)
		);

		$cache_key = 'wptravelengine_store_' . md5( $type . serialize( $args ) . $store_url );

		$products = $refresh ? false : get_transient( $cache_key );

		if ( ! $products ) {
			$response = wp_safe_remote_get( add_query_arg( $args, $store_url . "/edd-api/v2/products/" ) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$raw_data = wp_remote_retrieve_body( $response );

			if ( ! json_decode( $raw_data ) ) {
				return new WP_Error( 'edd_api_error', __( 'Invalid API response', 'wte-paypalexpress' ) );
			}

			$products = $raw_data;
			set_transient( $cache_key, $raw_data, 48 * HOUR_IN_SECONDS );
		}

		return json_decode( $products );
	}
}

if ( ! function_exists( 'wptravelengine_assets_dir' ) ) {
	function wptravelengine_assets_dir(): string {
		return dirname( __DIR__ ) . '/assets/';
	}
}

if ( ! function_exists( 'wptravelengine_pro_config_get_saved_license_key' ) ) {
	function wptravelengine_pro_config_get_saved_license_key( string $search_key ) {
		$license_keys = get_option( 'wp_travel_engine_license', array() );
		$key_mappings = array(
			'trip-fixed-starting-dates' => 'wte_fixed_starting_dates_license_key',
		);

		return $license_keys[ $search_key ] ?? $license_keys[ $key_mappings[ $search_key ] ?? 'invalid' ] ?? '';
	}
}

if ( ! function_exists( 'wptravelengine_pro_config_get_product_categories' ) ) {
	function wptravelengine_pro_config_get_product_categories( $taxonomy, array $args = array(), $store_url = '', $refresh = false ): array {

		if ( empty( $store_url ) ) {
			$taxonomy  = trim( $taxonomy, '/' );
			$store_url = trailingslashit( WP_TRAVEL_ENGINE_STORE_URL ) . "wp-json/wp/v2/{$taxonomy}";
		}

		$cache_key = 'wptravelengine_store_' . md5( $taxonomy . serialize( $args ) . $store_url );

		$categories = $refresh ? false : get_transient( $cache_key );

		if ( ! $categories ) {
			$response = wp_safe_remote_get( add_query_arg( $args, $store_url ) );

			if ( is_wp_error( $response ) ) {
				return array();
			}

			$raw_data = wp_remote_retrieve_body( $response );

			if ( ! json_decode( $raw_data ) ) {
				return array();
			}
			$categories = $raw_data;
			set_transient( $cache_key, $raw_data, 48 * HOUR_IN_SECONDS );
		}

		return json_decode( $categories );
	}
}
