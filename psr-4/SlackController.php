<?php
/**
 * Slack controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Slack controller class
 */
final class SlackController {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_filter( 'slack_get_events', $this->slack_get_events( ... ) );
	}

	/**
	 * Slack events.
	 *
	 * @see http://gedex.web.id/wp-slack/
	 * @see https://github.com/gedex/wp-slack/blob/0.5.1/includes/event-manager.php#L57-L167
	 * @see https://github.com/gedex/wp-slack-edd/blob/0.1.0/slack-edd.php
	 *
	 * @param array<string, array<string, mixed>> $events Events.
	 * @return array<string, array<string, mixed>>
	 */
	private function slack_get_events( $events ) {
		/*
		 * The `slack_get_events` filter is applied on `plugins_loaded`, before the
		 * `init` action, so translations are only resolved when it is safe to do so.
		 * The event descriptions are only displayed on the Slack integration settings
		 * page, where this filter is applied again after the `init` action.
		 *
		 * @link https://github.com/pronamic/wp-orbis-monitoring/issues/21
		 */
		$can_translate = ( \did_action( 'init' ) || \doing_action( 'init' ) );

		$opened_description   = 'When a monitor detects an outage.';
		$reminder_description = 'When a monitor detects that an outage continues (reminder).';
		$resolved_description = 'When a monitor detects a recovery.';

		if ( $can_translate ) {
			$opened_description   = \__( 'When a monitor detects an outage.', 'orbis-monitoring' );
			$reminder_description = \__( 'When a monitor detects that an outage continues (reminder).', 'orbis-monitoring' );
			$resolved_description = \__( 'When a monitor detects a recovery.', 'orbis-monitoring' );
		}

		/*
		 * These events only fire when the status of a monitor changes, not for
		 * every check, so a Slack channel is not flooded during an outage.
		 *
		 * @see IncidentController
		 */
		$events['orbis_monitoring_incident_opened'] = [
			'action'      => 'orbis_monitoring_incident_opened',
			'description' => $opened_description,
			'message'     => fn( $post, $response ) => ':red_circle: ' . \sprintf(
				/* translators: %s: monitor link */
				\__( '%s is offline.', 'orbis-monitoring' ),
				$this->get_link( $post )
			) . "\n" . $this->get_details( $post, $response ),
		];

		$events['orbis_monitoring_incident_reminder'] = [
			'action'      => 'orbis_monitoring_incident_reminder',
			'description' => $reminder_description,
			'message'     => fn( $post, $response, $incident ) => ':red_circle: ' . \sprintf(
				/* translators: 1: monitor link, 2: duration */
				\__( '%1$s is still offline for %2$s.', 'orbis-monitoring' ),
				$this->get_link( $post ),
				\human_time_diff( (int) \strtotime( $incident->started_at . ' UTC' ) )
			) . "\n" . $this->get_details( $post, $response ),
		];

		$events['orbis_monitoring_incident_resolved'] = [
			'action'      => 'orbis_monitoring_incident_resolved',
			'description' => $resolved_description,
			'message'     => fn( $post, $incident ) => ':white_check_mark: ' . \sprintf(
				/* translators: 1: monitor link, 2: duration */
				\__( '%1$s is back online after %2$s.', 'orbis-monitoring' ),
				$this->get_link( $post ),
				\human_time_diff( (int) \strtotime( $incident->started_at . ' UTC' ), (int) \strtotime( $incident->resolved_at . ' UTC' ) )
			) . "\n" . $this->get_details( $post ),
		];

		return $events;
	}

	/**
	 * Get a Slack link to the monitor, with the monitor title as text.
	 *
	 * @link https://api.slack.com/reference/surfaces/formatting#linking-urls
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private function get_link( $post ): string {
		return \sprintf(
			'<%s|%s>',
			\get_permalink( $post ),
			\get_the_title( $post )
		);
	}

	/**
	 * Get the details of what was checked, for the line below the status.
	 *
	 * Monitors currently check URLs, other types of monitors can show
	 * their own details here.
	 *
	 * @param \WP_Post                            $post     Post.
	 * @param array<string, mixed>|\WP_Error|null $response Response.
	 * @return string
	 */
	private function get_details( $post, $response = null ): string {
		$details = (string) \get_post_meta( $post->ID, '_orbis_monitor_url', true );

		if ( null === $response ) {
			return $details;
		}

		if ( \is_wp_error( $response ) ) {
			return $details . ' → ' . $response->get_error_message();
		}

		return $details . ' → ' . \sprintf(
			/* translators: %s: response code */
			\__( 'response code `%s`', 'orbis-monitoring' ),
			\get_post_meta( $post->ID, '_orbis_monitor_response_code', true )
		);
	}
}
