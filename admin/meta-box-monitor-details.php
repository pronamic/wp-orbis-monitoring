<?php

wp_nonce_field( 'orbis_save_monitor_details', 'orbis_monitor_details_meta_box_nonce' );

$url      = get_post_meta( $post->ID, '_orbis_monitor_url', true );
$code     = get_post_meta( $post->ID, '_orbis_monitor_required_response_code', true );
$location = get_post_meta( $post->ID, '_orbis_monitor_required_location', true );
$string   = get_post_meta( $post->ID, '_orbis_monitor_required_string', true );

$curl = sprintf(
	'curl --head %s',
	escapeshellarg( $url )
);

?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_url"><?php esc_html_e( 'URL', 'orbis_monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_url" name="_orbis_monitor_url" value="<?php echo esc_attr( $url ); ?>" type="text" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_status_code"><?php esc_html_e( 'Required Response Code', 'orbis_monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_status_code" name="_orbis_monitor_required_response_code" value="<?php echo esc_attr( $code ); ?>" type="text" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_location"><?php esc_html_e( 'Required Location', 'orbis_monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_location" name="_orbis_monitor_required_location" value="<?php echo esc_attr( $location ); ?>" type="text" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_curl"><?php esc_html_e( 'cURL', 'orbis_monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_curl" name="_orbis_monitor_curl" value="<?php echo esc_attr( $curl ); ?>" readonly="readonly" type="text" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_string"><?php esc_html_e( 'Required String', 'orbis_monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_string" name="_orbis_monitor_required_string" value="<?php echo esc_attr( $string ); ?>"  type="text" class="regular-text" />
		</td>
	</tr>
</table>
