<?php
/**
 * Availability badge
 *
 * Expects `$monitor` (object or null).
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$availability = ( null === $monitor ) ? \Pronamic\Orbis\Monitoring\Availability::Unknown : $monitor->availability;

?>
<span class="badge <?php echo esc_attr( $availability->badge_class() ); ?>"><?php echo esc_html( $availability->label() ); ?></span>
<?php if ( null !== $monitor && \Pronamic\Orbis\Monitoring\Availability::Pending === $availability ) : ?>
	<small class="text-muted">
		<?php
		printf(
			/* translators: %d: number of failed checks in a row */
			esc_html( _n( '(%d failed check)', '(%d failed checks)', (int) $monitor->failure_count, 'orbis-monitoring' ) ),
			(int) $monitor->failure_count
		);
		?>
	</small>
<?php endif; ?>
<?php if ( null !== $monitor && null !== $monitor->availability_changed_at && \Pronamic\Orbis\Monitoring\Availability::Unknown !== $availability ) : ?>
	<br />
	<small class="text-muted">
		<?php
		printf(
			/* translators: %s: human readable time difference */
			esc_html__( 'for %s', 'orbis-monitoring' ),
			esc_html( human_time_diff( (int) strtotime( $monitor->availability_changed_at . ' UTC' ) ) )
		);
		?>
	</small>
<?php endif; ?>
