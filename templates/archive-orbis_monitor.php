<?php
/**
 * Archive monitors
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

global $wp_query;

$incident_controller = \Pronamic\Orbis\Monitoring\Plugin::instance()->incident_controller;
$response_controller = \Pronamic\Orbis\Monitoring\Plugin::instance()->response_controller;

$checks_limit     = 30;
$checks_bar_class = 'orbis-monitor-checks--sm';

$monitors = $incident_controller->get_monitors_by_post_ids( wp_list_pluck( $wp_query->posts, 'ID' ) );

$checks_by_monitor = $response_controller->get_recent_checks_by_monitor_ids( wp_list_pluck( $monitors, 'id' ), $checks_limit );

?>
<?php require __DIR__ . '/status-stats.php'; ?>

<div class="card">
	<?php require __DIR__ . '/search-form.php'; ?>

	<?php if ( have_posts() ) : ?>

		<div class="table-responsive">
			<table class="table table-striped table-condense table-hover">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Status', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Recent checks', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Title', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'URL', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Required response code', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Last response code', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Last response message', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Last response duration', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Last time checked', 'orbis-monitoring' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php

					while ( have_posts() ) :
						the_post();

						$url           = get_post_meta( get_the_ID(), '_orbis_monitor_url', true );
						$required_code = get_post_meta( get_the_ID(), '_orbis_monitor_required_response_code', true );
						$code          = get_post_meta( get_the_ID(), '_orbis_monitor_response_code', true );
						$error_message = get_post_meta( get_the_ID(), '_orbis_monitor_error_message', true );
						$duration      = get_post_meta( get_the_ID(), '_orbis_monitor_duration', true );
						$monitor       = $monitors[ get_the_ID() ] ?? null;
						$checks        = ( null === $monitor ) ? [] : ( $checks_by_monitor[ (int) $monitor->id ] ?? [] );

						?>
						<tr id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<td>
								<?php require __DIR__ . '/status-badge.php'; ?>
							</td>
							<td>
								<?php require __DIR__ . '/monitor-checks-bar.php'; ?>
							</td>
							<td>
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>

								<?php get_template_part( 'templates/table-cell-comments' ); ?>
							</td>
							<td>
								<?php if ( empty( $url ) ) : ?>
									—
								<?php else : ?>
									<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $required_code ); ?>
							</td>
							<td>
								<?php echo esc_html( $code ); ?>
							</td>
							<td>
								<?php echo esc_html( empty( $error_message ) ? get_status_header_desc( (int) $code ) : $error_message ); ?>
							</td>
							<td>
								<?php echo empty( $duration ) ? '—' : esc_html( number_format_i18n( (float) $duration, 2 ) ); ?>
							</td>
							<td>
								<?php echo ( null === $monitor || null === $monitor->checked_at ) ? '—' : esc_html( wp_date( __( 'd-m-Y H:i', 'orbis-monitoring' ), (int) strtotime( $monitor->checked_at . ' UTC' ) ) ); ?>
							</td>
							<td>
								<?php get_template_part( 'templates/table-cell-actions' ); ?>
							</td>
						</tr>

					<?php endwhile; ?>
				</tbody>
			</table>
		</div>

	<?php else : ?>

		<?php get_template_part( 'templates/content-none' ); ?>

	<?php endif; ?>
</div>

<?php

if ( function_exists( 'orbis_content_nav' ) ) {
	orbis_content_nav();
} else {
	the_posts_pagination();
}

get_footer();
