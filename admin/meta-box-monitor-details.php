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
$headers  = get_post_meta( $post->ID, '_orbis_monitor_header', false );

$curl = 'curl --head';

foreach ( $headers as $header ) {
	$curl .= ' -H ' . escapeshellarg( $header );
}

$curl .= ' ' . escapeshellarg( $url );

$header_rows = [];

foreach ( $headers as $header ) {
	$parts = explode( ':', $header, 2 );

	$header_rows[] = [
		'name'  => trim( $parts[0] ),
		'value' => trim( $parts[1] ?? '' ),
	];
}

// Always offer an empty row, so a header can be added without JavaScript.
$header_rows[] = [
	'name'  => '',
	'value' => '',
];

$header_row = function ( $index, $name, $value ) {
	?>
	<tr>
		<td class="name">
			<input name="_orbis_monitor_headers[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" type="text" aria-label="<?php esc_attr_e( 'Name', 'orbis-monitoring' ); ?>" />
		</td>
		<td class="value">
			<input name="_orbis_monitor_headers[<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $value ); ?>" type="text" aria-label="<?php esc_attr_e( 'Value', 'orbis-monitoring' ); ?>" />
		</td>
		<td>
			<button type="button" class="button-link orbis-monitor-header-remove" title="<?php esc_attr_e( 'Remove', 'orbis-monitoring' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'orbis-monitoring' ); ?></span></button>
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
			<style>
				#orbis-monitor-headers table {
					border-collapse: collapse;
				}

				#orbis-monitor-headers th,
				#orbis-monitor-headers td {
					width: auto;
					padding: 0 8px 6px 0;
					vertical-align: middle;
				}

				#orbis-monitor-headers thead th {
					padding-bottom: 4px;
					color: #646970;
					font-size: 12px;
					font-weight: 400;
				}

				#orbis-monitor-headers .name input {
					width: 14em;
				}

				#orbis-monitor-headers .value input {
					width: 25em;
				}

				#orbis-monitor-headers .orbis-monitor-header-remove {
					color: #a7aaad;
					text-decoration: none;
				}

				#orbis-monitor-headers .orbis-monitor-header-remove:hover,
				#orbis-monitor-headers .orbis-monitor-header-remove:focus {
					color: #d63638;
				}

				#orbis-monitor-headers .orbis-monitor-header-add {
					text-decoration: none;
				}

				#orbis-monitor-headers .orbis-monitor-header-add .dashicons {
					font-size: 16px;
					width: 16px;
					height: 16px;
					vertical-align: text-bottom;
				}
			</style>

			<div id="orbis-monitor-headers">
				<table>
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

				<p style="margin-top: 0;">
					<button type="button" class="button-link orbis-monitor-header-add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add Header', 'orbis-monitoring' ); ?></button>
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
