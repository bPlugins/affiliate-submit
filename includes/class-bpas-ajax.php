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
		// add_action( 'wp_ajax_bpas_trigger_webhook', [ $this, 'trigger_webhook_test' ] );
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

		if ( ! is_wp_error( $result ) ) {
			$this->trigger_webhook( $name, $email );
		}

		wp_send_json_success( 'Affiliate added successfully!' );
	}

	/**
	 * Trigger custom webhook
	 *
	 * @param string $name  Full name.
	 * @param string $email Email address.
	 */
	private function trigger_webhook( $name, $email ) {
		$webhook_url = 'https://integrations-api.swipeone.com/webhooks/apps/generic-webhooks/694b6a636397f9ae8a38c2b7';
		
		// Extract first name
		$name_parts = explode( ' ', trim( $name ) );
		$first_name = $name_parts[0];

		$body = [
			'first_name' => $first_name,
			'email'      => $email,
			'tags'       => 'affiliate',
		];

		$response = wp_remote_post( $webhook_url, [
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => [
				'Content-Type' => 'application/json',
			],
			'body'        => wp_json_encode( $body ),
			'cookies'     => [],
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'BPAS Webhook Error: ' . $response->get_error_message() );
		} else {
			error_log( 'BPAS Webhook Success: ' . wp_remote_retrieve_body( $response ) );
		}
	}

	/**
	 * Test webhook trigger (for debugging)
	 */
	public function trigger_webhook_test() {
		check_ajax_referer( 'bpas_ajax_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : 'Test User';
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : 'test@example.com';

		$this->trigger_webhook( $name, $email );
		wp_send_json_success( 'Webhook triggered!' );
	}
}
