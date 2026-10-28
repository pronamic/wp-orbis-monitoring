<?php
/**
 * Meta box monitor responses
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

global $wpdb;

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
			0, 10
		SQL,
		$post->ID
	)
);

?>
<table class="widefat table">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'ID', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Date', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Duration', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Code', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Message', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Content Length', 'orbis-monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Content Type', 'orbis-monitoring' ); ?></th>
		</tr>
	</thead>

	<tbody>

		<?php foreach ( $responses as $response ) : ?>

			<tr>
				<td>
					<?php echo esc_html( $response->response_id ); ?>
				</td>
				<td>
					<?php echo esc_html( $response->monitored_date ); ?>
				</td>
				<td>
					<?php

					if ( empty( $response->duration ) ) {
						echo '—';
					} else {
						echo esc_html( number_format_i18n( $response->duration, 6 ) );
					}

					?>
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
				<td>
					<?php echo esc_html( $response->response_content_type ); ?>
				</td>
			</tr>

		<?php endforeach; ?>

	</tbody>
</table>
