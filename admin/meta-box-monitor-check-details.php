<?php

global $post;

$required_string = get_post_meta( $post->ID, '_orbis_monitor_check_required_string', true );
$should_contain  = get_post_meta( $post->ID, '_orbis_monitor_check_should_contain', true );

?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_string"><?php esc_html_e( 'String To Check', 'orbis_monitoring' ); ?></label>
		</th>
		<td>
			<select name="_orbis_monitor_check_should_contain">
				<option <?php selected( $should_contain ); ?> value="1"><?php esc_html_e( 'Should Contain', 'orbis_monitoring' ); ?></option>
				<option <?php selected( ! $should_contain ); ?> value="0"><?php esc_html_e( "Shouldn't Contain", 'orbis_monitoring' ); ?></option>
			</select>
			<input style='height: 28px;' id="orbis_monitor_string" name="_orbis_monitor_check_required_string" value="<?php echo esc_html( $required_string ); ?>" type="text" class="regular-text" />
		</td>
		<td>
			<p class="description">
				<?php esc_html_e( 'This field uses regular expressions to search.', 'orbis_monitoring' ); ?>
			</p>
		</td>
		<input type="hidden" name="_orbis_monitor_check" value="0">
	</tr>
</table>
