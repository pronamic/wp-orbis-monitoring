<?php
/**
 * Admin controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Admin controller class
 */
final class AdminController {
	/**
	 * Construct.
	 *
	 * @param string            $file               Plugin file.
	 * @param MonitorController $monitor_controller Monitor controller.
	 */
	public function __construct(
		private readonly string $file,
		private readonly MonitorController $monitor_controller
	) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'add_meta_boxes', $this->add_meta_boxes( ... ) );

		// Monitor.
		\add_action( 'save_post_orbis_monitor', $this->save_monitor( ... ) );
		\add_filter( 'manage_edit-orbis_monitor_columns', $this->columns( ... ) );
		\add_action( 'manage_orbis_monitor_posts_custom_column', $this->custom_columns( ... ), 10, 2 );
	}

	/**
	 * Add meta boxes
	 *
	 * @return void
	 */
	private function add_meta_boxes(): void {
		\add_meta_box(
			'orbis_monitor_details',
			\__( 'Monitor Details', 'orbis-monitoring' ),
			$this->meta_box_details( ... ),
			'orbis_monitor',
			'normal',
			'high'
		);

		\add_meta_box(
			'orbis_monitor_responses',
			\__( 'Monitor Responses', 'orbis-monitoring' ),
			$this->meta_box_responses( ... ),
			'orbis_monitor',
			'normal',
			'high'
		);
	}

	/**
	 * Meta box details.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	private function meta_box_details( $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Used in the included template.
		include \plugin_dir_path( $this->file ) . 'admin/meta-box-monitor-details.php';
	}

	/**
	 * Meta box responses.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	private function meta_box_responses( $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Used in the included template.
		include \plugin_dir_path( $this->file ) . 'admin/meta-box-monitor-responses.php';
	}

	/**
	 * Save monitor.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	private function save_monitor( $post_id ): void {
		// Nonce.
		if ( ! isset( $_POST['orbis_monitor_details_meta_box_nonce'] ) ) {
			return;
		}

		\check_admin_referer( 'orbis_save_monitor_details', 'orbis_monitor_details_meta_box_nonce' );

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( \defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// OK, its safe for us to save the data now.
		$data = [
			'_orbis_monitor_url'                    => isset( $_POST['_orbis_monitor_url'] ) ? \sanitize_url( \wp_unslash( $_POST['_orbis_monitor_url'] ) ) : '',
			'_orbis_monitor_required_response_code' => isset( $_POST['_orbis_monitor_required_response_code'] ) ? \absint( $_POST['_orbis_monitor_required_response_code'] ) : 0,
			'_orbis_monitor_required_location'      => isset( $_POST['_orbis_monitor_required_location'] ) ? \sanitize_text_field( \wp_unslash( $_POST['_orbis_monitor_required_location'] ) ) : '',
		];

		foreach ( $data as $key => $value ) {
			if ( empty( $value ) ) {
				\delete_post_meta( $post_id, $key );
			} else {
				\update_post_meta( $post_id, $key, $value );
			}
		}

		$this->monitor_controller->monitor_post( $post_id );
	}

	/**
	 * Columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	private function columns( $columns ) {
		$columns['orbis_monitor_duration']                = 'Duration';
		$columns['orbis_monitor_response_code']           = 'Last Response Code';
		$columns['orbis_monitor_response_message']        = 'Last Response Message';
		$columns['orbis_monitor_response_content_length'] = 'Last Content Length';
		$columns['orbis_monitor_response_content_type']   = 'Last Content Type';
		$columns['orbis_monitor_modified_date']           = 'Last Check';

		return $columns;
	}

	/**
	 * Custom columns.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function custom_columns( $column, $post_id ): void {
		switch ( $column ) {
			case 'orbis_monitor_duration':
				$duration = \get_post_meta( $post_id, '_orbis_monitor_duration', true );

				if ( empty( $duration ) ) {
					echo '—';

					break;
				}

				echo \esc_html( \number_format_i18n( (float) $duration, 6 ) );

				break;
			case 'orbis_monitor_response_code':
				echo \esc_html( \get_post_meta( $post_id, '_orbis_monitor_response_code', true ) );

				break;
			case 'orbis_monitor_response_message':
				echo \esc_html( \get_post_meta( $post_id, '_orbis_monitor_response_message', true ) );

				break;
			case 'orbis_monitor_response_content_length':
				echo \esc_html( \get_post_meta( $post_id, '_orbis_monitor_response_content_length', true ) );

				break;
			case 'orbis_monitor_response_content_type':
				echo \esc_html( \get_post_meta( $post_id, '_orbis_monitor_response_content_type', true ) );

				break;
			case 'orbis_monitor_modified_date':
				\the_modified_date( \__( 'D j M Y \a\t H:i:s', 'orbis-monitoring' ) );

				break;
		}
	}
}
