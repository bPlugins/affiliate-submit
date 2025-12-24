<?php
/**
 * Plugin Name: Affiliate Submit
 * Plugin URI: https://example.com
 * Description: Bulk submit affiliate applications to all Freemius products with a modern, beautiful interface.
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: affiliate-submit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package BPAS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'BPAS_VERSION', '2.0.0' );
define( 'BPAS_PLUGIN_FILE', __FILE__ );
define( 'BPAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BPAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Require Freemius SDK
require_once BPAS_PLUGIN_DIR . 'freemius-php-sdk/freemius/Freemius.php';

// Debug: Check where Freemius_Api is loaded from
if ( class_exists( 'Freemius_Api' ) ) {
	$reflector = new ReflectionClass( 'Freemius_Api' );
	$version = defined( 'Freemius_Api_Base::VERSION' ) ? Freemius_Api_Base::VERSION : 'unknown';
	error_log( 'BPAS Debug: Freemius_Api (v' . $version . ') loaded from ' . $reflector->getFileName() );
}

// Autoload classes
spl_autoload_register( function ( $class ) {
	$prefix   = 'BPAS\\';
	$base_dir = BPAS_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = $base_dir . 'class-bpas-' . str_replace( '_', '-', strtolower( $relative_class ) ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Main Plugin Class
 */
final class BPAS_Affiliate_Submit {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		// Initialize classes
		$admin    = new \BPAS\Admin();
		$settings = new \BPAS\Settings();
		$ajax     = new \BPAS\Ajax();

		$admin->init();
		$settings->init();
		$ajax->init();

		// Load text domain
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
	}

	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'affiliate-submit',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}
}

// Initialize plugin
BPAS_Affiliate_Submit::instance();
