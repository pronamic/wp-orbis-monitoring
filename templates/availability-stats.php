<?php
/**
 * Availability stats
 *
 * Shows the number of monitors per availability of the monitored URL, each linking to the filtered archive.
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

use Pronamic\Orbis\Monitoring\Availability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$availability_counts  = \Pronamic\Orbis\Monitoring\Plugin::instance()->incident_controller->get_availability_counts();
$current_availability = get_query_var( 'orbis_monitor_availability' );
$archive_url          = get_post_type_archive_link( 'orbis_monitor' );

if ( '' !== get_search_query() ) {
	$archive_url = add_query_arg( 's', rawurlencode( get_search_query() ), $archive_url );
}

$stats = [
	[
		'label'  => __( 'All', 'orbis-monitoring' ),
		'count'  => array_sum( $availability_counts ),
		'color'  => 'primary',
		'url'    => $archive_url,
		'active' => '' === $current_availability,
	],
];

foreach ( [ Availability::Online, Availability::Pending, Availability::Offline, Availability::Unknown ] as $availability ) {
	$stats[] = [
		'label'  => $availability->label(),
		'count'  => $availability_counts[ $availability->value ],
		'color'  => $availability->color(),
		'url'    => add_query_arg( 'orbis_monitor_availability', $availability->value, $archive_url ),
		'active' => $availability->value === $current_availability,
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
