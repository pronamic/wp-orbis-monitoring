<?php
/**
 * Monitor details
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$url      = get_post_meta( get_the_ID(), '_orbis_monitor_url', true );
$code     = get_post_meta( get_the_ID(), '_orbis_monitor_required_response_code', true );
$location = get_post_meta( get_the_ID(), '_orbis_monitor_required_location', true );

$monitor_status = $incident_controller->get_status( (int) get_the_ID() );
$incident       = ( null !== $monitor_status && \Pronamic\Orbis\Monitoring\MonitorStatus::Offline === $monitor_status->status ) ? $incident_controller->get_open_incident( (int) $monitor_status->id ) : null;

?>
<div class="card mb-3">
	<div class="card-header"><?php esc_html_e( 'Monitor Details', 'orbis-monitoring' ); ?></div>
	<div class="card-body">
		<div class="content">
			<dl>
				<dt><?php esc_html_e( 'Status', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php require __DIR__ . '/monitor-status-badge.php'; ?>
				</dd>

				<?php if ( null !== $incident ) : ?>
					<dt><?php esc_html_e( 'Current Incident', 'orbis-monitoring' ); ?></dt>
					<dd>
						<?php

						printf(
							/* translators: %s: date the incident started */
							esc_html__( 'Since %s', 'orbis-monitoring' ),
							esc_html( wp_date( __( 'd-m-Y H:i', 'orbis-monitoring' ), (int) strtotime( $incident->started_at . ' UTC' ) ) )
						);

						if ( ! empty( $incident->response_code ) || ! empty( $incident->response_message ) ) {
							echo '<br />';
							echo esc_html( trim( $incident->response_code . ' ' . $incident->response_message ) );
						}

						?>
					</dd>
				<?php endif; ?>

				<dt><?php esc_html_e( 'URL', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php if ( empty( $url ) ) : ?>
						—
					<?php else : ?>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a>
					<?php endif; ?>
				</dd>

				<dt><?php esc_html_e( 'Required Response Code', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php echo empty( $code ) ? '—' : esc_html( $code ); ?>
				</dd>

				<dt><?php esc_html_e( 'Required Location', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php if ( empty( $location ) ) : ?>
						—
					<?php else : ?>
						<a href="<?php echo esc_url( $location ); ?>"><?php echo esc_html( $location ); ?></a>
					<?php endif; ?>
				</dd>
			</dl>
		</div>
	</div>
</div>
