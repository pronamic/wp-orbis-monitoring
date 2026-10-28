<?php
/**
 * Monitor status badge
 *
 * Expects `$monitor_status` (object or null) and `$incident_controller`.
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_key = ( null === $monitor_status ) ? 'unknown' : $monitor_status->status;

$badge_class = match ( $status_key ) {
	'up'      => 'text-bg-success',
	'pending' => 'text-bg-warning',
	'down'    => 'text-bg-danger',
	default   => 'text-bg-secondary',
};

?>
<span class="badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $incident_controller->get_status_label( $status_key ) ); ?></span>
<?php if ( null !== $monitor_status && 'pending' === $monitor_status->status ) : ?>
	<small class="text-muted">
		<?php
		printf(
			/* translators: %d: number of failed checks in a row */
			esc_html( _n( '(%d failed check)', '(%d failed checks)', (int) $monitor_status->failure_count, 'orbis-monitoring' ) ),
			(int) $monitor_status->failure_count
		);
		?>
	</small>
<?php endif; ?>
<?php if ( null !== $monitor_status && null !== $monitor_status->status_changed_at && 'unknown' !== $monitor_status->status ) : ?>
	<br />
	<small class="text-muted">
		<?php
		printf(
			/* translators: %s: human readable time difference */
			esc_html__( 'for %s', 'orbis-monitoring' ),
			esc_html( human_time_diff( (int) strtotime( $monitor_status->status_changed_at . ' UTC' ) ) )
		);
		?>
	</small>
<?php endif; ?>
