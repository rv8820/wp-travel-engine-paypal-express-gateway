<?php
/**
 * Plugins API Response.
 *
 * @package WPTravelEnginePro
 * @since 1.0.0
 */

namespace CodewingSolutions\WPTravelEnginePro\Abstracts;

/**
 * This class acts as a response from the WordPress.org plugins API for PRO extensions.
 */
abstract class PluginsAPIResponse {

	/**
	 * @var string
	 */
	protected string $store_url = '';

	/**
	 * @var string
	 */
	public string $slug = '';

	/**
	 * @var string
	 */
	protected string $name = '';

	/**
	 * @var bool
	 */
	public bool $beta = false;

	/**
	 * @var string
	 */
	public string $version = '';

	/**
	 * @var ?string
	 */
	protected ?string $cache_key = null;

	/**
	 * @var string
	 */
	protected string $author = '';

	/**
	 * @var int
	 */
	protected int $health_check_timeout = 5;

	public function set_store_url( $url ) {
		$this->store_url = trailingslashit( $url );
	}

	public function generate_cache_key( $prefix = '', ...$tokens ): string {
		return $prefix . md5( serialize( implode( '', $tokens ) ) );
	}

	public function get_cached_version_info( string $cache_key = '' ) {

		if ( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}

		$cache = get_option( $cache_key );

		if ( empty( $cache[ 'timeout' ] ) || time() > $cache[ 'timeout' ] ) {
			return false;
		}

		$cache[ 'value' ] = json_decode( $cache[ 'value' ] );
		if ( ! empty( $cache[ 'value' ]->icons ) ) {
			$cache[ 'value' ]->icons = (array) $cache[ 'value' ]->icons;
		}

		return $cache[ 'value' ];

	}

	public function set_version_info_cache( object $value, string $cache_key = '' ) {

		if ( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}

		$data = array(
			'timeout' => strtotime( '+3 hours', time() ),
			'value'   => wp_json_encode( $value ),
		);

		update_option( $cache_key, $data, 'no' );
	}

	abstract public function get_plugin_information();

	public function plugins_api( $_data ): object {

		$transient = $this->get_cached_version_info();

		if ( empty( $transient ) ) {
			$api_response = $this->get_plugin_information();

			if ( ! empty( $api_response->slug ) ) {
				$this->set_version_info_cache( $api_response );
			}

			if ( false !== $api_response ) {
				$_data = $api_response;
			}
		} else {
			$_data = $transient;
		}

		$_data->version = $_data->new_version ?? $_data->stable_version;

		return $this->prepare_plugins_api( $_data );
	}

	/**
	 * Prepare the data.
	 *
	 * @param object $data Data.
	 *
	 * @return object
	 */
	protected function prepare_plugins_api( object $data ): object {
		$data->sections     = ( $data->sections ?? false ) ? $this->maybe_object_to_array( $data->sections ) : array();
		$data->banners      = ( $data->banners ?? false ) ? $this->maybe_object_to_array( $data->banners ) : array();
		$data->icons        = ( $data->icons ?? false ) ? $this->maybe_object_to_array( $data->icons ) : array();
		$data->contributors = ( $data->contributors ?? false ) ? $this->maybe_object_to_array( $data->contributors ) : array();
		$data->plugin       = $data->plugin ?? $this->name;

		return $data;
	}

	protected function verify_ssl(): bool {
		return true;
	}

	protected function maybe_object_to_array( $data ) {
		if ( is_object( $data ) ) {
			return $this->object_to_array( $data );
		}

		return $data;
	}

	protected function object_to_array( object $data ): array {
		$new_data = array();
		foreach ( $data as $key => $value ) {
			$new_data[ $key ] = is_object( $value ) ? $this->object_to_array( $value ) : $value;
		}

		return $new_data;
	}

}
