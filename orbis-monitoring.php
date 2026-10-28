<?php
/**
 * Orbis Monitoring
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 *
 * @wordpress-plugin
 * Plugin Name:       Orbis Monitoring
 * Plugin URI:        https://www.pronamic.eu/plugins/orbis-monitoring/
 * Description:       The Orbis Monitoring plugin extends your Orbis environment with the option to monitor websites.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.3
 * Author:            Pronamic
 * Author URI:        https://www.pronamic.eu/
 * Text Domain:       orbis-monitoring
 * Domain Path:       /languages/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub URI:        https://github.com/pronamic/wp-orbis-monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function (): void {
	$autoload_path = __DIR__ . '/vendor/autoload_packages.php';

	if ( \file_exists( $autoload_path ) ) {
		require_once $autoload_path;
	}

	\register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );

	Plugin::instance( __FILE__ );
} )();
