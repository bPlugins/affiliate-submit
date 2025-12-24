<?php
/**
 * AJAX Handler Class
 *
 * @package BPAS
 * @since 2.0.0
 */

namespace BPAS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax {

	/**
	 * Initialize AJAX handlers
	 */
	public function init() {
		add_action( 'wp_ajax_bpas_fetch_plugins', [ $this, 'fetch_plugins' ] );
		add_action( 'wp_ajax_bpas_add_affiliate', [ $this, 'add_affiliate' ] );
		add_action( 'wp_ajax_bpas_test_connection', [ $this, 'test_connection' ] );
		add_action( 'wp_ajax_bpas_clear_sync', [ $this, 'clear_sync' ] );
	}

	/**
	 * Clear clock sync cache
	 */
	public function clear_sync() {
		check_ajax_referer( 'bpas_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		delete_transient( 'bpas_freemius_clock_diff' );
		wp_send_json_success( 'Clock sync cache cleared!' );
	}

	/**
	 * Test API connection
	 */
	public function test_connection() {
		check_ajax_referer( 'bpas_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$settings = get_option( 'bpas_settings', [] );

		$api      = new Freemius_API( $settings );
		$plugins  = $api->fetch_plugins();

		if ( is_wp_error( $plugins ) ) {
			$dev_id = $settings['dev_id'] ?? 'N/A';
			$masked_pub = isset($settings['public_key']) ? substr($settings['public_key'], 0, 4) . '...' : 'N/A';
			$masked_sec = isset($settings['secret_key']) ? substr($settings['secret_key'], 0, 4) . '...' : 'N/A';
			wp_send_json_error( sprintf( '%s (Code: %s) [DevID: %s, Pub: %s, Sec: %s]', $plugins->get_error_message(), $plugins->get_error_code(), $dev_id, $masked_pub, $masked_sec ) );
		}

		$count = is_array( $plugins ) ? count( $plugins ) : 0;
		wp_send_json_success( sprintf( 'Connection successful! Found %d products.', $count ) );
	}

	/**
	 * Fetch plugins via AJAX
	 */
	public function fetch_plugins() {
		check_ajax_referer( 'bpas_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$settings = get_option( 'bpas_settings', [] );
		if ( empty( $settings ) || empty( $settings['dev_id'] ) ) {
			$old_settings = get_option( 'fs_as_settings', [] );
			if ( ! empty( $old_settings['dev_id'] ) ) {
				$settings = $old_settings;
			}
		}
		$api      = new Freemius_API( $settings );
		$plugins  = $api->fetch_plugins();

		if ( is_wp_error( $plugins ) ) {
			$dev_id = $settings['dev_id'] ?? 'N/A';
			$masked_pub = isset($settings['public_key']) ? substr($settings['public_key'], 0, 4) . '...' : 'N/A';
			$masked_sec = isset($settings['secret_key']) ? substr($settings['secret_key'], 0, 4) . '...' : 'N/A';
			wp_send_json_error( sprintf( '%s (Code: %s) [DevID: %s, Pub: %s, Sec: %s]', $plugins->get_error_message(), $plugins->get_error_code(), $dev_id, $masked_pub, $masked_sec ) );
		}

		wp_send_json_success( $plugins );
	}

	/**
	 * Add affiliate via AJAX
	 */
	public function add_affiliate() {
		check_ajax_referer( 'bpas_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$plugin_id = isset( $_POST['plugin_id'] ) ? intval( $_POST['plugin_id'] ) : 0;
		$terms_id  = isset( $_POST['terms_id'] ) ? intval( $_POST['terms_id'] ) : 0;
		$email     = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$domain    = isset( $_POST['domain'] ) ? sanitize_text_field( $_POST['domain'] ) : '';
		
		$optional = [
			'paypal_email'                 => isset( $_POST['paypal_email'] ) ? sanitize_email( $_POST['paypal_email'] ) : '',
			'additional_domains'           => isset( $_POST['additional_domains'] ) ? sanitize_text_field( $_POST['additional_domains'] ) : '',
			'promotional_methods'          => isset( $_POST['promotional_methods'] ) ? sanitize_text_field( $_POST['promotional_methods'] ) : '',
			'stats_description'            => isset( $_POST['stats_description'] ) ? sanitize_textarea_field( $_POST['stats_description'] ) : '',
			'promotion_method_description' => isset( $_POST['promotion_method_description'] ) ? sanitize_textarea_field( $_POST['promotion_method_description'] ) : '',
		];

		if ( ! $plugin_id || ! $terms_id || ! $email || ! $name ) {
			wp_send_json_error( 'Missing required fields' );
		}

		$settings = get_option( 'bpas_settings', [] );
		if ( empty( $settings ) || empty( $settings['dev_id'] ) ) {
			$old_settings = get_option( 'fs_as_settings', [] );
			if ( ! empty( $old_settings['dev_id'] ) ) {
				$settings = $old_settings;
			}
		}
		$api      = new Freemius_API( $settings );
		$result   = $api->add_affiliate( $plugin_id, $terms_id, $email, $name, $domain, $optional );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( 'Affiliate added successfully!' );
	}
}
