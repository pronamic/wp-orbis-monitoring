<?php
/**
 * Monitor checks bar
 *
 * Shows one pill per check, the newest check on the right.
 *
 * Expects `$checks` (objects, oldest first), `$checks_limit` (int) and
 * optionally `$checks_bar_class` (string).
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'orbis-monitoring' );
wp_enqueue_script( 'orbis-monitoring-tooltips' );

$checks_bar_class = $checks_bar_class ?? '';

$empty_count = max( 0, $checks_limit - count( $checks ) );

?>
<div class="orbis-monitor-checks <?php echo esc_attr( $checks_bar_class ); ?>" role="img" aria-label="<?php esc_attr_e( 'Recent checks', 'orbis-monitoring' ); ?>">
	<?php for ( $i = 0; $i < $empty_count; $i++ ) : ?>
		<span class="orbis-monitor-check"></span>
	<?php endfor; ?>

	<?php

	foreach ( $checks as $check ) :
		$check_succeeded = ( '1' === (string) $check->check_succeeded );

		$check_message = empty( $check->error_message ) ? get_status_header_desc( (int) $check->response_code ) : $check->error_message;

		$check_title = implode(
			"\n",
			array_filter(
				[
					$check_succeeded ? __( 'Check succeeded', 'orbis-monitoring' ) : __( 'Check failed', 'orbis-monitoring' ),
					mysql2date( __( 'd-m-Y H:i:s', 'orbis-monitoring' ), $check->monitored_date ),
					trim( $check->response_code . ' ' . $check_message ),
					empty( $check->duration ) ? '' : sprintf(
						/* translators: %s: duration in seconds */
						__( '%s seconds', 'orbis-monitoring' ),
						number_format_i18n( (float) $check->duration, 2 )
					),
				]
			)
		);

		?>
		<span class="orbis-monitor-check <?php echo esc_attr( $check_succeeded ? 'orbis-monitor-check--succeeded' : 'orbis-monitor-check--failed' ); ?>" data-bs-toggle="tooltip" data-bs-custom-class="orbis-monitor-check-tooltip" title="<?php echo esc_attr( $check_title ); ?>"></span>
	<?php endforeach; ?>
</div>
