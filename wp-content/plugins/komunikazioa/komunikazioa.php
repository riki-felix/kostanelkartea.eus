<?php
/**
 * Plugin Name: Komunikazioa
 * Description: Admin campaigns, leads and scheduled email delivery for Kostan.
 * Version: 0.1.0
 * Author: Entretiempo
 * Text Domain: komunikazioa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOMUNIKAZIOA_VERSION', '0.1.0' );
define( 'KOMUNIKAZIOA_FILE', __FILE__ );
define( 'KOMUNIKAZIOA_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'KOMUNIKAZIOA_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

add_action(
	'plugins_loaded',
	static function() {
		load_plugin_textdomain( 'komunikazioa', false, dirname( plugin_basename( KOMUNIKAZIOA_FILE ) ) . '/languages' );
	}
);

require_once KOMUNIKAZIOA_DIR . '/includes/class-komunikazioa.php';

register_activation_hook( KOMUNIKAZIOA_FILE, array( '\Kostan\\Komunikazioa\\Plugin', 'activate' ) );
register_deactivation_hook( KOMUNIKAZIOA_FILE, array( '\Kostan\\Komunikazioa\\Plugin', 'deactivate' ) );

\Kostan\Komunikazioa\Plugin::init();
