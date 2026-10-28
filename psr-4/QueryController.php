<?php
/**
 * Query controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Query controller class
 */
final class QueryController {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_filter( 'query_vars', $this->query_vars( ... ) );
		\add_filter( 'posts_clauses', $this->posts_clauses( ... ), 10, 2 );
	}

	/**
	 * Query vars.
	 *
	 * @link https://developer.wordpress.org/reference/hooks/query_vars/
	 * @param string[] $query_vars Query vars.
	 * @return string[]
	 */
	private function query_vars( $query_vars ) {
		$query_vars[] = 'orbis_monitor_availability';

		return $query_vars;
	}

	/**
	 * Posts clauses.
	 *
	 * Filters monitors on the availability of the monitored URL. Monitors without
	 * a row in the monitors table have not been checked yet, so they count as unknown.
	 *
	 * @link https://developer.wordpress.org/reference/hooks/posts_clauses/
	 * @param array<string, string> $clauses Clauses.
	 * @param \WP_Query             $query   Query.
	 * @return array<string, string>
	 */
	private function posts_clauses( $clauses, $query ) {
		if ( 'orbis_monitor' !== $query->get( 'post_type' ) ) {
			return $clauses;
		}

		$value = $query->get( 'orbis_monitor_availability' );

		if ( ! \is_string( $value ) ) {
			return $clauses;
		}

		$availability = Availability::tryFrom( $value );

		if ( null === $availability ) {
			return $clauses;
		}

		global $wpdb;

		$clauses['join'] .= "\n" . <<<SQL
			LEFT JOIN
				$wpdb->orbis_monitors AS orbis_monitor
					ON $wpdb->posts.ID = orbis_monitor.post_id
			SQL;

		$clauses['where'] .= match ( $availability ) {
			Availability::Unknown => $wpdb->prepare( ' AND ( orbis_monitor.status = %s OR orbis_monitor.id IS NULL )', $availability->value ),
			default               => $wpdb->prepare( ' AND orbis_monitor.status = %s', $availability->value ),
		};

		return $clauses;
	}
}
