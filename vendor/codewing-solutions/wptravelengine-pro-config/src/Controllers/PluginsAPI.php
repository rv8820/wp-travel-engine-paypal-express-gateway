<?php
/**
 * Plugins API controller.
 *
 * @since 1.0.0
 */

namespace CodewingSolutions\WPTravelEnginePro\Controllers;

use CodewingSolutions\WPTravelEnginePro\EDDPluginsResponse;
use stdClass;

class PluginsAPI {

	const PRO_LICENSE_KEY = 'e931a702384ff7a394f3d4d3f066fc37';
	const PRO_PRODUCT_ID = 138060;

	protected static ?PluginsAPI $instance = null;

	protected function __construct() {
		$this->hooks();
	}

	public static function instance(): PluginsAPI {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	public function hooks() {
		add_filter(
			'install_plugins_tabs', function ( $tabs ) {
			$tabs[ 'wptravelengine' ] = 'WP Travel Engine';

			return $tabs;
		}
		);

		add_action(
			'install_plugins_wptravelengine',
			function () {
				update_option( 'wptravelengine_pro_installer_page_shown', 'yes', true );

				wp_enqueue_style( 'page_plugin-install' );
				wptravelengine_pro_config_view( 'install_plugins_wptravelengine' );
			}
		);

		add_filter(
			'install_plugins_table_api_args_wptravelengine',
			function ( $args ) {
				$args[ 'browse' ]   = 'wptravelengine';
				$args[ 'page' ]     = 1;
				$args[ 'per_page' ] = 100;
				$args[ 'locale' ]   = get_user_locale();

				return $args;
			}
		);

		add_filter( 'plugins_api', array( static::class, 'plugins_api' ), 25, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * @param $_transient_data
	 *
	 * @return void
	 */
	public function check_update( $_transient_data ) {
		global $pagenow;

		if ( ! file_exists( WP_PLUGIN_DIR . '/wptravelengine-pro/wptravelengine-pro.php' ) ) {
			return $_transient_data;
		}

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/wptravelengine-pro/wptravelengine-pro.php' );

		$installed_version = $plugin_data[ 'Version' ];

		if ( ! is_object( $_transient_data ) ) {
			$_transient_data = new stdClass();
		}

		if ( 'plugins.php' == $pagenow && is_multisite() ) {
			return $_transient_data;
		}

		if ( ! empty( $_transient_data->response ) && ! empty( $_transient_data->response[ "wptravelengine-pro/wp-travel-engine-pro.php" ] ) ) {
			return $_transient_data;
		}

		$current = new EDDPluginsResponse(
			static::PRO_PRODUCT_ID,
			WP_TRAVEL_ENGINE_STORE_URL,
			array(
				'license' => static::PRO_LICENSE_KEY,
				'slug'    => 'wptravelengine-pro',
				'author'  => 'WP Travel Engine',
			)
		);

		$current = $current->get_plugin_information();

		if ( false !== $current && is_object( $current ) && isset( $current->new_version ) ) {
			if ( version_compare( $installed_version, $current->new_version, '<' ) ) {
				$_transient_data->response[ 'wptravelengine_pro/wptravelengine_pro.php' ] = $current;
			} else {
				// Populating the no_update information is required to support auto-updates in WordPress 5.5.
				$_transient_data->no_update[ 'wptravelengine_pro/wptravelengine_pro.php' ] = $current;
			}
		}
		$_transient_data->last_checked                                           = time();
		$_transient_data->checked[ 'wptravelengine_pro/wptravelengine_pro.php' ] = $installed_version;

		return $_transient_data;
	}

	public static function plugins_api( ...$args ) {

		if ( 'wptravelengine' === ( $args[ 2 ]->browse ?? '' ) ) {
			return static::query_plugins( ...$args );
		}


		if ( 'plugin_information' === $args[ 1 ] && 'wptravelengine-pro' === $args[ 2 ]->slug ) {

			$plugins_api = new EDDPluginsResponse(
				static::PRO_PRODUCT_ID,
				WP_TRAVEL_ENGINE_STORE_URL,
				array(
					'license' => static::PRO_LICENSE_KEY,
					'slug'    => 'wptravelengine-pro',
					'author'  => 'WP Travel Engine',
				)
			);

			$plugins_api->get_plugin_information( 'activate_license' );

			return $plugins_api->plugins_api( ...$args );
		}

		return $args[ 0 ];

	}

	/**
	 * @param mixed ...$args
	 *
	 * @return stdClass
	 */
	public static function query_plugins( ...$args ): stdClass {

		$response = new stdClass();

		$response->plugins = static::get_store_plugins();

		$response->info[ 'results' ] = count( $response->plugins );

		return $response;
	}

	public static function is_store_plugin( $slug ) {
		$plugins = static::get_store_plugins();

		foreach ( $plugins as $plugin ) {
			if ( $plugin->slug === $slug ) {
				return $plugin;
			}
		}

		return false;
	}

	public static function get_store_plugins(): array {
		$response = wp_safe_remote_get( "https://wptravelengine.com/edd-api/v2/products/?product=" . static::PRO_PRODUCT_ID );
		if ( is_wp_error( $response ) ) {
			return [];
		}

		$plugins = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! $plugins ) {
			return [];
		}

		return array_map( [ static::class, 'format_item' ], $plugins->products );
	}

	protected static function format_item( $item ): stdClass {
		$_item = new stdClass();

		$_item->id                = $item->info->id;
		$_item->name              = $item->info->title;
		$_item->slug              = $item->info->slug;
		$_item->version           = $item->licensing->version ?? '';
		$_item->author            = $item->info->author ?? 'WP Travel Engine';
		$_item->requires_php      = $item->info->requires_php ?? '7.4';
		$_item->requires_plugins  = $item->info->requires_plugins ?? [];
		$_item->tested            = $item->info->tested ?? '6.7';
		$_item->rating            = $item->info->rating ?? 0;
		$_item->ratings           = $item->info->ratings ?? [];
		$_item->short_description = $item->info->excerpt ?? '';
		$_item->description       = $item->info->content ?? '';
		$_item->licensing         = $item->info->licensing ?? [];
		$_item->pricing           = $item->info->pricing ?? [];
		$_item->thumbnail         = $item->info->thumbnail ?? '';
		$_item->tags              = $item->info->tags;
		$_item->price             = $item->info->price ?? '';
		$_item->category          = $item->info->category ?? '';
		$_item->image             = $item->info->image ?? '';
		$_item->license_key       = wptravelengine_pro_config_get_saved_license_key( $_item->slug );
		$_item->homepage          = trailingslashit( WP_TRAVEL_ENGINE_STORE_URL ) . trim( $item->info->permalink, '/' ) . "/?utm_source=free_plugin&utm_medium=pro_addon&utm_campaign=upgrade_to_pro";

		$response = new EDDPluginsResponse(
			static::PRO_PRODUCT_ID,
			WP_TRAVEL_ENGINE_STORE_URL, array(
				'slug'    => 'wptravelengine-pro',
				'license' => static::PRO_LICENSE_KEY,
			)
		);

		$plugin_api = $response->get_plugin_information();

		$_item->download_link = $plugin_api->download_link ?? '';

		return $_item;
	}

	public function admin_enqueue_scripts() {
		$config_assets_dir = wptravelengine_assets_dir();

		$asset_file = $config_assets_dir . 'page_plugin-install.css';
		$handle     = basename( $asset_file, '.css' );
		wp_register_style( $handle, plugin_dir_url( $asset_file ) . basename( $asset_file ), array(), filemtime( $asset_file ) );
	}

}
