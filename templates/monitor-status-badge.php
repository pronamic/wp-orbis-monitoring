<?php
/**
 * Monitor status badge
 *
 * Expects `$monitor_status` (object or null).
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$badge_status = ( null === $monitor_status ) ? \Pronamic\Orbis\Monitoring\MonitorStatus::Unknown : $monitor_status->status;

?>
<span class="badge <?php echo esc_attr( $badge_status->badge_class() ); ?>"><?php echo esc_html( $badge_status->label() ); ?></span>
<?php if ( null !== $monitor_status && \Pronamic\Orbis\Monitoring\MonitorStatus::Pending === $badge_status ) : ?>
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
<?php if ( null !== $monitor_status && null !== $monitor_status->status_changed_at && \Pronamic\Orbis\Monitoring\MonitorStatus::Unknown !== $badge_status ) : ?>
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
