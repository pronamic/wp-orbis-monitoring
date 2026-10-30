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
$headers  = get_post_meta( get_the_ID(), '_orbis_monitor_headers', true );
$headers  = is_array( $headers ) ? $headers : [];

$monitor  = $incident_controller->get_monitor_by_post_id( (int) get_the_ID() );
$incident = ( null !== $monitor && \Pronamic\Orbis\Monitoring\Status::Alert === $monitor->status ) ? $incident_controller->get_open_incident( (int) $monitor->id ) : null;

?>
<div class="card mb-3">
	<div class="card-header"><?php esc_html_e( 'Monitor Details', 'orbis-monitoring' ); ?></div>
	<div class="card-body">
		<div class="content">
			<dl>
				<dt><?php esc_html_e( 'Status', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php require __DIR__ . '/status-badge.php'; ?>
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

						$message = empty( $incident->error_message ) ? get_status_header_desc( (int) $incident->response_code ) : $incident->error_message;

						if ( ! empty( $incident->response_code ) || ! empty( $message ) ) {
							echo '<br />';
							echo esc_html( trim( $incident->response_code . ' ' . $message ) );
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

				<dt><?php esc_html_e( 'HTTP Headers', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php if ( empty( $headers ) ) : ?>
						—
					<?php else : ?>
						<?php foreach ( $headers as $header ) : ?>
							<code><?php echo esc_html( $header['name'] . ': ' . $header['value'] ); ?></code><br />
						<?php endforeach; ?>
					<?php endif; ?>
				</dd>
			</dl>
		</div>
	</div>
</div>
