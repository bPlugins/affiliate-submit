<?php
/**
 * Admin Class
 *
 * @package BPAS
 * @since 2.0.0
 */

namespace BPAS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/**
	 * Plugin version for cache busting
	 */
	const VERSION = '2.0.0';

	/**
	 * Initialize admin functionality
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add admin menu
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Affiliate Submit', 'affiliate-submit' ),
			__( 'Affiliate Submit', 'affiliate-submit' ),
			'manage_options',
			'bpas-affiliate-submit',
			[ $this, 'render_page' ],
			'dashicons-groups',
			30
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'bpas-affiliate-submit' ) ) {
			return;
		}

		// Enqueue compiled CSS
		wp_enqueue_style(
			'bpas-dashboard',
			plugins_url( 'build/css/dashboard.css', BPAS_PLUGIN_FILE ),
			[],
			self::VERSION
		);

		// Enqueue compiled JS
		wp_enqueue_script(
			'bpas-dashboard',
			plugins_url( 'build/js/dashboard.js', BPAS_PLUGIN_FILE ),
			[ 'jquery' ],
			self::VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'bpas-dashboard',
			'bpasData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bpas_ajax_nonce' ),
			]
		);
	}

	/**
	 * Render admin page
	 */
	public function render_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'submit';
		
		include BPAS_PLUGIN_DIR . 'templates/admin-page.php';
	}
}
