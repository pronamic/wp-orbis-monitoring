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
final readonly class AdminController {
	/**
	 * Construct.
	 *
	 * @param string             $file                Plugin file.
	 * @param MonitorController  $monitor_controller  Monitor controller.
	 * @param IncidentController $incident_controller Incident controller.
	 */
	public function __construct(
		private string $file,
		private MonitorController $monitor_controller,
		private IncidentController $incident_controller
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
			'orbis_monitor_status',
			\__( 'Status', 'orbis-monitoring' ),
			$this->meta_box_status( ... ),
			'orbis_monitor',
			'side',
			'high'
		);

		\add_meta_box(
			'orbis_monitor_incidents',
			\__( 'Monitor Incidents', 'orbis-monitoring' ),
			$this->meta_box_incidents( ... ),
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
	 * Meta box status.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	private function meta_box_status( $post ): void {
		$this->column_status( $post->ID );
	}

	/**
	 * Meta box incidents.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	private function meta_box_incidents( $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Used in the included template.
		$incident_controller = $this->incident_controller;

		include \plugin_dir_path( $this->file ) . 'admin/meta-box-monitor-incidents.php';
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
			'_orbis_monitor_required_location'      => isset( $_POST['_orbis_monitor_required_location'] ) ? \sanitize_url( \wp_unslash( $_POST['_orbis_monitor_required_location'] ) ) : '',
		];

		foreach ( $data as $key => $value ) {
			if ( empty( $value ) ) {
				\delete_post_meta( $post_id, $key );
			} else {
				\update_post_meta( $post_id, $key, $value );
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in `sanitize_headers()`.
		$headers = isset( $_POST['_orbis_monitor_headers'] ) ? $this->sanitize_headers( \wp_unslash( $_POST['_orbis_monitor_headers'] ) ) : [];

		$this->save_headers( $post_id, $headers );

		$this->monitor_controller->monitor_post( $post_id );
	}

	/**
	 * Sanitize the custom HTTP headers from the monitor details meta box.
	 *
	 * Rows with an empty or invalid header name are skipped. Line breaks are
	 * removed from the values to prevent header injection.
	 *
	 * @link https://github.com/pronamic/wp-orbis-monitoring/issues/28
	 * @link https://www.rfc-editor.org/rfc/rfc9110#name-tokens
	 * @param mixed $input Input.
	 * @return string[] Headers as `Name: Value` lines.
	 */
	private function sanitize_headers( $input ): array {
		if ( ! \is_array( $input ) ) {
			return [];
		}

		$headers = [];

		foreach ( $input as $row ) {
			if ( ! \is_array( $row ) ) {
				continue;
			}

			$name  = \trim( \sanitize_text_field( (string) ( $row['name'] ?? '' ) ) );
			$value = \trim( \sanitize_text_field( (string) ( $row['value'] ?? '' ) ) );

			if ( 1 !== \preg_match( '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $name ) ) {
				continue;
			}

			$headers[] = $name . ': ' . $value;
		}

		return \array_values( \array_unique( $headers ) );
	}

	/**
	 * Save the custom HTTP headers, one `_orbis_monitor_header` meta row per header.
	 *
	 * Only headers that are new get added and only headers that are gone get
	 * deleted, unchanged headers keep their meta row.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $headers Headers as `Name: Value` lines.
	 * @return void
	 */
	private function save_headers( int $post_id, array $headers ): void {
		$current = \get_post_meta( $post_id, '_orbis_monitor_header', false );

		foreach ( \array_diff( $current, $headers ) as $header ) {
			\delete_post_meta( $post_id, '_orbis_monitor_header', \wp_slash( $header ) );
		}

		foreach ( \array_diff( $headers, $current ) as $header ) {
			\add_post_meta( $post_id, '_orbis_monitor_header', \wp_slash( $header ) );
		}
	}

	/**
	 * Columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	private function columns( $columns ) {
		$columns['orbis_monitor_status']                  = \__( 'Status', 'orbis-monitoring' );
		$columns['orbis_monitor_duration']                = \__( 'Duration', 'orbis-monitoring' );
		$columns['orbis_monitor_response_code']           = \__( 'Last Response Code', 'orbis-monitoring' );
		$columns['orbis_monitor_message']                 = \__( 'Last Message', 'orbis-monitoring' );
		$columns['orbis_monitor_response_content_length'] = \__( 'Last Content Length', 'orbis-monitoring' );
		$columns['orbis_monitor_response_content_type']   = \__( 'Last Content Type', 'orbis-monitoring' );
		$columns['orbis_monitor_checked_at']              = \__( 'Last Check', 'orbis-monitoring' );

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
			case 'orbis_monitor_status':
				$this->column_status( $post_id );

				break;
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
			case 'orbis_monitor_message':
				$error_message = \get_post_meta( $post_id, '_orbis_monitor_error_message', true );

				echo \esc_html( empty( $error_message ) ? \get_status_header_desc( (int) \get_post_meta( $post_id, '_orbis_monitor_response_code', true ) ) : $error_message );

				break;
			case 'orbis_monitor_response_content_length':
				echo \esc_html( \get_post_meta( $post_id, '_orbis_monitor_response_content_length', true ) );

				break;
			case 'orbis_monitor_response_content_type':
				echo \esc_html( \get_post_meta( $post_id, '_orbis_monitor_response_content_type', true ) );

				break;
			case 'orbis_monitor_checked_at':
				$monitor = $this->incident_controller->get_monitor_by_post_id( $post_id );

				if ( null === $monitor || null === $monitor->checked_at ) {
					echo '—';

					break;
				}

				echo \esc_html( \wp_date( \__( 'D j M Y \a\t H:i:s', 'orbis-monitoring' ), (int) \strtotime( $monitor->checked_at . ' UTC' ) ) );

				break;
		}
	}

	/**
	 * Status column.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function column_status( $post_id ): void {
		$monitor = $this->incident_controller->get_monitor_by_post_id( $post_id );

		if ( null === $monitor ) {
			echo '—';

			return;
		}

		echo \esc_html( $monitor->status->icon() . ' ' . $monitor->status->label() );

		if ( Status::Pending === $monitor->status ) {
			echo ' ';
			\printf(
				/* translators: %d: number of failed checks in a row */
				\esc_html( \_n( '(%d failed check)', '(%d failed checks)', (int) $monitor->failure_count, 'orbis-monitoring' ) ),
				(int) $monitor->failure_count
			);
		}

		if ( null !== $monitor->status_changed_at && Status::Unknown !== $monitor->status ) {
			echo '<br />';
			\printf(
				/* translators: %s: human readable time difference */
				\esc_html__( 'for %s', 'orbis-monitoring' ),
				\esc_html( \human_time_diff( (int) \strtotime( $monitor->status_changed_at . ' UTC' ) ) )
			);
		}
	}
}
