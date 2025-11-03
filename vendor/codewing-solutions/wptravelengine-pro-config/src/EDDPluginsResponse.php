<?php
/**
 * Plugins API Response.
 *
 * @package WPTravelEnginePro
 * @since 1.0.0
 */

namespace CodewingSolutions\WPTravelEnginePro;

use WP_Error;

/**
 * This class acts as a response from the WordPress.org plugins API for PRO extensions.
 */
class EDDPluginsResponse extends Abstracts\PluginsAPIResponse {
	protected array $api_data = array();

	/**
	 * @var int
	 */
	protected int $product_id = 0;

	/**
	 * @var mixed
	 */
	protected string $license_key = '';

	/**
	 * Constructor.
	 */
	public function __construct ( $product_id, $store_url, array $api_data ) {

		$this->product_id = $product_id;

		$this->set_store_url( $store_url );
		$this->set_api_data( $api_data );
	}

	public function get_cache_key (): string {
		if ( $this->cache_key ) {
			return $this->cache_key;
		}

		$this->cache_key = $this->generate_cache_key( 'edd_sl_', $this->slug, $this->license_key, $this->beta );

		return $this->cache_key;
	}

	public function fetch_product ( int $product_id = null ) {
		$product_id = $product_id ?? $this->product_id;

		$response = wp_remote_get( $this->store_url . 'edd-api/v2/products/?product=' . $product_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( !$response || !isset( $response->products ) || !is_array( $response->products ) ) {
			return new WP_Error( 'edd_api_error', __( 'Invalid API response', 'wte-paypalexpress' ) );
		}

		return (object)$response->products[ 0 ];
	}

	public function set_api_data ( $data ) {
		$this->name = $data[ 'name' ] ?? $this->name;
		$this->slug = $data[ 'slug' ] ?? $this->slug;
		$this->version = $data[ 'version' ] ?? $this->version;
		$this->license_key = $data[ 'license' ] ?? $this->license_key;
		$this->beta = $data[ 'beta' ] ?? $this->beta;
		$this->author = $data[ 'author' ] ?? $this->author;
		$this->cache_key = $this->generate_cache_key( 'edd_sl_', $this->slug, $this->license_key, $this->beta );

		$this->api_data[ 'item_id' ] = $data[ 'item_id' ] ?? $this->product_id;
		$this->api_data = $data;
	}

	public function set_version ( $version ) {
		$this->version = $version;
	}

	public function set_license_key ( $license_key ) {
		$this->license_key = $license_key;
		$this->cache_key = $this->generate_cache_key( 'edd_sl_', $this->slug, $this->license_key, $this->beta );
	}

	public function beta ( $beta = true ) {
		$this->beta = $beta;
		$this->cache_key = $this->generate_cache_key( 'edd_sl_', $this->slug, $this->license_key, $this->beta );
	}

	public function set_version_info_cache ( object $value, string $cache_key = '' ) {
		parent::set_version_info_cache( $value, $this->cache_key );
		delete_option( $this->generate_cache_key( 'edd_api_request_', $this->slug, $this->license_key, $this->beta ) );
	}

	public function get_plugin_information ( $action = 'get_version' ) {
		$response = $this->api_request(
			$action, array(
			'slug'   => $this->slug,
			'is_ssl' => is_ssl(),
			'fields' => array(
				'banners' => array(),
				'reviews' => false,
				'icons'   => array(),
			),
		)
		);

		return !$response || is_wp_error( $response ) ? false : (object)$response;
	}

	protected function api_request ( string $_action, array $_data = array() ) {

		global $edd_plugin_url_available;

		$verify_ssl = $this->verify_ssl();

		$store_hash = md5( $this->store_url );
		if ( !is_array( $edd_plugin_url_available ) || !isset( $edd_plugin_url_available[ $store_hash ] ) ) {
			$test_url_parts = parse_url( $this->store_url );

			$scheme = !empty( $test_url_parts[ 'scheme' ] ) ? $test_url_parts[ 'scheme' ] : 'http';
			$host = !empty( $test_url_parts[ 'host' ] ) ? $test_url_parts[ 'host' ] : '';
			$port = !empty( $test_url_parts[ 'port' ] ) ? ':' . $test_url_parts[ 'port' ] : '';

			if ( empty( $host ) ) {
				$edd_plugin_url_available[ $store_hash ] = false;
			} else {
				$test_url = $scheme . '://' . $host . $port;
				$response = wp_remote_get(
					$test_url,
					array(
						'timeout'   => $this->health_check_timeout,
						'sslverify' => $verify_ssl,
					)
				);
				$edd_plugin_url_available[ $store_hash ] = !is_wp_error( $response );
			}
		}

		if ( false === $edd_plugin_url_available[ $store_hash ] ) {
			return false;
		}

		$data = array_merge( $this->api_data, $_data );

		if ( $data[ 'slug' ] != $this->slug ) {
			return false;
		}

		if ( $this->store_url == trailingslashit( home_url() ) ) {
			return false;
		}

		$api_params = array(
			'edd_action' => $_action,
			'license'    => $this->license_key,
			'item_id'    => $this->product_id,
			'version'    => $this->version,
			'slug'       => $this->slug,
			'author'     => $this->author,
			'url'        => home_url(),
			'beta'       => $this->beta,
		);

		$response = wp_remote_post(
			$this->store_url,
			array(
				'timeout'   => 15,
				'sslverify' => $verify_ssl,
				'body'      => $api_params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $response ) {
			$this->prepare_response( $response );
		}

		return $response;
	}

	protected function verify_ssl (): bool {
		return (bool)apply_filters( 'edd_sl_api_request_verify_ssl', true, $this );
	}

	protected function prepare_response ( $response ) {

		if ( $response && isset( $response->sections ) ) {
			$response->sections = maybe_unserialize( $response->sections );
		} else {
			$response = false;
		}

		if ( $response && isset( $response->banners ) ) {
			$response->banners = maybe_unserialize( $response->banners );
		}

		if ( $response && isset( $response->icons ) ) {
			$response->icons = maybe_unserialize( $response->icons );
		}

		if ( !empty( $response->sections ) ) {
			foreach ( $response->sections as $key => $section ) {
				$response->$key = (array)$section;
			}
		}

		return $response;
	}
}
