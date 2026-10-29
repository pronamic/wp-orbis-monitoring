<?php
/**
 * Status stats
 *
 * Shows the number of monitors per status, each linking to the filtered archive.
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

use Pronamic\Orbis\Monitoring\Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_counts  = \Pronamic\Orbis\Monitoring\Plugin::instance()->incident_controller->get_status_counts();
$current_status = get_query_var( 'orbis_monitor_status' );
$archive_url    = get_post_type_archive_link( 'orbis_monitor' );

if ( '' !== get_search_query() ) {
	$archive_url = add_query_arg( 's', rawurlencode( get_search_query() ), $archive_url );
}

$stats = [
	[
		'label'  => __( 'All', 'orbis-monitoring' ),
		'count'  => array_sum( $status_counts ),
		'color'  => 'primary',
		'url'    => $archive_url,
		'active' => '' === $current_status,
	],
];

foreach ( [ Status::Ok, Status::Pending, Status::Alert, Status::Unknown ] as $monitor_status ) {
	$stats[] = [
		'label'  => $monitor_status->label(),
		'count'  => $status_counts[ $monitor_status->value ],
		'color'  => $monitor_status->color(),
		'url'    => add_query_arg( 'orbis_monitor_status', $monitor_status->value, $archive_url ),
		'active' => $monitor_status->value === $current_status,
	];
}

?>
<div class="row row-cols-2 row-cols-md-5 g-3 mb-4">
	<?php foreach ( $stats as $stat ) : ?>

		<div class="col">
			<div class="card h-100 text-center <?php echo esc_attr( $stat['active'] ? 'border-2 border-' . $stat['color'] : '' ); ?>">
				<div class="card-body">
					<a href="<?php echo esc_url( $stat['url'] ); ?>" class="stretched-link text-decoration-none text-body-secondary" <?php echo $stat['active'] ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( $stat['label'] ); ?>
					</a>

					<div class="fs-2 fw-bold text-<?php echo esc_attr( $stat['color'] ); ?>">
						<?php echo esc_html( number_format_i18n( $stat['count'] ) ); ?>
					</div>
				</div>
			</div>
		</div>

	<?php endforeach; ?>
</div>
