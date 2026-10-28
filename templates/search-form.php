<?php
/**
 * Search form
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_availability = get_query_var( 'orbis_monitor_availability' );

?>
<div class="card-body">
	<form method="get" action="<?php echo esc_url( get_post_type_archive_link( 'orbis_monitor' ) ); ?>">
		<div class="d-flex justify-content-between gap-2">
			<div class="d-flex align-items-center gap-2">
				<label for="orbis_search_query" class="visually-hidden"><?php esc_html_e( 'Search', 'orbis-monitoring' ); ?></label>

				<input id="orbis_search_query" type="search" class="form-control" name="s" placeholder="<?php esc_attr_e( 'Search', 'orbis-monitoring' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">

				<button type="submit" class="btn btn-secondary"><?php esc_html_e( 'Search', 'orbis-monitoring' ); ?></button>
			</div>

			<div class="d-flex align-items-center gap-2">
				<label for="orbis_monitor_availability" class="visually-hidden"><?php esc_html_e( 'Status', 'orbis-monitoring' ); ?></label>

				<select id="orbis_monitor_availability" name="orbis_monitor_availability" class="form-select">
					<option value=""><?php esc_html_e( '— Select Status —', 'orbis-monitoring' ); ?></option>

					<?php foreach ( \Pronamic\Orbis\Monitoring\Availability::cases() as $availability ) : ?>

						<option value="<?php echo esc_attr( $availability->value ); ?>" <?php selected( $availability->value, $current_availability ); ?>><?php echo esc_html( $availability->label() ); ?></option>

					<?php endforeach; ?>
				</select>

				<button class="btn btn-secondary" type="submit"><?php esc_html_e( 'Filter', 'orbis-monitoring' ); ?></button>
			</div>
		</div>
	</form>
</div>
