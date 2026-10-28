<?php
/**
 * Monitor graph
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view filter.

$periods = [
	'd' => __( 'Day', 'orbis-monitoring' ),
	'w' => __( 'Week', 'orbis-monitoring' ),
	'm' => __( 'Month', 'orbis-monitoring' ),
];

if ( ! array_key_exists( $period, $periods ) ) {
	$period = 'w';
}

$group_by = match ( $period ) {
	'd'     => 'DAY',
	'm'     => 'MONTH',
	default => 'WEEKOFYEAR',
};

$last_year = new DateTimeImmutable( '1 year ago midnight', wp_timezone() );

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The group by function is one of a fixed set of values.
$response_times = $wpdb->get_results(
	$wpdb->prepare(
		<<<SQL
		SELECT
			AVG( duration ) AS avg_duration,
			monitored_date
		FROM
			$wpdb->orbis_monitor_responses
		WHERE
			post_id = %d
				AND
			monitored_date > %s
		GROUP BY
			$group_by( monitored_date )
		ORDER BY
			monitored_date ASC
		SQL,
		get_the_ID(),
		$last_year->format( 'Y-m-d H:i:s' )
	)
);
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$average_durations = [];

foreach ( $response_times as $response ) {
	$average_durations[] = [
		strtotime( $response->monitored_date ) * 1000,
		(float) $response->avg_duration,
	];
}

$flot_options = [
	'xaxis'  => [
		'mode'       => 'time',
		'timeformat' => '%d/%m',
	],
	'series' => [
		'lines'      => [
			'show'      => true,
			'lineWidth' => 4,
		],
		'points'     => [
			'show'      => true,
			'fill'      => true,
			'fillColor' => '#007bff',
			'radius'    => 4,
		],
		'shadowSize' => 0,
	],
	'grid'   => [
		'color'       => '#BBB',
		'borderWidth' => 0.5,
	],
	'colors' => [ '#007bff' ],
];

wp_enqueue_script( 'flotcharts' );
wp_enqueue_script( 'flotcharts-time' );

wp_add_inline_script(
	'flotcharts-time',
	sprintf(
		'jQuery( function( $ ) { $.plot( "#orbis-monitor-graph", [ %s ], %s ); } );',
		wp_json_encode( $average_durations ),
		wp_json_encode( $flot_options )
	)
);

?>
<div class="card mb-3">
	<div class="card-header">
		<?php

		echo esc_html(
			sprintf(
				/* translators: %s: period, for example Day, Week or Month. */
				__( 'Average Response Time Per %s', 'orbis-monitoring' ),
				$periods[ $period ]
			)
		);

		?>
		<button class="btn btn-light dropdown-toggle m-0 p-0 float-right" type="button" id="orbis-monitor-graph-period" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo esc_html( $periods[ $period ] ); ?></button>
		<div class="dropdown-menu" aria-labelledby="orbis-monitor-graph-period">
			<?php foreach ( $periods as $key => $label ) : ?>
				<a class="dropdown-item" href="<?php echo esc_url( add_query_arg( 'period', $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="card-body">
		<div id="orbis-monitor-graph" style="width: 100%; height: 300px;"></div>
	</div>
</div>
