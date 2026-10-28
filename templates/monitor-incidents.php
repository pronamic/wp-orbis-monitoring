<?php
/**
 * Monitor incidents
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$amounts = [ 10, 50, 100 ];

$amount = isset( $_GET['incidents'] ) ? absint( $_GET['incidents'] ) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view filter.

if ( ! in_array( $amount, $amounts, true ) ) {
	$amount = 10;
}

$incidents = $incident_controller->get_incidents( (int) get_the_ID(), $amount );

?>
<div class="card mb-3">
	<div class="card-header">
		<?php esc_html_e( 'Incidents', 'orbis-monitoring' ); ?>
		<div class="dropdown float-end">
			<button class="btn btn-light dropdown-toggle m-0 p-0" type="button" id="orbis-monitor-incidents-amount" data-bs-toggle="dropdown" aria-expanded="false"><?php echo esc_html( (string) $amount ); ?></button>
			<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="orbis-monitor-incidents-amount">
				<?php foreach ( $amounts as $value ) : ?>
					<li><a class="dropdown-item" href="<?php echo esc_url( add_query_arg( 'incidents', $value ) ); ?>"><?php echo esc_html( (string) $value ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<?php if ( [] === $incidents ) : ?>

		<div class="card-body">
			<p class="mb-0 text-muted"><?php esc_html_e( 'No incidents.', 'orbis-monitoring' ); ?></p>
		</div>

	<?php else : ?>

		<div class="table-responsive">
			<table class="table table-striped mb-0">
				<thead>
					<tr>
						<th class="border-top-0" scope="col"><?php esc_html_e( 'Started', 'orbis-monitoring' ); ?></th>
						<th class="border-top-0" scope="col"><?php esc_html_e( 'Resolved', 'orbis-monitoring' ); ?></th>
						<th class="border-top-0" scope="col"><?php esc_html_e( 'Duration', 'orbis-monitoring' ); ?></th>
						<th class="border-top-0" scope="col"><?php esc_html_e( 'Code', 'orbis-monitoring' ); ?></th>
						<th class="border-top-0" scope="col"><?php esc_html_e( 'Message', 'orbis-monitoring' ); ?></th>
					</tr>
				</thead>

				<tbody>

					<?php foreach ( $incidents as $incident ) : ?>

						<tr>
							<td>
								<?php echo esc_html( wp_date( __( 'd-m-Y H:i', 'orbis-monitoring' ), (int) strtotime( $incident->started_at . ' UTC' ) ) ); ?>
							</td>
							<td>
								<?php if ( null === $incident->resolved_at ) : ?>
									<span class="badge text-bg-danger"><?php esc_html_e( 'Ongoing', 'orbis-monitoring' ); ?></span>
								<?php else : ?>
									<?php echo esc_html( wp_date( __( 'd-m-Y H:i', 'orbis-monitoring' ), (int) strtotime( $incident->resolved_at . ' UTC' ) ) ); ?>
								<?php endif; ?>
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
		</div>

	<?php endif; ?>
</div>
