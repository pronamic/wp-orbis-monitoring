<?php
/**
 * Meta box monitor details
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

wp_nonce_field( 'orbis_save_monitor_details', 'orbis_monitor_details_meta_box_nonce' );

$url      = get_post_meta( $post->ID, '_orbis_monitor_url', true );
$code     = get_post_meta( $post->ID, '_orbis_monitor_required_response_code', true );
$location = get_post_meta( $post->ID, '_orbis_monitor_required_location', true );
$headers  = get_post_meta( $post->ID, '_orbis_monitor_headers', true );
$headers  = is_array( $headers ) ? array_values( $headers ) : [];

$curl = 'curl --head';

foreach ( $headers as $header ) {
	$curl .= ' -H ' . escapeshellarg( $header['name'] . ': ' . $header['value'] );
}

$curl .= ' ' . escapeshellarg( $url );

// Always offer an empty row, so a header can be added without JavaScript.
$header_rows   = $headers;
$header_rows[] = [
	'name'  => '',
	'value' => '',
];

$header_row = function ( $index, $name, $value ) {
	?>
	<tr>
		<td>
			<input name="_orbis_monitor_headers[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" type="text" class="widefat" placeholder="Authorization" aria-label="<?php esc_attr_e( 'Name', 'orbis-monitoring' ); ?>" />
		</td>
		<td>
			<input name="_orbis_monitor_headers[<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $value ); ?>" type="text" class="widefat" aria-label="<?php esc_attr_e( 'Value', 'orbis-monitoring' ); ?>" />
		</td>
		<td>
			<button type="button" class="button-link button-link-delete orbis-monitor-header-remove"><?php esc_html_e( 'Remove', 'orbis-monitoring' ); ?></button>
		</td>
	</tr>
	<?php
};

?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_url"><?php esc_html_e( 'URL', 'orbis-monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_url" name="_orbis_monitor_url" value="<?php echo esc_attr( $url ); ?>" type="text" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_status_code"><?php esc_html_e( 'Required Response Code', 'orbis-monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_status_code" name="_orbis_monitor_required_response_code" value="<?php echo esc_attr( $code ); ?>" type="text" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_location"><?php esc_html_e( 'Required Location', 'orbis-monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_location" name="_orbis_monitor_required_location" value="<?php echo esc_attr( $location ); ?>" type="text" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<?php esc_html_e( 'HTTP Headers', 'orbis-monitoring' ); ?>
		</th>
		<td>
			<div id="orbis-monitor-headers">
				<table class="widefat striped" style="max-width: 50em;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'orbis-monitoring' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Value', 'orbis-monitoring' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'orbis-monitoring' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php

						foreach ( $header_rows as $index => $header ) {
							$header_row( $index, $header['name'], $header['value'] );
						}

						?>
					</tbody>
				</table>

				<template>
					<?php $header_row( '__index__', '', '' ); ?>
				</template>

				<p>
					<button type="button" class="button orbis-monitor-header-add"><?php esc_html_e( 'Add Header', 'orbis-monitoring' ); ?></button>
				</p>

				<p class="description">
					<?php esc_html_e( 'Headers that are sent with every check, for example Authorization. A User-Agent header replaces the default user agent.', 'orbis-monitoring' ); ?>
				</p>
			</div>

			<script>
				( function () {
					const container = document.getElementById( 'orbis-monitor-headers' );
					const tbody     = container.querySelector( 'tbody' );
					const template  = container.querySelector( 'template' );

					let index = <?php echo (int) count( $header_rows ); ?>;

					container.addEventListener( 'click', function ( event ) {
						if ( event.target.closest( '.orbis-monitor-header-add' ) ) {
							tbody.insertAdjacentHTML( 'beforeend', template.innerHTML.replaceAll( '__index__', index++ ) );
							tbody.lastElementChild.querySelector( 'input' ).focus();
						}

						if ( event.target.closest( '.orbis-monitor-header-remove' ) ) {
							event.target.closest( 'tr' ).remove();
						}
					} );
				} )();
			</script>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label for="orbis_monitor_curl"><?php esc_html_e( 'cURL', 'orbis-monitoring' ); ?></label>
		</th>
		<td>
			<input id="orbis_monitor_curl" name="_orbis_monitor_curl" value="<?php echo esc_attr( $curl ); ?>" readonly="readonly" type="text" class="large-text code" />
		</td>
	</tr>
</table>
