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

// The summaries are per UTC hour, they are grouped per day, week or month in the site timezone.
$group_format = match ( $period ) {
	'd'     => 'Y-m-d',
	'm'     => 'Y-m',
	default => 'o-W',
};

$label_format = match ( $period ) {
	'm'     => 'M Y',
	default => 'j M',
};

$last_year = new DateTimeImmutable( '1 year ago midnight', wp_timezone() );

$stats = $wpdb->get_results(
	$wpdb->prepare(
		<<<SQL
		SELECT
			stats.started_at,
			stats.check_count,
			stats.duration_total
		FROM
			$wpdb->orbis_monitor_response_stats AS stats
				INNER JOIN
			$wpdb->orbis_monitors AS monitor
					ON monitor.id = stats.monitor_id
		WHERE
			monitor.post_id = %d
				AND
			stats.started_at >= %s
		ORDER BY
			stats.started_at ASC
		SQL,
		get_the_ID(),
		$last_year->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
	)
);

$groups = [];

foreach ( $stats as $stat ) {
	$timestamp = (int) strtotime( $stat->started_at . ' UTC' );

	$key = wp_date( $group_format, $timestamp );

	$groups[ $key ] ??= [
		'timestamp'      => $timestamp,
		'check_count'    => 0,
		'duration_total' => 0.0,
	];

	$groups[ $key ]['check_count']    += (int) $stat->check_count;
	$groups[ $key ]['duration_total'] += (float) $stat->duration_total;
}

$labels = [];
$data   = [];

foreach ( $groups as $group ) {
	$labels[] = wp_date( $label_format, $group['timestamp'] );
	$data[]   = ( $group['check_count'] > 0 ) ? round( $group['duration_total'] / $group['check_count'], 3 ) : null;
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
