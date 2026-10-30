<?php
/**
 * Response controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Response controller class
 *
 * Each check adds a row to the responses table. To keep the table lean, the
 * responses are summarized per hour in the response stats table and removed
 * after the response retention period.
 */
final readonly class ResponseController {
	/**
	 * Construct.
	 *
	 * @param SettingsController $settings_controller Settings controller.
	 */
	public function __construct(
		private SettingsController $settings_controller
	) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'action_scheduler_init', $this->schedule( ... ) );
		\add_action( 'orbis_monitoring_aggregate_responses', $this->aggregate_responses( ... ) );
		\add_action( 'orbis_monitoring_cleanup_responses', $this->cleanup_responses( ... ) );
	}

	/**
	 * Schedule the recurring actions that summarize and clean up the responses.
	 *
	 * @link https://actionscheduler.org/api/
	 * @return void
	 */
	private function schedule(): void {
		if ( false === \as_has_scheduled_action( 'orbis_monitoring_aggregate_responses' ) ) {
			\as_schedule_recurring_action( \time(), HOUR_IN_SECONDS, 'orbis_monitoring_aggregate_responses', [], 'orbis-monitoring', true );
		}

		if ( false === \as_has_scheduled_action( 'orbis_monitoring_cleanup_responses' ) ) {
			\as_schedule_recurring_action( \time(), DAY_IN_SECONDS, 'orbis_monitoring_cleanup_responses', [], 'orbis-monitoring', true );
		}
	}

	/**
	 * Aggregate responses.
	 *
	 * Summarizes the responses up to the start of the current hour. The
	 * progress is stored in the `orbis_monitoring_responses_aggregated_until`
	 * option. The last summarized hour is summarized again, in case responses
	 * were added after it was summarized. A run handles at most 31 days, so a
	 * large backlog is processed over several runs.
	 *
	 * @return void
	 */
	private function aggregate_responses(): void {
		$start = $this->get_aggregation_start();

		if ( null === $start ) {
			return;
		}

		$end = \min( $this->get_current_hour(), $start->modify( '+31 days' ) );

		if ( $start >= $end ) {
			return;
		}

		$this->aggregate( $start, $end );

		$this->set_aggregated_until( $end );
	}

	/**
	 * Get the start of the next aggregation.
	 *
	 * This is the hour before the `orbis_monitoring_responses_aggregated_until`
	 * option, or the hour of the first response when nothing is summarized yet.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function get_aggregation_start(): ?DateTimeImmutable {
		$aggregated_until = $this->get_aggregated_until();

		if ( null !== $aggregated_until ) {
			return $aggregated_until->modify( '-1 hour' );
		}

		return $this->get_first_response_hour();
	}

	/**
	 * Get the date until which the responses are summarized.
	 *
	 * The option contains a UTC date.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function get_aggregated_until(): ?DateTimeImmutable {
		$value = (string) \get_option( 'orbis_monitoring_responses_aggregated_until', '' );

		if ( '' === $value ) {
			return null;
		}

		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Set the date until which the responses are summarized.
	 *
	 * @param DateTimeImmutable $date Date.
	 * @return void
	 */
	public function set_aggregated_until( DateTimeImmutable $date ): void {
		\update_option( 'orbis_monitoring_responses_aggregated_until', $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), false );
	}

	/**
	 * Get the hour of the first response.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function get_first_response_hour(): ?DateTimeImmutable {
		global $wpdb;

		$first_monitored_date = $wpdb->get_var( "SELECT MIN( monitored_date ) FROM $wpdb->orbis_monitor_responses;" );

		if ( null === $first_monitored_date ) {
			return null;
		}

		return $this->start_of_hour( new DateTimeImmutable( $first_monitored_date, new DateTimeZone( 'UTC' ) ) );
	}

	/**
	 * Get the start of the current hour.
	 *
	 * @return DateTimeImmutable
	 */
	public function get_current_hour(): DateTimeImmutable {
		return $this->start_of_hour( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
	}

	/**
	 * Aggregate the responses in a period.
	 *
	 * Summarizes the responses per monitor per UTC hour. Existing summaries
	 * in the period are replaced, so running it again gives the same result.
	 * The responses are summarized per monitor, so the queries use the
	 * `monitor_id_monitored_date` index. The response dates are stored in
	 * UTC, see `current_time( 'mysql', true )`.
	 *
	 * @param DateTimeImmutable $start Start (inclusive), should be the start of a UTC hour.
	 * @param DateTimeImmutable $end   End (exclusive), should be the start of a UTC hour.
	 * @return void
	 */
	public function aggregate( DateTimeImmutable $start, DateTimeImmutable $end ): void {
		global $wpdb;

		$monitor_ids = $wpdb->get_col( "SELECT id FROM $wpdb->orbis_monitors;" );

		foreach ( $monitor_ids as $monitor_id ) {
			$wpdb->query(
				$wpdb->prepare(
					<<<SQL
					INSERT INTO $wpdb->orbis_monitor_response_stats (
						monitor_id,
						started_at,
						ended_at,
						check_count,
						duration_total,
						duration_min,
						duration_max
					)
					SELECT
						response.monitor_id,
						DATE_FORMAT( response.monitored_date, '%%Y-%%m-%%d %%H:00:00' ) AS started_at,
						DATE_FORMAT( response.monitored_date, '%%Y-%%m-%%d %%H:00:00' ) + INTERVAL 1 HOUR AS ended_at,
						COUNT( * ) AS check_count,
						COALESCE( SUM( response.duration ), 0 ) AS duration_total,
						MIN( response.duration ) AS duration_min,
						MAX( response.duration ) AS duration_max
					FROM
						$wpdb->orbis_monitor_responses AS response
					WHERE
						response.monitor_id = %d
							AND
						response.monitored_date >= %s
							AND
						response.monitored_date < %s
					GROUP BY
						response.monitor_id,
						started_at,
						ended_at
					ON DUPLICATE KEY UPDATE
						ended_at = VALUES( ended_at ),
						check_count = VALUES( check_count ),
						duration_total = VALUES( duration_total ),
						duration_min = VALUES( duration_min ),
						duration_max = VALUES( duration_max )
					;
					SQL,
					$monitor_id,
					$start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
					$end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
				)
			);
		}
	}

	/**
	 * Cleanup responses.
	 *
	 * Deletes the responses older than the response retention period. Only
	 * responses that are summarized in the response stats table are deleted.
	 * The rows are deleted per monitor in small batches, to keep the locks short.
	 *
	 * @return void
	 */
	private function cleanup_responses(): void {
		global $wpdb;

		$aggregated_until = $this->get_aggregated_until();

		if ( null === $aggregated_until ) {
			return;
		}

		$retention_days = $this->settings_controller->get_response_retention_days();

		/*
		 * The cutoff is the start of an hour, so the remaining responses always
		 * start with a complete hour and can be summarized again.
		 */
		$cutoff = \min(
			$this->get_current_hour()->modify( '-' . $retention_days . ' days' ),
			$aggregated_until
		)->format( 'Y-m-d H:i:s' );

		$monitor_ids = $wpdb->get_col( "SELECT id FROM $wpdb->orbis_monitors;" );

		foreach ( $monitor_ids as $monitor_id ) {
			do {
				$deleted = (int) $wpdb->query(
					$wpdb->prepare(
						<<<SQL
						DELETE FROM
							$wpdb->orbis_monitor_responses
						WHERE
							monitor_id = %d
								AND
							monitored_date < %s
						LIMIT
							10000
						;
						SQL,
						$monitor_id,
						$cutoff
					)
				);
			} while ( $deleted >= 10000 );
		}
	}

	/**
	 * Start of the UTC hour.
	 *
	 * @param DateTimeImmutable $date Date.
	 * @return DateTimeImmutable
	 */
	public function start_of_hour( DateTimeImmutable $date ): DateTimeImmutable {
		$date = $date->setTimezone( new DateTimeZone( 'UTC' ) );

		return $date->setTime( (int) $date->format( 'G' ), 0 );
	}

	/**
	 * Get the recent checks of a monitor.
	 *
	 * @param int $post_id Monitor post ID.
	 * @param int $limit   Maximum number of checks.
	 * @return object[] Checks, oldest first.
	 */
	public function get_recent_checks( int $post_id, int $limit ): array {
		global $wpdb;

		$checks = $wpdb->get_results(
			$wpdb->prepare(
				<<<SQL
				SELECT
					response.monitored_date,
					response.duration,
					response.response_code,
					response.error_message,
					response.check_succeeded
				FROM
					$wpdb->orbis_monitor_responses AS response
						INNER JOIN
					$wpdb->orbis_monitors AS monitor
							ON monitor.id = response.monitor_id
				WHERE
					monitor.post_id = %d
				ORDER BY
					response.monitored_date DESC
				LIMIT
					%d
				;
				SQL,
				$post_id,
				$limit
			)
		);

		return \array_reverse( $checks );
	}

	/**
	 * Get the recent checks of multiple monitors in one query.
	 *
	 * Uses a `UNION ALL` of one subquery per monitor, so each subquery can use
	 * the `monitor_id_monitored_date` index and stop after `$limit` rows.
	 *
	 * @param int[] $monitor_ids Monitor IDs.
	 * @param int   $limit       Maximum number of checks per monitor.
	 * @return array<int, object[]> Checks keyed by monitor ID, oldest first.
	 */
	public function get_recent_checks_by_monitor_ids( array $monitor_ids, int $limit ): array {
		global $wpdb;

		$monitor_ids = \array_unique( \array_filter( \array_map( 'intval', $monitor_ids ) ) );

		if ( [] === $monitor_ids ) {
			return [];
		}

		$subqueries = \array_map(
			fn( int $monitor_id ) => $wpdb->prepare(
				<<<SQL
				(
					SELECT
						monitor_id,
						monitored_date,
						duration,
						response_code,
						error_message,
						check_succeeded
					FROM
						$wpdb->orbis_monitor_responses
					WHERE
						monitor_id = %d
					ORDER BY
						monitored_date DESC
					LIMIT
						%d
				)
				SQL,
				$monitor_id,
				$limit
			),
			$monitor_ids
		);

		$union = \implode( "\nUNION ALL\n", $subqueries );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The subqueries are prepared above.
		$rows = $wpdb->get_results(
			<<<SQL
			SELECT
				*
			FROM
				(
					$union
				) AS response
			ORDER BY
				monitor_id ASC,
				monitored_date ASC
			;
			SQL
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$checks = [];

		foreach ( $rows as $row ) {
			$checks[ (int) $row->monitor_id ][] = $row;
		}

		return $checks;
	}
}
