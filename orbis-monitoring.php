<?php
/**
 * Orbis Monitoring
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   Copyright (c) Pronamic
 * @package   Pronamic\Orbis\Monitoring
 *
 * @wordpress-plugin
 * Plugin Name:       Orbis Monitoring
 * Plugin URI:        https://www.pronamic.eu/plugins/orbis-monitoring/
 * Description:       The Orbis Monitoring plugin extends your Orbis environment with the option to monitor websites.
 * Version:           1.0.0
 * Requires at least: 3.5
 * Author:            Pronamic
 * Author URI:        https://www.pronamic.eu/
 * Text Domain:       orbis_monitoring
 * Domain Path:       /languages/
 * License:           Copyright (c) Pronamic
 * License URI:       https://www.pronamic.eu/copyright/
 * GitHub URI:        https://github.com/pronamic/wp-orbis-monitoring
 */

function orbis_monitoring_bootstrap() {
	// Classes
	require_once 'classes/orbis-monitoring-plugin.php';

	// Initialize
	global $orbis_onitoring_plugin;

	$orbis_onitoring_plugin = new Orbis_Monitoring_Plugin( __FILE__ );
}

add_action( 'orbis_bootstrap', 'orbis_monitoring_bootstrap' );
