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
		$wpdb->orbis_monitor_incidents = $wpdb->prefix . 'orbis_monitor_incidents';

		$wpdb->orbis_monitor_response_stats = $wpdb->prefix . 'orbis_monitor_response_stats';

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
		if ( '1.7.0' === \get_option( 'orbis_monitoring_db_version' ) ) {
			return;
		}

		$this->install();

		$this->migrate_responses_table();

		$this->migrate_response_messages();

		$this->add_foreign_keys();

		$this->migrate_statuses();

		$this->migrate_check_succeeded();

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Only once after activation or an update.
		\flush_rewrite_rules();

		\update_option( 'orbis_monitoring_db_version', '1.7.0' );
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
				status VARCHAR(10) NOT NULL DEFAULT 'unknown',
				failure_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
				status_changed_at DATETIME DEFAULT NULL,
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
				response_content_length BIGINT(20) DEFAULT NULL,
				response_content_type VARCHAR(40) DEFAULT NULL,
				error_message VARCHAR(255) DEFAULT NULL,
				check_succeeded TINYINT(1) DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY monitor_id_monitored_date (monitor_id,monitored_date)
			) $charset_collate;

			CREATE TABLE $wpdb->orbis_monitor_response_stats (
				monitor_id BIGINT(16) UNSIGNED NOT NULL,
				started_at DATETIME NOT NULL,
				ended_at DATETIME NOT NULL,
				check_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
				duration_total DOUBLE NOT NULL DEFAULT 0,
				duration_min FLOAT DEFAULT NULL,
				duration_max FLOAT DEFAULT NULL,
				PRIMARY KEY  (monitor_id,started_at)
			) $charset_collate;

			CREATE TABLE $wpdb->orbis_monitor_incidents (
				id BIGINT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
				monitor_id BIGINT(16) UNSIGNED NOT NULL,
				started_at DATETIME NOT NULL,
				resolved_at DATETIME DEFAULT NULL,
				last_notified_at DATETIME DEFAULT NULL,
				response_code VARCHAR(3) DEFAULT NULL,
				error_message VARCHAR(255) DEFAULT NULL,
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
	 * Migrate check succeeded.
	 *
	 * Responses from before the `check_succeeded` column existed are marked as
	 * succeeded when the response code matches the required response code. The
	 * required location was not stored, so it is not taken into account.
	 *
	 * @return void
	 */
	private function migrate_check_succeeded(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only the table names are interpolated.
		$wpdb->query(
			<<<SQL
			UPDATE
				$wpdb->orbis_monitor_responses AS response
					INNER JOIN
				$wpdb->orbis_monitors AS monitor
						ON monitor.id = response.monitor_id
					LEFT JOIN
				$wpdb->postmeta AS meta
						ON meta.post_id = monitor.post_id AND meta.meta_key = '_orbis_monitor_required_response_code'
			SET
				response.check_succeeded = ( COALESCE( response.response_code, '' ) = COALESCE( NULLIF( meta.meta_value, '' ), '200' ) )
			WHERE
				response.check_succeeded IS NULL
			;
			SQL
		);
	}

	/**
	 * Migrate statuses.
	 *
	 * The `up` and `down` statuses are renamed to `online` and `offline`.
	 *
	 * @return void
	 */
	private function migrate_statuses(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only the table name is interpolated.
		$wpdb->query(
			<<<SQL
			UPDATE
				$wpdb->orbis_monitors
			SET
				status = CASE status
					WHEN 'up' THEN 'online'
					WHEN 'down' THEN 'offline'
				END
			WHERE
				status IN ( 'up', 'down' )
			;
			SQL
		);
	}

	/**
	 * Migrate responses table.
	 *
	 * `dbDelta` does not drop columns or indexes, so the unused response body
	 * and date columns and the index that is replaced by the combined
	 * `monitor_id_monitored_date` index are dropped separately.
	 *
	 * @return void
	 */
	private function migrate_responses_table(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- `dbDelta` does not drop columns or indexes, only the table name is interpolated.
		foreach ( [ 'response_body', 'response_date' ] as $column ) {
			if ( ! $this->column_exists( $wpdb->orbis_monitor_responses, $column ) ) {
				continue;
			}

			$wpdb->query( "ALTER TABLE $wpdb->orbis_monitor_responses DROP COLUMN $column;" );
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				<<<'SQL'
				SELECT
					INDEX_NAME
				FROM
					information_schema.STATISTICS
				WHERE
					TABLE_SCHEMA = DATABASE()
						AND
					TABLE_NAME = %s
						AND
					INDEX_NAME = %s
				LIMIT
					1
				SQL,
				$wpdb->orbis_monitor_responses,
				'monitor_id'
			)
		);

		if ( null !== $exists ) {
			$wpdb->query( "ALTER TABLE $wpdb->orbis_monitor_responses DROP INDEX monitor_id;" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Migrate response messages.
	 *
	 * The `response_message` columns are replaced by `error_message`. The HTTP
	 * status text follows from the response code, so only the error message
	 * of a failed request is kept. A failed request has no response code.
	 *
	 * @return void
	 */
	private function migrate_response_messages(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- `dbDelta` does not drop columns, only the table name is interpolated.
		if ( $this->column_exists( $wpdb->orbis_monitor_incidents, 'response_message' ) ) {
			$wpdb->query(
				<<<SQL
				UPDATE
					$wpdb->orbis_monitor_incidents
				SET
					error_message = response_message
				WHERE
					( response_code IS NULL OR response_code = '' )
						AND
					error_message IS NULL
				;
				SQL
			);

			$wpdb->query( "ALTER TABLE $wpdb->orbis_monitor_incidents DROP COLUMN response_message;" );
		}

		if ( $this->column_exists( $wpdb->orbis_monitor_responses, 'response_message' ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->orbis_monitor_responses DROP COLUMN response_message;" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		\delete_post_meta_by_key( '_orbis_monitor_response_message' );
	}

	/**
	 * Check if a column exists.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return bool
	 */
	private function column_exists( string $table, string $column ): bool {
		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				<<<'SQL'
				SELECT
					COLUMN_NAME
				FROM
					information_schema.COLUMNS
				WHERE
					TABLE_SCHEMA = DATABASE()
						AND
					TABLE_NAME = %s
						AND
					COLUMN_NAME = %s
				SQL,
				$table,
				$column
			)
		);

		return null !== $exists;
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
			[
				'table'   => $wpdb->orbis_monitor_response_stats,
				'name'    => $wpdb->prefix . 'orbis_monitor_response_stats_monitor_id',
				'cleanup' => "DELETE FROM $wpdb->orbis_monitor_response_stats WHERE monitor_id NOT IN ( SELECT id FROM $wpdb->orbis_monitors );",
				'sql'     => "ALTER TABLE $wpdb->orbis_monitor_response_stats ADD CONSTRAINT {$wpdb->prefix}orbis_monitor_response_stats_monitor_id FOREIGN KEY ( monitor_id ) REFERENCES $wpdb->orbis_monitors ( id ) ON DELETE CASCADE;",
			],
			[
				'table'   => $wpdb->orbis_monitor_incidents,
				'name'    => $wpdb->prefix . 'orbis_monitor_incidents_monitor_id',
				'cleanup' => "DELETE FROM $wpdb->orbis_monitor_incidents WHERE monitor_id NOT IN ( SELECT id FROM $wpdb->orbis_monitors );",
				'sql'     => "ALTER TABLE $wpdb->orbis_monitor_incidents ADD CONSTRAINT {$wpdb->prefix}orbis_monitor_incidents_monitor_id FOREIGN KEY ( monitor_id ) REFERENCES $wpdb->orbis_monitors ( id ) ON DELETE CASCADE;",
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
