<?php
/**
 * Archive monitors
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<div class="card">
	<div class="card-block">
		<?php get_template_part( 'templates/search_form' ); ?>
	</div>

	<?php if ( have_posts() ) : ?>

		<div class="table-responsive">
			<table class="table table-striped table-condense table-hover">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'URL', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Required response code', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Last response code', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Last response message', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Last response duration', 'orbis-monitoring' ); ?></th>
						<th><?php esc_html_e( 'Last time checked', 'orbis-monitoring' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php

					while ( have_posts() ) :
						the_post();

						$url           = get_post_meta( get_the_ID(), '_orbis_monitor_url', true );
						$required_code = get_post_meta( get_the_ID(), '_orbis_monitor_required_response_code', true );
						$code          = get_post_meta( get_the_ID(), '_orbis_monitor_response_code', true );
						$message       = get_post_meta( get_the_ID(), '_orbis_monitor_response_message', true );
						$duration      = get_post_meta( get_the_ID(), '_orbis_monitor_duration', true );
						$time          = get_post_meta( get_the_ID(), '_orbis_monitor_response_date', true );

						?>
						<tr id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<td>
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>

								<?php get_template_part( 'templates/table-cell-comments' ); ?>
							</td>
							<td>
								<?php if ( empty( $url ) ) : ?>
									—
								<?php else : ?>
									<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $required_code ); ?>
							</td>
							<td>
								<?php echo esc_html( $code ); ?>
							</td>
							<td>
								<?php echo esc_html( $message ); ?>
							</td>
							<td>
								<?php echo empty( $duration ) ? '—' : esc_html( number_format_i18n( (float) $duration, 2 ) ); ?>
							</td>
							<td>
								<?php echo empty( $time ) ? '—' : esc_html( date_i18n( __( 'd-m-Y H:i', 'orbis-monitoring' ), (int) $time ) ); ?>
							</td>
							<td>
								<?php get_template_part( 'templates/table-cell-actions' ); ?>
							</td>
						</tr>

					<?php endwhile; ?>
				</tbody>
			</table>
		</div>

	<?php else : ?>

		<?php get_template_part( 'templates/content-none' ); ?>

	<?php endif; ?>
</div>

<?php

if ( function_exists( 'orbis_content_nav' ) ) {
	orbis_content_nav();
} else {
	the_posts_pagination();
}

get_footer();
