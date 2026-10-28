<?php
/**
 * Install controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Install controller class
 */
final class InstallController {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		global $wpdb;

		$wpdb->orbis_monitor_responses = $wpdb->prefix . 'orbis_monitor_responses';

		\add_action( 'init', $this->maybe_install( ... ), 20 );
	}

	/**
	 * Activate.
	 *
	 * Removes the database version so the tables are installed and the
	 * rewrite rules are flushed on the next `init`.
	 *
	 * @return void
	 */
	public static function activate(): void {
		\delete_option( 'orbis_monitoring_db_version' );
	}

	/**
	 * Maybe install.
	 *
	 * Runs after the post types are registered so the rewrite rules include them.
	 *
	 * @return void
	 */
	private function maybe_install(): void {
		if ( '1.1.0' === \get_option( 'orbis_monitoring_db_version' ) ) {
			return;
		}

		$this->install();

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Only once after activation or an update.
		\flush_rewrite_rules();

		\update_option( 'orbis_monitoring_db_version', '1.1.0' );
	}

	/**
	 * Install.
	 *
	 * @link https://codex.wordpress.org/Creating_Tables_with_Plugins
	 * @return void
	 */
	private function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = <<<SQL
			CREATE TABLE $wpdb->orbis_monitor_responses (
				response_id BIGINT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id BIGINT(16) UNSIGNED NOT NULL,
				monitored_date DATETIME NOT NULL,
				duration FLOAT DEFAULT NULL,
				response_code VARCHAR(3) DEFAULT NULL,
				response_message VARCHAR(40) DEFAULT NULL,
				response_body LONGTEXT DEFAULT NULL,
				response_date DATETIME NOT NULL,
				response_content_length BIGINT(20) DEFAULT NULL,
				response_content_type VARCHAR(40) DEFAULT NULL,
				PRIMARY KEY  (response_id),
				KEY post_id (post_id)
			) $charset_collate;
			SQL;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		\dbDelta( $sql );

		// Monitors are checked through Action Scheduler, remove the legacy WP-Cron event.
		\wp_clear_scheduled_hook( 'orbis_monitor' );
	}
}
