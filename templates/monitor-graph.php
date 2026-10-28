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
	'd'     => 'DATE( stats.started_at )',
	'm'     => 'EXTRACT( YEAR_MONTH FROM stats.started_at )',
	default => 'YEARWEEK( stats.started_at, 3 )',
};

$label_format = match ( $period ) {
	'm'     => 'M Y',
	default => 'j M',
};

$last_year = new DateTimeImmutable( '1 year ago midnight', wp_timezone() );

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The group by expression is one of a fixed set of values.
$response_times = $wpdb->get_results(
	$wpdb->prepare(
		<<<SQL
		SELECT
			SUM( stats.duration_total ) / SUM( stats.check_count ) AS avg_duration,
			MIN( stats.started_at ) AS monitored_date
		FROM
			$wpdb->orbis_monitor_response_stats AS stats
				INNER JOIN
			$wpdb->orbis_monitors AS monitor
					ON monitor.id = stats.monitor_id
		WHERE
			monitor.post_id = %d
				AND
			stats.started_at >= %s
		GROUP BY
			$group_by
		ORDER BY
			monitored_date ASC
		SQL,
		get_the_ID(),
		$last_year->format( 'Y-m-d H:i:s' )
	)
);
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$labels = [];
$data   = [];

foreach ( $response_times as $response ) {
	$monitored_date = new DateTimeImmutable( $response->monitored_date, wp_timezone() );

	$labels[] = wp_date( $label_format, $monitored_date->getTimestamp() );
	$data[]   = round( (float) $response->avg_duration, 3 );
}

$chart_config = [
	'type'    => 'line',
	'data'    => [
		'labels'   => $labels,
		'datasets' => [
			[
				'label'            => __( 'Average response time (seconds)', 'orbis-monitoring' ),
				'data'             => $data,
				'borderColor'      => '#007bff',
				'backgroundColor'  => '#007bff',
				'borderWidth'      => 3,
				'pointRadius'      => 3,
				'pointHoverRadius' => 5,
				'tension'          => 0.2,
			],
		],
	],
	'options' => [
		'responsive'          => true,
		'maintainAspectRatio' => false,
		'interaction'         => [
			'mode'      => 'index',
			'intersect' => false,
		],
		'plugins'             => [
			'legend' => [
				'display' => false,
			],
		],
		'scales'              => [
			'y' => [
				'beginAtZero' => true,
				'title'       => [
					'display' => true,
					'text'    => __( 'Seconds', 'orbis-monitoring' ),
				],
			],
		],
	],
];

wp_enqueue_script( 'orbis-monitoring-chartjs' );

wp_add_inline_script(
	'orbis-monitoring-chartjs',
	sprintf(
		'new Chart( document.getElementById( "orbis-monitor-graph" ), %s );',
		wp_json_encode( $chart_config )
	)
);

?>
<div class="card mb-3">
	<div class="card-header">
		<?php esc_html_e( 'Average Response Time', 'orbis-monitoring' ); ?>
		<div class="dropdown float-end">
			<button class="btn btn-light dropdown-toggle m-0 p-0" type="button" id="orbis-monitor-graph-period" data-bs-toggle="dropdown" aria-expanded="false"><?php echo esc_html( $periods[ $period ] ); ?></button>
			<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="orbis-monitor-graph-period">
				<?php foreach ( $periods as $key => $label ) : ?>
					<li><a class="dropdown-item" href="<?php echo esc_url( add_query_arg( 'period', $key ) ); ?>"><?php echo esc_html( $label ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<div class="card-body">
		<div style="position: relative; height: 300px;">
			<canvas id="orbis-monitor-graph" role="img" aria-label="<?php esc_attr_e( 'Average response time chart', 'orbis-monitoring' ); ?>"></canvas>
		</div>
	</div>
</div>
