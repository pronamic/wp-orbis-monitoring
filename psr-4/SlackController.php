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

		$problem_description = 'When a Orbis monitor problem was detected.';
		$checked_description = 'When a Orbis monitor was checked.';

		if ( $can_translate ) {
			$problem_description = \__( 'When a Orbis monitor problem was detected.', 'orbis-monitoring' );
			$checked_description = \__( 'When a Orbis monitor was checked.', 'orbis-monitoring' );
		}

		$events['orbis_monitor_problem'] = [
			'action'      => 'orbis_monitor_problem',
			'description' => $problem_description,
			'message'     => function ( $post, $response, $extra ) {
				$message = \sprintf(
					\__( 'Orbis monitor <%s|%s> was just checked, response code was `%s` » %s.', 'orbis-monitoring' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
					\get_permalink( $post ),
					\get_the_title( $post ),
					\get_post_meta( $post->ID, '_orbis_monitor_response_code', true ),
					\get_post_meta( $post->ID, '_orbis_monitor_url', true )
				);

				if ( \is_wp_error( $response ) ) {
					$message .= "\n";
					$message .= $response->get_error_message();
				}

				if ( ! empty( $extra ) ) {
					$message .= "\n";
					$message .= $extra;
				}

				return $message;
			},
		];

		$events['orbis_monitor_checked'] = [
			'action'      => 'orbis_monitor_checked',
			'description' => $checked_description,
			'message'     => function ( $post ) {
				return \sprintf(
					\__( 'Orbis monitor <%s|%s> was just checked, response code was `%s` » %s.', 'orbis-monitoring' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
					\get_permalink( $post ),
					\get_the_title( $post ),
					\get_post_meta( $post->ID, '_orbis_monitor_response_code', true ),
					\get_post_meta( $post->ID, '_orbis_monitor_url', true )
				);
			},
		];

		return $events;
	}
}
