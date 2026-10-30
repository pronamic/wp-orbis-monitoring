<?php
/**
 * CLI controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

use DateTimeImmutable;
use WP_CLI;

/**
 * Manage the Orbis Monitoring response summaries.
 *
 * @link https://make.wordpress.org/cli/handbook/guides/commands-cookbook/
 */
final readonly class CliController {
	/**
	 * Construct.
	 *
	 * @param ResponseController $response_controller Response controller.
	 */
	public function __construct(
		private ResponseController $response_controller
	) {
	}

	/**
	 * Summarizes the responses per monitor per UTC hour.
	 *
	 * Without options, summarizes all responses that are not summarized yet,
	 * without the limit of 31 days of the hourly scheduled action.
	 *
	 * ## OPTIONS
	 *
	 * [--rebuild]
	 * : Delete the summaries for the period that still has responses and summarize these responses again. Summaries of responses that are already removed are kept.
	 *
	 * [--from=<date>]
	 * : Summarize the responses from this date, for example `2026-10-01`, in the site timezone. Does not change the progress of the scheduled action.
	 *
	 * [--to=<date>]
	 * : Summarize the responses until this date (exclusive), in the site timezone. Defaults to the start of the current hour.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message of `--rebuild`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis-monitoring aggregate-responses
	 *     wp orbis-monitoring aggregate-responses --rebuild --yes
	 *     wp orbis-monitoring aggregate-responses --from=2026-10-01 --to=2026-10-08
	 *
	 * @subcommand aggregate-responses
	 *
	 * @param array<int, string>    $args       Arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function aggregate_responses( $args, $assoc_args ): void {
		global $wpdb;

		$rebuild = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'rebuild', false );

		$first_response_hour = $this->response_controller->get_first_response_hour();

		if ( null === $first_response_hour ) {
			WP_CLI::success( 'There are no responses to summarize.' );

			return;
		}

		$timezone = \wp_timezone();

		$end = $this->response_controller->get_current_hour();

		if ( isset( $assoc_args['to'] ) ) {
			$end = \min( $end, $this->response_controller->start_of_hour( new DateTimeImmutable( $assoc_args['to'], $timezone ) ) );
		}

		$update_progress = false;

		if ( isset( $assoc_args['from'] ) ) {
			$start = \max( $first_response_hour, $this->response_controller->start_of_hour( new DateTimeImmutable( $assoc_args['from'], $timezone ) ) );
		} elseif ( $rebuild ) {
			$start = $first_response_hour;

			$update_progress = true;
		} else {
			$start = $this->response_controller->get_aggregation_start() ?? $first_response_hour;

			$update_progress = true;
		}

		if ( $start >= $end ) {
			WP_CLI::success( 'The responses are already summarized.' );

			return;
		}

		if ( $rebuild ) {
			WP_CLI::confirm(
				\sprintf(
					'Delete the summaries from %s and summarize the responses again?',
					$start->format( 'Y-m-d H:i:s T' )
				),
				$assoc_args
			);

			$deleted = $wpdb->query(
				$wpdb->prepare(
					<<<SQL
					DELETE FROM
						$wpdb->orbis_monitor_response_stats
					WHERE
						started_at >= %s
							AND
						started_at < %s
					;
					SQL,
					$start->format( 'Y-m-d H:i:s' ),
					$end->format( 'Y-m-d H:i:s' )
				)
			);

			WP_CLI::log( \sprintf( 'Deleted %d summaries.', (int) $deleted ) );
		}

		$days = (int) \ceil( ( $end->getTimestamp() - $start->getTimestamp() ) / DAY_IN_SECONDS );

		WP_CLI::log(
			\sprintf(
				'Summarizing the responses from %s until %s.',
				$start->format( 'Y-m-d H:i:s T' ),
				$end->format( 'Y-m-d H:i:s T' )
			)
		);

		$progress = WP_CLI\Utils\make_progress_bar( 'Summarizing responses', $days );

		$from = $start;

		while ( $from < $end ) {
			$to = \min( $from->modify( '+1 day' ), $end );

			$this->response_controller->aggregate( $from, $to );

			// Store the progress after each day, so an interrupted run can continue.
			if ( $update_progress ) {
				$this->response_controller->set_aggregated_until( $to );
			}

			$progress->tick();

			$from = $to;
		}

		$progress->finish();

		WP_CLI::success( 'The responses are summarized.' );
	}

	/**
	 * Compares the number of responses with the summaries per UTC day.
	 *
	 * Only the period that still has responses and is summarized can be compared.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Show all days, not only the days with a difference.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis-monitoring verify-responses
	 *     wp orbis-monitoring verify-responses --all
	 *
	 * @subcommand verify-responses
	 *
	 * @param array<int, string>    $args       Arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function verify_responses( $args, $assoc_args ): void {
		global $wpdb;

		$first_response_hour = $this->response_controller->get_first_response_hour();

		$aggregated_until = $this->response_controller->get_aggregated_until();

		WP_CLI::log( \sprintf( 'First response:   %s', null === $first_response_hour ? '-' : $first_response_hour->format( 'Y-m-d H:i:s T' ) ) );
		WP_CLI::log( \sprintf( 'Summarized until: %s', null === $aggregated_until ? '-' : $aggregated_until->format( 'Y-m-d H:i:s T' ) ) );

		if ( null === $first_response_hour || null === $aggregated_until || $first_response_hour >= $aggregated_until ) {
			WP_CLI::warning( 'There is no period with responses that is summarized, run `wp orbis-monitoring aggregate-responses` first.' );

			return;
		}

		$start = $first_response_hour->format( 'Y-m-d H:i:s' );
		$end   = $aggregated_until->format( 'Y-m-d H:i:s' );

		$response_counts = $wpdb->get_results(
			$wpdb->prepare(
				<<<SQL
				SELECT
					DATE( monitored_date ) AS day,
					COUNT( * ) AS count
				FROM
					$wpdb->orbis_monitor_responses
				WHERE
					monitored_date >= %s
						AND
					monitored_date < %s
				GROUP BY
					day
				SQL,
				$start,
				$end
			),
			OBJECT_K
		);

		$summary_counts = $wpdb->get_results(
			$wpdb->prepare(
				<<<SQL
				SELECT
					DATE( started_at ) AS day,
					SUM( check_count ) AS count
				FROM
					$wpdb->orbis_monitor_response_stats
				WHERE
					started_at >= %s
						AND
					started_at < %s
				GROUP BY
					day
				SQL,
				$start,
				$end
			),
			OBJECT_K
		);

		$days = \array_unique( \array_merge( \array_keys( $response_counts ), \array_keys( $summary_counts ) ) );

		\sort( $days );

		$show_all = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		$items = [];

		$differences = 0;

		foreach ( $days as $day ) {
			$responses  = isset( $response_counts[ $day ] ) ? (int) $response_counts[ $day ]->count : 0;
			$summarized = isset( $summary_counts[ $day ] ) ? (int) $summary_counts[ $day ]->count : 0;

			$difference = $summarized - $responses;

			if ( 0 !== $difference ) {
				++$differences;
			}

			if ( 0 === $difference && ! $show_all ) {
				continue;
			}

			$items[] = [
				'day'        => $day,
				'responses'  => $responses,
				'summarized' => $summarized,
				'difference' => $difference,
			];
		}

		if ( [] !== $items ) {
			WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, [ 'day', 'responses', 'summarized', 'difference' ] );
		}

		if ( $differences > 0 ) {
			WP_CLI::error( \sprintf( '%d of %d days have a difference, run `wp orbis-monitoring aggregate-responses --rebuild` to summarize the responses again.', $differences, \count( $days ) ) );
		}

		WP_CLI::success( \sprintf( 'The summaries of %d days match the responses.', \count( $days ) ) );
	}
}
