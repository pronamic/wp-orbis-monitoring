<?php
/**
 * Monitor details
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$url      = get_post_meta( get_the_ID(), '_orbis_monitor_url', true );
$code     = get_post_meta( get_the_ID(), '_orbis_monitor_required_response_code', true );
$location = get_post_meta( get_the_ID(), '_orbis_monitor_required_location', true );

?>
<div class="card mb-3">
	<div class="card-header"><?php esc_html_e( 'Monitor Details', 'orbis-monitoring' ); ?></div>
	<div class="card-body">
		<div class="content">
			<dl>
				<dt><?php esc_html_e( 'URL', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php if ( empty( $url ) ) : ?>
						—
					<?php else : ?>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a>
					<?php endif; ?>
				</dd>

				<dt><?php esc_html_e( 'Required Response Code', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php echo empty( $code ) ? '—' : esc_html( $code ); ?>
				</dd>

				<dt><?php esc_html_e( 'Required Location', 'orbis-monitoring' ); ?></dt>
				<dd>
					<?php if ( empty( $location ) ) : ?>
						—
					<?php else : ?>
						<a href="<?php echo esc_url( $location ); ?>"><?php echo esc_html( $location ); ?></a>
					<?php endif; ?>
				</dd>
			</dl>
		</div>
	</div>
</div>
