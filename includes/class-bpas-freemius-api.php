<?php
/**
 * Freemius API Wrapper Class
 *
 * @package BPAS
 * @since 2.0.0
 */

namespace BPAS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freemius_API {

	private $dev_id;
	private $public_key;
	private $secret_key;

	/**
	 * Constructor
	 */
	public function __construct( $settings ) {
		$this->dev_id      = isset( $settings['dev_id'] ) ? trim( $settings['dev_id'] ) : '';
		$this->public_key  = isset( $settings['public_key'] ) ? trim( $settings['public_key'] ) : '';
		$this->secret_key  = isset( $settings['secret_key'] ) ? trim( $settings['secret_key'] ) : '';
	}

	/**
	 * Fetch plugins
	 */
	public function fetch_plugins() {
		$settings = get_option( 'bpas_settings', [] );
		if ( empty( $settings ) || empty( $settings['dev_id'] ) ) {
			$settings = get_option( 'fs_as_settings', [] );
		}

		$dev_id = trim( $settings['dev_id'] ?? '' );
		$pub    = trim( $settings['public_key'] ?? '' );
		$sec    = trim( $settings['secret_key'] ?? '' );

		if ( ! $dev_id || ! $pub || ! $sec ) {
			return new \WP_Error( 'not_configured', 'API credentials not configured' );
		}

		try {
			$client = new \Freemius_Api( 'developer', (int) $dev_id, $pub, $sec );

			// Debug: Log the path being requested
			if ( defined( 'BPAS_PLUGIN_DIR' ) ) {
				$debug_info = sprintf( "[%s] Requesting /plugins.json with DevID: %s, Pub: %s\n", date('Y-m-d H:i:s'), $dev_id, substr($pub, 0, 8) . '...' );
				file_put_contents( BPAS_PLUGIN_DIR . 'debug.log', $debug_info, FILE_APPEND );
			}

			$response = $client->Api( '/plugins.json' );

			if ( isset( $response->error ) ) {
				return new \WP_Error( $response->error->code ?? 'api_error', $response->error->message ?? 'Unknown API Error' );
			}

			if ( isset( $response->plugins ) || is_array( $response ) ) {
				return isset( $response->plugins ) ? $response->plugins : $response;
			}

			return new \WP_Error( 'api_error', 'Unexpected response format' );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'sdk_error', $e->getMessage() );
		}
	}

	/**
	 * Add affiliate
	 */
	public function add_affiliate( $plugin_id, $terms_id, $email, $name, $domain, $optional = [] ) {
		$settings = get_option( 'bpas_settings', [] );
		if ( empty( $settings ) || empty( $settings['dev_id'] ) ) {
			$settings = get_option( 'fs_as_settings', [] );
		}

		$dev_id = trim( $settings['dev_id'] ?? '' );
		$pub    = trim( $settings['public_key'] ?? '' );
		$sec    = trim( $settings['secret_key'] ?? '' );

		if ( ! $dev_id || ! $pub || ! $sec ) {
			return new \WP_Error( 'not_configured', 'API credentials not configured' );
		}

		try {
			$client = new \Freemius_Api( 'developer', $dev_id, $pub, $sec );

			$payload = [
				'name'                         => $name,
				'email'                        => $email,
				'domain'                       => str_replace( [ 'http://', 'https://' ], '', $domain ) ?: 'example.com',
				'promotional_methods'          => ! empty( $optional['promotional_methods'] ) ? $optional['promotional_methods'] : 'content_marketing',
				'stats_description'            => ! empty( $optional['stats_description'] ) ? $optional['stats_description'] : 'N/A',
				'promotion_method_description' => ! empty( $optional['promotion_method_description'] ) ? $optional['promotion_method_description'] : 'Applied via Bulk Tool',
				'state'                        => 'active'
			];

			if ( ! empty( $optional['paypal_email'] ) ) {
				$payload['paypal_email'] = $optional['paypal_email'];
			}

			if ( ! empty( $optional['additional_domains'] ) ) {
				$domains = array_map( 'trim', explode( ',', $optional['additional_domains'] ) );
				$payload['additional_domains'] = array_filter( $domains );
			}

			$path     = "/plugins/{$plugin_id}/aff/{$terms_id}/affiliates.json";
			$response = $client->Api( $path, 'POST', $payload );

			if ( isset( $response->error ) ) {
				return new \WP_Error( $response->error->code ?? 'api_error', $response->error->message ?? 'Unknown API Error' );
			}

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'sdk_error', $e->getMessage() );
		}
	}

	/**
	 * Check if API is configured
	 */
	private function is_configured() {
		return ! empty( $this->dev_id ) && ! empty( $this->public_key ) && ! empty( $this->secret_key );
	}
}
