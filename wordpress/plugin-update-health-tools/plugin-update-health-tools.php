<?php
/**
 * Plugin Name:     Plugin Update Checker / Health Tools
 * Description:     An administrator dashboard for plugin updates, rollback, conflict isolation, and diagnostics using trusted WordPress tools.
 * Author:          Contributors
 * Text Domain:     plugin-update-health-tools
 * Version:         1.0.0
 * Requires at least: 6.5
 * Requires PHP:    7.4
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package         Plugin_Update_Health_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-plugin-update-health-tools.php';

add_action( 'admin_menu', array( 'PUHT_Dashboard', 'register_site_menu' ) );
add_action( 'network_admin_menu', array( 'PUHT_Dashboard', 'register_network_menu' ) );
add_action( 'admin_post_puht_check_updates', array( 'PUHT_Dashboard', 'check_updates' ) );
