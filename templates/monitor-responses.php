<?php
/**
 * Monitor responses
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

$amounts = [ 10, 50, 100 ];

$amount = isset( $_GET['amount'] ) ? absint( $_GET['amount'] ) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view filter.

if ( ! in_array( $amount, $amounts, true ) ) {
	$amount = 10;
}

$responses = $wpdb->get_results(
	$wpdb->prepare(
		<<<SQL
		SELECT
			*
		FROM
			$wpdb->orbis_monitor_responses
		WHERE
			post_id = %d
		ORDER BY
			monitored_date DESC
		LIMIT
			0, %d
		SQL,
		get_the_ID(),
		$amount
	)
);

?>
<div class="card mb-3">
	<div class="card-header">
		<?php

		echo esc_html(
			sprintf(
				/* translators: %d: number of responses. */
				__( 'Last %d Responses', 'orbis-monitoring' ),
				$amount
			)
		);

		?>
		<div class="dropdown float-end">
			<button class="btn btn-light dropdown-toggle m-0 p-0" type="button" id="orbis-monitor-responses-amount" data-bs-toggle="dropdown" aria-expanded="false"><?php echo esc_html( (string) $amount ); ?></button>
			<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="orbis-monitor-responses-amount">
				<?php foreach ( $amounts as $value ) : ?>
					<li><a class="dropdown-item" href="<?php echo esc_url( add_query_arg( 'amount', $value ) ); ?>"><?php echo esc_html( (string) $value ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-striped mb-0">
			<thead>
				<tr>
					<th class="border-top-0" scope="col"><?php esc_html_e( 'Date', 'orbis-monitoring' ); ?></th>
					<th class="border-top-0" scope="col"><?php esc_html_e( 'Duration', 'orbis-monitoring' ); ?></th>
					<th class="border-top-0" scope="col"><?php esc_html_e( 'Code', 'orbis-monitoring' ); ?></th>
					<th class="border-top-0" scope="col"><?php esc_html_e( 'Message', 'orbis-monitoring' ); ?></th>
					<th class="border-top-0" scope="col"><?php esc_html_e( 'Content Length', 'orbis-monitoring' ); ?></th>
				</tr>
			</thead>

			<tbody>

				<?php foreach ( $responses as $response ) : ?>

					<tr>
						<td>
							<?php echo esc_html( mysql2date( __( 'd-m-Y H:i', 'orbis-monitoring' ), $response->monitored_date ) ); ?>
						</td>
						<td>
							<?php echo empty( $response->duration ) ? '—' : esc_html( number_format_i18n( (float) $response->duration, 2 ) ); ?>
						</td>
						<td>
							<?php echo esc_html( $response->response_code ); ?>
						</td>
						<td>
							<?php echo esc_html( $response->response_message ); ?>
						</td>
						<td>
							<?php echo esc_html( $response->response_content_length ); ?>
						</td>
					</tr>

				<?php endforeach; ?>

			</tbody>
		</table>
	</div>
</div>
