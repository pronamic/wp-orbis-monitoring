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
		\add_filter( 'cron_schedules', $this->cron_schedules( ... ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		\add_action( 'init', $this->schedule( ... ) );
		\add_action( 'orbis_monitor', $this->monitor( ... ) );
	}

	/**
	 * Cron Schedules
	 *
	 * @see https://codex.wordpress.org/Function_Reference/wp_schedule_event
	 * @see https://developer.wordpress.org/plugins/cron/understanding-wp-cron-scheduling/
	 * @see http://stackoverflow.com/questions/14103262/customizing-the-wp-schedule-event
	 * @see http://wordpress.stackexchange.com/questions/208135/how-to-run-a-function-every-5-minutes
	 * @see http://shinephp.com/start-scheduled-task-with-wp-cron-more-often/
	 *
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	private function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['every_5_minutes'] ) ) {
			$display = 'Once Every 5 Minutes';

			/*
			 * Translation functions can only be used once the `init` action is running,
			 * the `cron_schedules` filter can be applied earlier.
			 *
			 * @link https://github.com/pronamic/wp-orbis-monitoring/issues/21
			 */
			if ( \did_action( 'init' ) || \doing_action( 'init' ) ) {
				$display = \__( 'Once Every 5 Minutes', 'orbis-monitoring' );
			}

			$schedules['every_5_minutes'] = [
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => $display,
			];
		}

		return $schedules;
	}

	/**
	 * Schedule the monitor event.
	 *
	 * @return void
	 */
	private function schedule(): void {
		if ( ! \wp_next_scheduled( 'orbis_monitor' ) ) {
			\wp_schedule_event( \time(), 'every_5_minutes', 'orbis_monitor' );
		}
	}

	/**
	 * Monitor.
	 *
	 * @return void
	 */
	private function monitor(): void {
		$query = new \WP_Query(
			[
				'post_type'      => 'orbis_monitor',
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$this->monitor_post( \get_post() );

				\wp_update_post(
					[
						'ID' => \get_the_ID(),
					]
				);
			}

			\wp_reset_postdata();
		}
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
