<?php
/**
 * Monitor controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Monitor controller class
 */
final class MonitorController {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'action_scheduler_init', $this->schedule( ... ) );
		\add_action( 'orbis_monitoring_schedule_checks', $this->schedule_checks( ... ) );
		\add_action( 'orbis_monitoring_check_monitor', $this->check_monitor( ... ) );
	}

	/**
	 * Schedule the recurring action that schedules the monitor checks every minute.
	 *
	 * @link https://actionscheduler.org/api/
	 * @return void
	 */
	private function schedule(): void {
		if ( false === \as_has_scheduled_action( 'orbis_monitoring_schedule_checks' ) ) {
			\as_schedule_recurring_action( \time(), MINUTE_IN_SECONDS, 'orbis_monitoring_schedule_checks', [], 'orbis-monitoring', true );
		}
	}

	/**
	 * Schedule a check for each published monitor.
	 *
	 * Checks are unique per monitor, so a slow check is not stacked up.
	 *
	 * @return void
	 */
	private function schedule_checks(): void {
		$post_ids = \get_posts(
			[
				'post_type'      => 'orbis_monitor',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		foreach ( $post_ids as $post_id ) {
			\as_enqueue_async_action( 'orbis_monitoring_check_monitor', [ 'post_id' => $post_id ], 'orbis-monitoring', true );
		}
	}

	/**
	 * Check monitor.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function check_monitor( $post_id ): void {
		$this->monitor_post( $post_id );

		// Touch the modified date, the admin "Last Check" column shows this date.
		\wp_update_post(
			[
				'ID' => $post_id,
			]
		);
	}

	/**
	 * Monitor post.
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return void
	 */
	public function monitor_post( $post ) {
		global $wpdb;

		$post = \get_post( $post );

		$url = \get_post_meta( $post->ID, '_orbis_monitor_url', true );

		if ( empty( $url ) ) {
			return;
		}

		$start = \microtime( true );

		$response = \wp_remote_get(
			$url,
			[
				'timeout'     => 30,
				'redirection' => 0,
				/**
				 * We pretend to be a widely used web browser.
				 *
				 * @link https://github.com/pronamic/wp-orbis-monitoring/issues/10
				 * @link http://www.browser-info.net/useragents
				 * @link https://www.whatismybrowser.com/guides/the-latest-user-agent/chrome
				 */
				'user-agent'  => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36',
			]
		);

		$end = \microtime( true );

		$duration = $end - $start;

		$response_code    = \wp_remote_retrieve_response_code( $response );
		$response_message = \wp_remote_retrieve_response_message( $response );
		$body             = \wp_remote_retrieve_body( $response );
		$content_type     = \wp_remote_retrieve_header( $response, 'content-type' );
		$date             = \wp_remote_retrieve_header( $response, 'date' );

		\update_post_meta( $post->ID, '_orbis_monitor_duration', $duration );
		\update_post_meta( $post->ID, '_orbis_monitor_response_code', $response_code );
		\update_post_meta( $post->ID, '_orbis_monitor_response_message', $response_message );
		\update_post_meta( $post->ID, '_orbis_monitor_response_body', $body );
		\update_post_meta( $post->ID, '_orbis_monitor_response_content_length', \strlen( $body ) );
		\update_post_meta( $post->ID, '_orbis_monitor_response_content_type', $content_type );
		\update_post_meta( $post->ID, '_orbis_monitor_response_date', \strtotime( $date ) );

		$wpdb->insert(
			$wpdb->orbis_monitor_responses,
			[
				'post_id'                 => $post->ID,
				'monitored_date'          => \current_time( 'mysql' ),
				'duration'                => $duration,
				'response_code'           => $response_code,
				'response_message'        => $response_message,
				'response_content_length' => \strlen( $body ),
				'response_content_type'   => $content_type,
			],
			[
				'%d',
				'%s',
				'%f',
				'%s',
				'%s',
				'%d',
				'%s',
			]
		);

		// Custom actions.
		$required_response_code = \get_post_meta( $post->ID, '_orbis_monitor_required_response_code', true );
		$required_response_code = empty( $required_response_code ) ? '200' : $required_response_code;

		$response_code = \get_post_meta( $post->ID, '_orbis_monitor_response_code', true );

		$required_location = \get_post_meta( $post->ID, '_orbis_monitor_required_location', true );

		// Add custom message.
		$message = null;

		if (
			( $required_response_code !== $response_code )
				||
			( ! empty( $required_location ) && \wp_remote_retrieve_header( $response, 'location' ) !== $required_location )
		) {
			\do_action( 'orbis_monitor_problem', $post, $response, $message );
		}

		\do_action( 'orbis_monitor_checked', $post, $response );
	}
}
