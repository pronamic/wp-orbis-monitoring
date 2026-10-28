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

		$wpdb->orbis_monitors          = $wpdb->prefix . 'orbis_monitors';
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
		if ( '1.2.0' === \get_option( 'orbis_monitoring_db_version' ) ) {
			return;
		}

		$this->install();

		$this->add_foreign_keys();

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Only once after activation or an update.
		\flush_rewrite_rules();

		\update_option( 'orbis_monitoring_db_version', '1.2.0' );
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
			CREATE TABLE $wpdb->orbis_monitors (
				id BIGINT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id BIGINT(20) UNSIGNED DEFAULT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY post_id (post_id)
			) $charset_collate;
			CREATE TABLE $wpdb->orbis_monitor_responses (
				id BIGINT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
				monitor_id BIGINT(16) UNSIGNED NOT NULL,
				monitored_date DATETIME NOT NULL,
				duration FLOAT DEFAULT NULL,
				response_code VARCHAR(3) DEFAULT NULL,
				response_message VARCHAR(40) DEFAULT NULL,
				response_body LONGTEXT DEFAULT NULL,
				response_date DATETIME DEFAULT NULL,
				response_content_length BIGINT(20) DEFAULT NULL,
				response_content_type VARCHAR(40) DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY monitor_id (monitor_id)
			) $charset_collate;
			SQL;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		\dbDelta( $sql );

		// Monitors are checked through Action Scheduler, remove the legacy WP-Cron event.
		\wp_clear_scheduled_hook( 'orbis_monitor' );
	}

	/**
	 * Add foreign keys.
	 *
	 * `dbDelta` does not support foreign keys, so they are added separately
	 * when they do not exist yet. References that would violate a foreign
	 * key are cleaned up first.
	 *
	 * @return void
	 */
	private function add_foreign_keys(): void {
		global $wpdb;

		$foreign_keys = [
			[
				'table'   => $wpdb->orbis_monitors,
				'name'    => $wpdb->prefix . 'orbis_monitors_post_id',
				'cleanup' => "UPDATE $wpdb->orbis_monitors SET post_id = NULL WHERE post_id IS NOT NULL AND post_id NOT IN ( SELECT ID FROM $wpdb->posts );",
				'sql'     => "ALTER TABLE $wpdb->orbis_monitors ADD CONSTRAINT {$wpdb->prefix}orbis_monitors_post_id FOREIGN KEY ( post_id ) REFERENCES $wpdb->posts ( ID ) ON DELETE SET NULL;",
			],
			[
				'table'   => $wpdb->orbis_monitor_responses,
				'name'    => $wpdb->prefix . 'orbis_monitor_responses_monitor_id',
				'cleanup' => "DELETE FROM $wpdb->orbis_monitor_responses WHERE monitor_id NOT IN ( SELECT id FROM $wpdb->orbis_monitors );",
				'sql'     => "ALTER TABLE $wpdb->orbis_monitor_responses ADD CONSTRAINT {$wpdb->prefix}orbis_monitor_responses_monitor_id FOREIGN KEY ( monitor_id ) REFERENCES $wpdb->orbis_monitors ( id ) ON DELETE CASCADE;",
			],
		];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- `dbDelta` does not support foreign keys, the queries are built from table names only.
		foreach ( $foreign_keys as $foreign_key ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					<<<'SQL'
					SELECT
						CONSTRAINT_NAME
					FROM
						information_schema.TABLE_CONSTRAINTS
					WHERE
						CONSTRAINT_SCHEMA = DATABASE()
							AND
						TABLE_NAME = %s
							AND
						CONSTRAINT_NAME = %s
							AND
						CONSTRAINT_TYPE = 'FOREIGN KEY'
					SQL,
					$foreign_key['table'],
					$foreign_key['name']
				)
			);

			if ( null !== $exists ) {
				continue;
			}

			$wpdb->query( $foreign_key['cleanup'] );

			$wpdb->query( $foreign_key['sql'] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
	}
}
