<?php

wp_nonce_field( 'orbis_save_monitor_details', 'orbis_monitor_details_meta_box_nonce' );

$url = get_post_meta( $post->ID, '_orbis_monitor_url', true );

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
</table>
