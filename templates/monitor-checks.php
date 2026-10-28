<?php
/**
 * Monitor checks
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checks_limit = 100;

$checks = $response_controller->get_recent_checks( (int) get_the_ID(), $checks_limit );

$succeeded_count = count(
	array_filter(
		$checks,
		fn( $check ) => '1' === (string) $check->check_succeeded
	)
);

?>
<div class="card mb-3">
	<div class="card-header">
		<?php

		echo esc_html(
			sprintf(
				/* translators: %d: number of checks. */
				__( 'Last %d Checks', 'orbis-monitoring' ),
				$checks_limit
			)
		);

		?>

		<?php if ( [] !== $checks ) : ?>
			<span class="float-end text-muted">
				<?php

				echo esc_html(
					sprintf(
						/* translators: %s: percentage of succeeded checks. */
						__( '%s%% succeeded', 'orbis-monitoring' ),
						number_format_i18n( $succeeded_count / count( $checks ) * 100, 1 )
					)
				);

				?>
			</span>
		<?php endif; ?>
	</div>
	<div class="card-body">
		<?php require __DIR__ . '/monitor-checks-bar.php'; ?>
	</div>
</div>
