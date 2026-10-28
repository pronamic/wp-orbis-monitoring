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

		$down_description       = 'When a Orbis monitor is down.';
		$still_down_description = 'When a Orbis monitor is still down (reminder).';
		$up_description         = 'When a Orbis monitor is up again.';

		if ( $can_translate ) {
			$down_description       = \__( 'When a Orbis monitor is down.', 'orbis-monitoring' );
			$still_down_description = \__( 'When a Orbis monitor is still down (reminder).', 'orbis-monitoring' );
			$up_description         = \__( 'When a Orbis monitor is up again.', 'orbis-monitoring' );
		}

		/*
		 * These events only fire when the status of a monitor changes, not for
		 * every check, so a Slack channel is not flooded during an outage.
		 *
		 * @see IncidentController
		 */
		$events['orbis_monitor_down'] = [
			'action'      => 'orbis_monitor_down',
			'description' => $down_description,
			'message'     => function ( $post, $response ) {
				$message = ':red_circle: ' . \sprintf(
					/* translators: 1: monitor permalink, 2: monitor title, 3: response code, 4: monitor URL */
					\__( 'Orbis monitor <%1$s|%2$s> is down, response code was `%3$s` » %4$s.', 'orbis-monitoring' ),
					\get_permalink( $post ),
					\get_the_title( $post ),
					\get_post_meta( $post->ID, '_orbis_monitor_response_code', true ),
					\get_post_meta( $post->ID, '_orbis_monitor_url', true )
				);

				if ( \is_wp_error( $response ) ) {
					$message .= "\n";
					$message .= $response->get_error_message();
				}

				return $message;
			},
		];

		$events['orbis_monitor_still_down'] = [
			'action'      => 'orbis_monitor_still_down',
			'description' => $still_down_description,
			'message'     => fn( $post, $response, $incident ) => ':red_circle: ' . \sprintf(
				/* translators: 1: monitor permalink, 2: monitor title, 3: duration, 4: response code, 5: monitor URL */
				\__( 'Orbis monitor <%1$s|%2$s> is still down for %3$s, response code was `%4$s` » %5$s.', 'orbis-monitoring' ),
				\get_permalink( $post ),
				\get_the_title( $post ),
				\human_time_diff( (int) \strtotime( $incident->started_at . ' UTC' ) ),
				\get_post_meta( $post->ID, '_orbis_monitor_response_code', true ),
				\get_post_meta( $post->ID, '_orbis_monitor_url', true )
			),
		];

		$events['orbis_monitor_up'] = [
			'action'      => 'orbis_monitor_up',
			'description' => $up_description,
			'message'     => fn( $post, $incident ) => ':white_check_mark: ' . \sprintf(
				/* translators: 1: monitor permalink, 2: monitor title, 3: duration, 4: monitor URL */
				\__( 'Orbis monitor <%1$s|%2$s> is up again after %3$s » %4$s.', 'orbis-monitoring' ),
				\get_permalink( $post ),
				\get_the_title( $post ),
				\human_time_diff( (int) \strtotime( $incident->started_at . ' UTC' ), (int) \strtotime( $incident->resolved_at . ' UTC' ) ),
				\get_post_meta( $post->ID, '_orbis_monitor_url', true )
			),
		];

		return $events;
	}
}
