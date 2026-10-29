<?php
/**
 * Incident controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Incident controller class
 *
 * Keeps track of the status of each monitor (see `Status`) and of the
 * incidents. An incident is only opened after a number
 * of failed checks in a row. The `orbis_monitoring_incident_opened` and
 * `orbis_monitoring_incident_resolved` actions fire when an incident is
 * opened or resolved, and the `orbis_monitoring_incident_reminder` action
 * fires at the reminder interval while an incident is open, so
 * notifications are not sent for every check.
 *
 * @link https://github.com/louislam/uptime-kuma
 */
final readonly class IncidentController {
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
		\add_action( 'transition_post_status', $this->transition_post_status( ... ), 10, 3 );
	}

	/**
	 * Process the result of a check.
	 *
	 * @param \WP_Post                       $post       Post.
	 * @param array<string, mixed>|\WP_Error $response   Response.
	 * @param bool                           $check_succeeded Whether the check succeeded.
	 * @param int                            $monitor_id Monitor ID.
	 * @return void
	 */
	public function process( \WP_Post $post, $response, bool $check_succeeded, int $monitor_id ): void {
		$monitor = $this->get_monitor( $monitor_id );

		if ( null === $monitor ) {
			return;
		}

		$now = \current_time( 'mysql', true );

		if ( $check_succeeded ) {
			if ( Status::Alert !== $monitor->status ) {
				$this->update_monitor( $monitor_id, Status::Ok, 0, ( Status::Ok === $monitor->status ) ? $monitor->status_changed_at : $now );

				return;
			}

			$incident = $this->get_open_incident( $monitor_id );

			$this->update_monitor( $monitor_id, Status::Ok, 0, $now );

			if ( null === $incident ) {
				return;
			}

			$this->update_incident( (int) $incident->id, [ 'resolved_at' => $now ] );

			$incident->resolved_at = $now;

			\do_action( 'orbis_monitoring_incident_resolved', $post, $incident );

			return;
		}

		$failure_count = (int) $monitor->failure_count + 1;

		if ( Status::Alert === $monitor->status ) {
			$this->update_monitor( $monitor_id, Status::Alert, $failure_count, $monitor->status_changed_at );

			$this->maybe_remind( $post, $response, $monitor_id, $now );

			return;
		}

		if ( $failure_count < $this->settings_controller->get_failure_threshold() ) {
			$this->update_monitor( $monitor_id, Status::Pending, $failure_count, ( Status::Pending === $monitor->status ) ? $monitor->status_changed_at : $now );

			return;
		}

		// The incident started with the first failed check.
		$started_at = ( Status::Pending === $monitor->status && null !== $monitor->status_changed_at ) ? $monitor->status_changed_at : $now;

		$incident = $this->open_incident( $monitor_id, $response, $started_at, $now );

		$this->update_monitor( $monitor_id, Status::Alert, $failure_count, $started_at );

		\do_action( 'orbis_monitoring_incident_opened', $post, $response, $incident );
	}

	/**
	 * Maybe send a reminder while the alert is still open.
	 *
	 * @param \WP_Post                       $post       Post.
	 * @param array<string, mixed>|\WP_Error $response   Response.
	 * @param int                            $monitor_id Monitor ID.
	 * @param string                         $now        Current GMT date.
	 * @return void
	 */
	private function maybe_remind( \WP_Post $post, $response, int $monitor_id, string $now ): void {
		$interval = $this->settings_controller->get_reminder_interval();

		if ( $interval <= 0 ) {
			return;
		}

		$incident = $this->get_open_incident( $monitor_id );

		if ( null === $incident ) {
			return;
		}

		$last_notified_at = ( null === $incident->last_notified_at ) ? false : \strtotime( $incident->last_notified_at . ' UTC' );

		if ( false !== $last_notified_at && ( \time() - $last_notified_at ) < ( $interval * MINUTE_IN_SECONDS ) ) {
			return;
		}

		$this->update_incident( (int) $incident->id, [ 'last_notified_at' => $now ] );

		$incident->last_notified_at = $now;

		\do_action( 'orbis_monitoring_incident_reminder', $post, $response, $incident );
	}

	/**
	 * Transition post status.
	 *
	 * When a monitor is no longer published (for example set to draft), the
	 * open incident is closed without a notification and the status is reset.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	private function transition_post_status( $new_status, $old_status, $post ): void {
		if ( 'orbis_monitor' !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $old_status || 'publish' === $new_status ) {
			return;
		}

		$monitor = $this->get_monitor_by_post_id( $post->ID );

		if ( null === $monitor ) {
			return;
		}

		$monitor_id = (int) $monitor->id;

		$now = \current_time( 'mysql', true );

		$incident = $this->get_open_incident( $monitor_id );

		if ( null !== $incident ) {
			$this->update_incident( (int) $incident->id, [ 'resolved_at' => $now ] );
		}

		$this->update_monitor( $monitor_id, Status::Unknown, 0, $now );
	}

	/**
	 * Get the monitor of a monitor post.
	 *
	 * @param int $post_id Post ID.
	 * @return object{id: string, status: Status, failure_count: string, status_changed_at: string|null}|null
	 */
	public function get_monitor_by_post_id( $post_id ): ?object {
		global $wpdb;

		$monitor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, failure_count, status_changed_at FROM $wpdb->orbis_monitors WHERE post_id = %d;",
				$post_id
			)
		);

		return ( null === $monitor ) ? null : $this->hydrate( $monitor );
	}

	/**
	 * Get the monitors of monitor posts.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array<int, object{id: string, post_id: string, status: Status, failure_count: string, status_changed_at: string|null}> Monitors keyed by post ID.
	 */
	public function get_monitors_by_post_ids( array $post_ids ): array {
		global $wpdb;

		$post_ids = \array_filter( \array_map( 'intval', $post_ids ) );

		if ( [] === $post_ids ) {
			return [];
		}

		$placeholders = \implode( ', ', \array_fill( 0, \count( $post_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholders are generated above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, post_id, status, failure_count, status_changed_at FROM $wpdb->orbis_monitors WHERE post_id IN ( $placeholders );",
				$post_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$monitors = [];

		foreach ( $rows as $row ) {
			$monitors[ (int) $row->post_id ] = $this->hydrate( $row );
		}

		return $monitors;
	}

	/**
	 * Count the published monitors per status.
	 *
	 * Monitors without a row in the monitors table have not been checked yet, so they count as unknown.
	 *
	 * @return array<string, int> Number of monitors keyed by status value.
	 */
	public function get_status_counts(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			<<<SQL
			SELECT
				COALESCE( orbis_monitor.status, 'unknown' ) AS status,
				COUNT( post.ID ) AS count
			FROM
				$wpdb->posts AS post
					LEFT JOIN
				$wpdb->orbis_monitors AS orbis_monitor
						ON post.ID = orbis_monitor.post_id
			WHERE
				post.post_type = 'orbis_monitor'
					AND
				post.post_status = 'publish'
			GROUP BY
				status
			;
			SQL
		);

		$counts = \array_fill_keys( \array_column( Status::cases(), 'value' ), 0 );

		foreach ( $rows as $row ) {
			if ( \array_key_exists( $row->status, $counts ) ) {
				$counts[ $row->status ] = (int) $row->count;
			}
		}

		return $counts;
	}

	/**
	 * Get the incidents of a monitor post, newest first.
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   Maximum number of incidents.
	 * @return array<object{id: string, monitor_id: string, started_at: string, resolved_at: string|null, last_notified_at: string|null, response_code: string|null, error_message: string|null}>
	 */
	public function get_incidents( int $post_id, int $limit = 10 ): array {
		global $wpdb;

		$incidents = $wpdb->get_results(
			$wpdb->prepare(
				<<<SQL
				SELECT
					incident.*
				FROM
					$wpdb->orbis_monitor_incidents AS incident
						INNER JOIN
					$wpdb->orbis_monitors AS monitor
							ON monitor.id = incident.monitor_id
				WHERE
					monitor.post_id = %d
				ORDER BY
					incident.started_at DESC
				LIMIT
					0, %d
				;
				SQL,
				$post_id,
				$limit
			)
		);

		return $incidents ?? [];
	}

	/**
	 * Get the human readable duration of an incident.
	 *
	 * Ongoing incidents are measured until now.
	 *
	 * @param object{started_at: string, resolved_at: string|null} $incident Incident.
	 * @return string
	 */
	public function get_incident_duration( object $incident ): string {
		$from = (int) \strtotime( $incident->started_at . ' UTC' );
		$to   = ( null === $incident->resolved_at ) ? \time() : (int) \strtotime( $incident->resolved_at . ' UTC' );

		return \human_time_diff( $from, $to );
	}

	/**
	 * Get monitor.
	 *
	 * @param int $monitor_id Monitor ID.
	 * @return object{status: Status, failure_count: string, status_changed_at: string|null}|null
	 */
	private function get_monitor( int $monitor_id ): ?object {
		global $wpdb;

		$monitor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, failure_count, status_changed_at FROM $wpdb->orbis_monitors WHERE id = %d;",
				$monitor_id
			)
		);

		return ( null === $monitor ) ? null : $this->hydrate( $monitor );
	}

	/**
	 * Hydrate a monitor row, the `status` value is converted to a `Status`.
	 *
	 * @param object $row Row.
	 * @return object
	 */
	private function hydrate( object $row ): object {
		$row->status = Status::tryFrom( (string) $row->status ) ?? Status::Unknown;

		return $row;
	}

	/**
	 * Update monitor.
	 *
	 * @param int         $monitor_id        Monitor ID.
	 * @param Status      $status            Status.
	 * @param int         $failure_count     Number of failed checks in a row.
	 * @param string|null $status_changed_at GMT date the status changed.
	 * @return void
	 */
	private function update_monitor( int $monitor_id, Status $status, int $failure_count, ?string $status_changed_at ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->orbis_monitors,
			[
				'status'            => $status->value,
				'failure_count'     => $failure_count,
				'status_changed_at' => $status_changed_at,
				'updated_at'        => \current_time( 'mysql', true ),
			],
			[
				'id' => $monitor_id,
			],
			[
				'%s',
				'%d',
				'%s',
				'%s',
			],
			[
				'%d',
			]
		);
	}

	/**
	 * Get the open incident of a monitor.
	 *
	 * @param int $monitor_id Monitor ID.
	 * @return object{id: string, monitor_id: string, started_at: string, resolved_at: string|null, last_notified_at: string|null, response_code: string|null, error_message: string|null}|null
	 */
	public function get_open_incident( int $monitor_id ): ?object {
		global $wpdb;

		$incident = $wpdb->get_row(
			$wpdb->prepare(
				<<<SQL
				SELECT
					*
				FROM
					$wpdb->orbis_monitor_incidents
				WHERE
					monitor_id = %d
						AND
					resolved_at IS NULL
				ORDER BY
					started_at DESC
				LIMIT
					1
				;
				SQL,
				$monitor_id
			)
		);

		return $incident ?? null;
	}

	/**
	 * Open incident.
	 *
	 * @param int                            $monitor_id Monitor ID.
	 * @param array<string, mixed>|\WP_Error $response   Response.
	 * @param string                         $started_at GMT date the incident started.
	 * @param string                         $now        Current GMT date.
	 * @return object{id: int, monitor_id: int, started_at: string, resolved_at: null, last_notified_at: string, response_code: string, error_message: string|null}
	 */
	private function open_incident( int $monitor_id, $response, string $started_at, string $now ): object {
		global $wpdb;

		$response_code = (string) \wp_remote_retrieve_response_code( $response );
		$error_message = \is_wp_error( $response ) ? \mb_substr( $response->get_error_message(), 0, 255 ) : null;

		$data = [
			'monitor_id'       => $monitor_id,
			'started_at'       => $started_at,
			'last_notified_at' => $now,
			'response_code'    => $response_code,
			'error_message'    => $error_message,
		];

		$wpdb->insert(
			$wpdb->orbis_monitor_incidents,
			$data,
			[
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);

		return (object) ( [ 'id' => (int) $wpdb->insert_id ] + $data + [ 'resolved_at' => null ] );
	}

	/**
	 * Update incident.
	 *
	 * @param int                   $incident_id Incident ID.
	 * @param array<string, string> $data        Data.
	 * @return void
	 */
	private function update_incident( int $incident_id, array $data ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->orbis_monitor_incidents,
			$data,
			[
				'id' => $incident_id,
			],
			\array_fill( 0, \count( $data ), '%s' ),
			[
				'%d',
			]
		);
	}
}
