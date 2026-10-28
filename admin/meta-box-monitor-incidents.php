<?php
/**
 * Meta box monitor incidents
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

$incidents = $incident_controller->get_incidents( $post->ID, 10 );

if ( [] === $incidents ) : ?>

	<p><?php esc_html_e( 'No incidents.', 'orbis-monitoring' ); ?></p>

	<?php

	return;
endif;

?>
<table class="widefat table">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Started', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Resolved', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Duration', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Code', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Message', 'orbis-monitoring' ); ?></th>
		</tr>
	</thead>

	<tbody>

		<?php foreach ( $incidents as $incident ) : ?>

			<tr>
				<td>
					<?php echo esc_html( get_date_from_gmt( $incident->started_at ) ); ?>
				</td>
				<td>
					<?php echo ( null === $incident->resolved_at ) ? '🔴 ' . esc_html__( 'Ongoing', 'orbis-monitoring' ) : esc_html( get_date_from_gmt( $incident->resolved_at ) ); ?>
				</td>
				<td>
					<?php echo esc_html( $incident_controller->get_incident_duration( $incident ) ); ?>
				</td>
				<td>
					<?php echo empty( $incident->response_code ) ? '—' : esc_html( $incident->response_code ); ?>
				</td>
				<td>
					<?php echo empty( $incident->response_message ) ? '—' : esc_html( $incident->response_message ); ?>
				</td>
			</tr>

		<?php endforeach; ?>

	</tbody>
</table>
