<?php

global $wpdb;

$responses = $wpdb->get_results( $wpdb->prepare( "
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
", $post->ID ) );

?>
<table class="widefat table">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'ID', 'orbis_monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Date', 'orbis_monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Duration', 'orbis_monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Code', 'orbis_monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Message', 'orbis_monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Content Length', 'orbis_monitoring' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Content Type', 'orbis_monitoring' ); ?></th>
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
