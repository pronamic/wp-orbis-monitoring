<?php

global $post;

$required_string = get_post_meta( $post->ID, '_orbis_monitor_check_required_string', true );

 ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_string"><?php esc_html_e( 'Required String', 'orbis_monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_string" name="_orbis_monitor_check_required_string" value="<?php echo esc_html( $required_string ); ?>" type="text" class="regular-text" />
		</td>
		<input type="hidden" name="_orbis_monitor_check" value="0">
	</tr>
</table>