<?php
/**
 * Settings Class
 *
 * @package BPAS
 * @since 2.0.0
 */

namespace BPAS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	/**
	 * Initialize settings
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'migrate_old_settings' ] );
	}

	/**
	 * Migrate old settings from previous version
	 */
	public function migrate_old_settings() {
		$new_settings = get_option( 'bpas_settings' );
		
		// Migrate if new settings don't exist OR if dev_id is empty (migration might have been skipped)
		if ( ! $new_settings || empty( $new_settings['dev_id'] ) ) {
			$old_settings = get_option( 'fs_as_settings' );
			
			if ( $old_settings && is_array( $old_settings ) ) {
				$migrated = is_array( $new_settings ) ? $new_settings : [];
				
				if ( ! empty( $old_settings['dev_id'] ) ) {
					$migrated['dev_id'] = $old_settings['dev_id'];
				}
				
				if ( ! empty( $old_settings['public_key'] ) ) {
					$migrated['public_key'] = $old_settings['public_key'];
				}
				
				if ( ! empty( $old_settings['secret_key'] ) ) {
					$migrated['secret_key'] = $old_settings['secret_key'];
				}
				
				if ( ! empty( $migrated ) ) {
					update_option( 'bpas_settings', $migrated );
				}
			}
		}
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'bpas_settings', 'bpas_settings', [
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
		] );
	}

	/**
	 * Sanitize settings
	 */
	public function sanitize_settings( $input ) {
		$sanitized = [];

		if ( isset( $input['dev_id'] ) ) {
			$sanitized['dev_id'] = trim( $input['dev_id'] );
		}

		if ( isset( $input['public_key'] ) ) {
			$sanitized['public_key'] = trim( $input['public_key'] );
		}

		if ( isset( $input['secret_key'] ) ) {
			$sanitized['secret_key'] = trim( $input['secret_key'] );
		}

		return $sanitized;
	}
}
