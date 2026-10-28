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
 * Keeps track of the status of each monitor (`unknown`, `up`, `pending` or
 * `down`) and of the incidents. A monitor is only considered down after a
 * number of failed checks in a row, and the `orbis_monitor_down`,
 * `orbis_monitor_still_down` and `orbis_monitor_up` actions only fire when
 * the status changes, so notifications are not sent for every check.
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
	 * @param bool                           $is_up      Whether the check succeeded.
	 * @param int                            $monitor_id Monitor ID.
	 * @return void
	 */
	public function process( \WP_Post $post, $response, bool $is_up, int $monitor_id ): void {
		$monitor = $this->get_monitor( $monitor_id );

		if ( null === $monitor ) {
			return;
		}

		$now = \current_time( 'mysql', true );

		if ( $is_up ) {
			if ( 'down' !== $monitor->status ) {
				$this->update_monitor( $monitor_id, 'up', 0, ( 'up' === $monitor->status ) ? $monitor->status_changed_at : $now );

				return;
			}

			$incident = $this->get_open_incident( $monitor_id );

			$this->update_monitor( $monitor_id, 'up', 0, $now );

			if ( null === $incident ) {
				return;
			}

			$this->update_incident( (int) $incident->id, [ 'resolved_at' => $now ] );

			$incident->resolved_at = $now;

			\do_action( 'orbis_monitor_up', $post, $incident );

			return;
		}

		$failure_count = (int) $monitor->failure_count + 1;

		if ( 'down' === $monitor->status ) {
			$this->update_monitor( $monitor_id, 'down', $failure_count, $monitor->status_changed_at );

			$this->maybe_remind( $post, $response, $monitor_id, $now );

			return;
		}

		if ( $failure_count < $this->settings_controller->get_failure_threshold() ) {
			$this->update_monitor( $monitor_id, 'pending', $failure_count, ( 'pending' === $monitor->status ) ? $monitor->status_changed_at : $now );

			return;
		}

		// The incident started with the first failed check.
		$started_at = ( 'pending' === $monitor->status && null !== $monitor->status_changed_at ) ? $monitor->status_changed_at : $now;

		$incident = $this->open_incident( $monitor_id, $response, $started_at, $now );

		$this->update_monitor( $monitor_id, 'down', $failure_count, $started_at );

		\do_action( 'orbis_monitor_down', $post, $response, $incident );
	}

	/**
	 * Maybe send a reminder for a monitor that is still down.
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

		\do_action( 'orbis_monitor_still_down', $post, $response, $incident );
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

		$status = $this->get_status( $post->ID );

		if ( null === $status ) {
			return;
		}

		$monitor_id = (int) $status->id;

		$now = \current_time( 'mysql', true );

		$incident = $this->get_open_incident( $monitor_id );

		if ( null !== $incident ) {
			$this->update_incident( (int) $incident->id, [ 'resolved_at' => $now ] );
		}

		$this->update_monitor( $monitor_id, 'unknown', 0, $now );
	}

	/**
	 * Get the status of a monitor post.
	 *
	 * @param int $post_id Post ID.
	 * @return object{id: string, status: string, failure_count: string, status_changed_at: string|null}|null
	 */
	public function get_status( $post_id ): ?object {
		global $wpdb;

		$status = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, failure_count, status_changed_at FROM $wpdb->orbis_monitors WHERE post_id = %d;",
				$post_id
			)
		);

		return $status ?? null;
	}

	/**
	 * Get monitor.
	 *
	 * @param int $monitor_id Monitor ID.
	 * @return object{status: string, failure_count: string, status_changed_at: string|null}|null
	 */
	private function get_monitor( int $monitor_id ): ?object {
		global $wpdb;

		$monitor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, failure_count, status_changed_at FROM $wpdb->orbis_monitors WHERE id = %d;",
				$monitor_id
			)
		);

		return $monitor ?? null;
	}

	/**
	 * Update monitor.
	 *
	 * @param int         $monitor_id        Monitor ID.
	 * @param string      $status            Status.
	 * @param int         $failure_count     Number of failed checks in a row.
	 * @param string|null $status_changed_at GMT date the status changed.
	 * @return void
	 */
	private function update_monitor( int $monitor_id, string $status, int $failure_count, ?string $status_changed_at ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->orbis_monitors,
			[
				'status'            => $status,
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
	 * @return object{id: string, monitor_id: string, started_at: string, resolved_at: string|null, last_notified_at: string|null, response_code: string|null, response_message: string|null}|null
	 */
	private function get_open_incident( int $monitor_id ): ?object {
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
	 * @return object{id: int, monitor_id: int, started_at: string, resolved_at: null, last_notified_at: string, response_code: string, response_message: string}
	 */
	private function open_incident( int $monitor_id, $response, string $started_at, string $now ): object {
		global $wpdb;

		$response_code    = (string) \wp_remote_retrieve_response_code( $response );
		$response_message = \is_wp_error( $response ) ? $response->get_error_message() : \wp_remote_retrieve_response_message( $response );

		$data = [
			'monitor_id'       => $monitor_id,
			'started_at'       => $started_at,
			'last_notified_at' => $now,
			'response_code'    => $response_code,
			'response_message' => \mb_substr( $response_message, 0, 255 ),
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
