<?php
/**
 * Plugin
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Plugin class
 */
final class Plugin {
	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Return instance of this class.
	 *
	 * @param string $file Plugin file.
	 * @return self A single instance of this class.
	 */
	public static function instance( string $file = '' ): self {
		self::$instance ??= new self( $file );

		return self::$instance;
	}

	/**
	 * Construct.
	 *
	 * @param string $file Plugin file.
	 */
	private function __construct(
		/**
		 * Plugin file.
		 */
		public readonly string $file
	) {
		self::register_tables();

		// General hooks.
		\add_action( 'init', $this->init( ... ) );
		\add_action( 'init', $this->maybe_install( ... ), 20 );
		\add_filter( 'cron_schedules', $this->cron_schedules( ... ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		\add_action( 'add_meta_boxes', $this->add_meta_boxes( ... ) );
		\add_filter( 'slack_get_events', $this->slack_get_events( ... ) );

		// Monitor.
		\add_action( 'orbis_monitor', $this->monitor( ... ) );
		\add_action( 'save_post_orbis_monitor', $this->save_post( ... ) );
		\add_filter( 'manage_edit-orbis_monitor_columns', $this->columns( ... ) );
		\add_action( 'manage_orbis_monitor_posts_custom_column', $this->custom_columns( ... ), 10, 2 );

		// Monitor check.
		\add_action( 'save_post_orbis_monitor_check', $this->orbis_save_monitor_check( ... ) );
	}

	/**
	 * Register custom tables on `$wpdb`.
	 *
	 * @return void
	 */
	private static function register_tables(): void {
		global $wpdb;

		$wpdb->orbis_monitor_responses = $wpdb->prefix . 'orbis_monitor_responses';
	}

	/**
	 * Activate.
	 *
	 * Removes the database version so the tables are installed and the
	 * rewrite rules are flushed on the next `init`.
	 *
	 * @return void
	 */
	public static function activate(): void {
		\delete_option( 'orbis_monitoring_db_version' );
	}

	/**
	 * Maybe install.
	 *
	 * Runs after the post types are registered so the rewrite rules include them.
	 *
	 * @return void
	 */
	private function maybe_install(): void {
		if ( '1.0.0' === \get_option( 'orbis_monitoring_db_version' ) ) {
			return;
		}

		$this->install();

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Only once after activation or an update.
		\flush_rewrite_rules();

		\update_option( 'orbis_monitoring_db_version', '1.0.0' );
	}

	/**
	 * Install.
	 *
	 * @link https://codex.wordpress.org/Creating_Tables_with_Plugins
	 * @return void
	 */
	private function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = <<<SQL
			CREATE TABLE $wpdb->orbis_monitor_responses (
				response_id BIGINT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id BIGINT(16) UNSIGNED NOT NULL,
				monitored_date DATETIME NOT NULL,
				duration FLOAT DEFAULT NULL,
				response_code VARCHAR(3) DEFAULT NULL,
				response_message VARCHAR(40) DEFAULT NULL,
				response_body LONGTEXT DEFAULT NULL,
				response_date DATETIME NOT NULL,
				response_content_length BIGINT(20) DEFAULT NULL,
				response_content_type VARCHAR(40) DEFAULT NULL,
				PRIMARY KEY  (response_id),
				KEY post_id (post_id)
			) $charset_collate;
			SQL;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		\dbDelta( $sql );
	}

	/**
	 * Add meta boxes
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
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

		\add_meta_box(
			'orbis_monitor_check_details',
			\__( 'Monitor Check Details', 'orbis-monitoring' ),
			$this->meta_box_check_details( ... ),
			'orbis_monitor_check',
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
	public function meta_box_details( $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Used in the included template.
		include \plugin_dir_path( $this->file ) . 'admin/meta-box-monitor-details.php';
	}

	/**
	 * Meta box responses.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function meta_box_responses( $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Used in the included template.
		include \plugin_dir_path( $this->file ) . 'admin/meta-box-monitor-responses.php';
	}

	/**
	 * Meta box check details.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function meta_box_check_details( $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Used in the included template.
		include \plugin_dir_path( $this->file ) . 'admin/meta-box-monitor-check-details.php';
	}

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return int|void
	 */
	public function save_post( $post_id ) {
		if ( \filter_has_var( INPUT_POST, '_orbis_monitor_check' ) ) {
			$this->orbis_save_monitor_check( $post_id );
			return $post_id;
		}

		// Nonce.
		if ( ! \filter_has_var( INPUT_POST, 'orbis_monitor_details_meta_box_nonce' ) ) {
			return $post_id;
		}

		\check_admin_referer( 'orbis_save_monitor_details', 'orbis_monitor_details_meta_box_nonce' );

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( \defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// OK, its safe for us to save the data now.
		$definition = [
			'_orbis_monitor_url'                    => FILTER_VALIDATE_URL,
			'_orbis_monitor_required_response_code' => [
				'filter' => FILTER_VALIDATE_INT,
				'flags'  => FILTER_NULL_ON_FAILURE,
			],
			'_orbis_monitor_required_location'      => FILTER_UNSAFE_RAW,
			'_orbis_monitor_required_string'        => FILTER_UNSAFE_RAW,
		];

		$data = \filter_input_array( INPUT_POST, $definition );

		if ( isset( $data['_orbis_monitor_required_location'] ) ) {
			$data['_orbis_monitor_required_location'] = \sanitize_text_field( \wp_unslash( $data['_orbis_monitor_required_location'] ) );
		}

		foreach ( $data as $key => $value ) {
			if ( empty( $value ) ) {
				\delete_post_meta( $post_id, $key );
			} else {
				\update_post_meta( $post_id, $key, $value );
			}
		}

		$this->monitor_post( $post_id );
	}

	/**
	 * Can translate.
	 *
	 * Translation functions can only be used once the `init` action is running,
	 * some filters this plugin hooks into are applied earlier.
	 *
	 * @link https://github.com/pronamic/wp-orbis-monitoring/issues/21
	 *
	 * @return bool True if it is safe to translate, false otherwise.
	 */
	private function can_translate() {
		return ( \did_action( 'init' ) || \doing_action( 'init' ) );
	}

	/**
	 * Columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ) {
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
	public function custom_columns( $column, $post_id ) {
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

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public function init() {
		\register_post_type(
			'orbis_monitor',
			[
				'labels'             => [
					'name'               => \_x( 'Monitors', 'post type general name', 'orbis-monitoring' ),
					'singular_name'      => \_x( 'Monitor', 'post type singular name', 'orbis-monitoring' ),
					'menu_name'          => \_x( 'Monitors', 'admin menu', 'orbis-monitoring' ),
					'name_admin_bar'     => \_x( 'Monitor', 'add new on admin bar', 'orbis-monitoring' ),
					'add_new'            => \_x( 'Add New', 'monitor', 'orbis-monitoring' ),
					'add_new_item'       => \__( 'Add New Monitor', 'orbis-monitoring' ),
					'new_item'           => \__( 'New Monitor', 'orbis-monitoring' ),
					'edit_item'          => \__( 'Edit Monitor', 'orbis-monitoring' ),
					'view_item'          => \__( 'View Monitor', 'orbis-monitoring' ),
					'all_items'          => \__( 'All Monitors', 'orbis-monitoring' ),
					'search_items'       => \__( 'Search Monitors', 'orbis-monitoring' ),
					'parent_item_colon'  => \__( 'Parent Monitor:', 'orbis-monitoring' ),
					'not_found'          => \__( 'No monitors found.', 'orbis-monitoring' ),
					'not_found_in_trash' => \__( 'No monitors found in Trash.', 'orbis-monitoring' ),
				],
				'description'        => \__( 'Description.', 'orbis-monitoring' ),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => true,
				'rewrite'            => [
					'slug'       => 'monitors',
					'with_front' => false,
				],
				'menu_icon'          => 'dashicons-sos',
				'capability_type'    => 'post',
				'has_archive'        => true,
				'show_in_rest'       => true,
				'rest_base'          => 'orbis/monitors',
				'hierarchical'       => false,
				'menu_position'      => null,
				'supports'           => [
					'title',
					'author',
					'comments',
				],
			]
		);

		\register_post_type(
			'orbis_monitor_check',
			[
				'labels'             => [
					'name'               => \_x( 'Monitor Checks', 'post type general name', 'orbis-monitoring' ),
					'singular_name'      => \_x( 'Monitor Check', 'post type singular name', 'orbis-monitoring' ),
					'menu_name'          => \_x( 'Monitor Checks', 'admin menu', 'orbis-monitoring' ),
					'name_admin_bar'     => \_x( 'Monitor Check', 'add new on admin bar', 'orbis-monitoring' ),
					'add_new'            => \_x( 'Add New', 'monitor', 'orbis-monitoring' ),
					'add_new_item'       => \__( 'Add New Monitor Check', 'orbis-monitoring' ),
					'new_item'           => \__( 'New Monitor Check', 'orbis-monitoring' ),
					'edit_item'          => \__( 'Edit Monitor Check', 'orbis-monitoring' ),
					'view_item'          => \__( 'View Monitor Check', 'orbis-monitoring' ),
					'all_items'          => \__( 'Monitor Checks', 'orbis-monitoring' ),
					'search_items'       => \__( 'Search Monitor Checks', 'orbis-monitoring' ),
					'parent_item_colon'  => \__( 'Parent Monitor Check:', 'orbis-monitoring' ),
					'not_found'          => \__( 'No monitor check found.', 'orbis-monitoring' ),
					'not_found_in_trash' => \__( 'No monitor checks found in Trash.', 'orbis-monitoring' ),
				],
				'description'        => \__( 'Description.', 'orbis-monitoring' ),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => 'edit.php?post_type=orbis_monitor',
				'query_var'          => true,
				'rewrite'            => [ 'slug' => 'monitor_checks' ],
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => true,
				'menu_position'      => null,
				'supports'           => [
					'title',
					'author',
					'comments',
				],
			]
		);

		if ( ! \wp_next_scheduled( 'orbis_monitor' ) ) {
			\wp_schedule_event( \time(), 'every_5_minutes', 'orbis_monitor' );
		}
	}

	/**
	 * Monitor post.
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @return void
	 */
	public function monitor_post( $post ) {
		global $wpdb;

		$post = \get_post( $post );

		$url = \get_post_meta( $post->ID, '_orbis_monitor_url', true );

		if ( empty( $url ) ) {
			return;
		}

		$start = \microtime( true );

		$response = \wp_remote_get(
			$url,
			[
				'timeout'     => 30,
				'redirection' => 0,
				/**
				 * We pretend to be a widely used web browser.
				 *
				 * @link https://github.com/pronamic/wp-orbis-monitoring/issues/10
				 * @link http://www.browser-info.net/useragents
				 * @link https://www.whatismybrowser.com/guides/the-latest-user-agent/chrome
				 */
				'user-agent'  => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36',
			]
		);

		$end = \microtime( true );

		$duration = $end - $start;

		$response_code    = \wp_remote_retrieve_response_code( $response );
		$response_message = \wp_remote_retrieve_response_message( $response );
		$body             = \wp_remote_retrieve_body( $response );
		$content_type     = \wp_remote_retrieve_header( $response, 'content-type' );
		$date             = \wp_remote_retrieve_header( $response, 'date' );

		\update_post_meta( $post->ID, '_orbis_monitor_duration', $duration );
		\update_post_meta( $post->ID, '_orbis_monitor_response_code', $response_code );
		\update_post_meta( $post->ID, '_orbis_monitor_response_message', $response_message );
		\update_post_meta( $post->ID, '_orbis_monitor_response_body', $body );
		\update_post_meta( $post->ID, '_orbis_monitor_response_content_length', \strlen( $body ) );
		\update_post_meta( $post->ID, '_orbis_monitor_response_content_type', $content_type );
		\update_post_meta( $post->ID, '_orbis_monitor_response_date', \strtotime( $date ) );

		$wpdb->insert(
			$wpdb->orbis_monitor_responses,
			[
				'post_id'                 => $post->ID,
				'monitored_date'          => \current_time( 'mysql' ),
				'duration'                => $duration,
				'response_code'           => $response_code,
				'response_message'        => $response_message,
				'response_content_length' => \strlen( $body ),
				'response_content_type'   => $content_type,
			],
			[
				'%d',
				'%s',
				'%f',
				'%s',
				'%s',
				'%d',
				'%s',
			]
		);

		// Custom actions.
		$required_response_code = \get_post_meta( $post->ID, '_orbis_monitor_required_response_code', true );
		$required_response_code = empty( $required_response_code ) ? '200' : $required_response_code;

		$response_code = \get_post_meta( $post->ID, '_orbis_monitor_response_code', true );

		$required_location = \get_post_meta( $post->ID, '_orbis_monitor_required_location', true );

		// Add custom message.
		$message = null;

		// Required regular expression.
		$monitor_checks = new \WP_Query(
			[
				'post_type'      => 'orbis_monitor_check',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
			]
		);

		$monitor_checks = $monitor_checks->posts;

		$regex_match = true;

		foreach ( $monitor_checks as $check_id ) {
			$regex_check    = \get_post_meta( $check_id, '_orbis_monitor_check_required_string', true );
			$should_contain = \intval( \get_post_meta( $check_id, '_orbis_monitor_check_should_contain', true ) );

			if ( \preg_match( $regex_check, $body ) !== $should_contain ) {
				$regex_match = false;

				$message = \esc_html__( 'The response does not match the check.', 'orbis-monitoring' );
				break;
			}
		}

		if (
			( $required_response_code !== $response_code )
				||
			( ! empty( $required_location ) && \wp_remote_retrieve_header( $response, 'location' ) !== $required_location )
				||
			( ! $regex_match )
		) {
			\do_action( 'orbis_monitor_problem', $post, $response, $message );
		}

		\do_action( 'orbis_monitor_checked', $post, $response );
	}

	/**
	 * Save monitor check.
	 *
	 * @return void
	 */
	public function orbis_save_monitor_check() {
		global $post;

		$required_string = \filter_input( INPUT_POST, '_orbis_monitor_check_required_string', FILTER_UNSAFE_RAW );
		$should_contain  = \filter_input( INPUT_POST, '_orbis_monitor_check_should_contain', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE );

		if ( null !== $required_string ) {
			$required_string = \sanitize_text_field( \wp_unslash( $required_string ) );
		}

		\update_post_meta( $post->ID, '_orbis_monitor_check_required_string', $required_string );
		\update_post_meta( $post->ID, '_orbis_monitor_check_should_contain', $should_contain );
	}

	/**
	 * Monitor.
	 *
	 * @return void
	 */
	public function monitor() {
		$query = new \WP_Query(
			[
				'post_type'      => 'orbis_monitor',
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$this->monitor_post( \get_post() );

				\wp_update_post(
					[
						'ID' => \get_the_ID(),
					]
				);
			}

			\wp_reset_postdata();
		}
	}

	/**
	 * Cron Schedules
	 *
	 * @see https://codex.wordpress.org/Function_Reference/wp_schedule_event
	 * @see https://developer.wordpress.org/plugins/cron/understanding-wp-cron-scheduling/
	 * @see http://stackoverflow.com/questions/14103262/customizing-the-wp-schedule-event
	 * @see http://wordpress.stackexchange.com/questions/208135/how-to-run-a-function-every-5-minutes
	 * @see http://shinephp.com/start-scheduled-task-with-wp-cron-more-often/
	 *
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['every_5_minutes'] ) ) {
			$display = 'Once Every 5 Minutes';

			if ( $this->can_translate() ) {
				$display = \__( 'Once Every 5 Minutes', 'orbis-monitoring' );
			}

			$schedules['every_5_minutes'] = [
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => $display,
			];
		}

		return $schedules;
	}

	/**
	 * Slack events.
	 *
	 * @see http://gedex.web.id/wp-slack/
	 * @see https://github.com/gedex/wp-slack/blob/0.5.1/includes/event-manager.php#L57-L167
	 * @see https://github.com/gedex/wp-slack-edd/blob/0.1.0/slack-edd.php
	 *
	 * @param array<string, array<string, mixed>> $events Events.
	 * @return array<string, array<string, mixed>>
	 */
	public function slack_get_events( $events ) {
		/*
		 * The `slack_get_events` filter is applied on `plugins_loaded`, before the
		 * `init` action, so translations are only resolved when it is safe to do so.
		 * The event descriptions are only displayed on the Slack integration settings
		 * page, where this filter is applied again after the `init` action.
		 *
		 * @link https://github.com/pronamic/wp-orbis-monitoring/issues/21
		 */
		$can_translate = $this->can_translate();

		$problem_description = 'When a Orbis monitor problem was detected.';
		$checked_description = 'When a Orbis monitor was checked.';

		if ( $can_translate ) {
			$problem_description = \__( 'When a Orbis monitor problem was detected.', 'orbis-monitoring' );
			$checked_description = \__( 'When a Orbis monitor was checked.', 'orbis-monitoring' );
		}

		$events['orbis_monitor_problem'] = [
			'action'      => 'orbis_monitor_problem',
			'description' => $problem_description,
			'message'     => function ( $post, $response, $extra ) {
				$message = \sprintf(
					\__( 'Orbis monitor <%s|%s> was just checked, response code was `%s` » %s.', 'orbis-monitoring' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
					\get_permalink( $post ),
					\get_the_title( $post ),
					\get_post_meta( $post->ID, '_orbis_monitor_response_code', true ),
					\get_post_meta( $post->ID, '_orbis_monitor_url', true )
				);

				if ( \is_wp_error( $response ) ) {
					$message .= "\n";
					$message .= $response->get_error_message();
				}

				if ( ! empty( $extra ) ) {
					$message .= "\n";
					$message .= $extra;
				}

				return $message;
			},
		];

		$events['orbis_monitor_checked'] = [
			'action'      => 'orbis_monitor_checked',
			'description' => $checked_description,
			'message'     => function ( $post ) {
				return \sprintf(
					\__( 'Orbis monitor <%s|%s> was just checked, response code was `%s` » %s.', 'orbis-monitoring' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
					\get_permalink( $post ),
					\get_the_title( $post ),
					\get_post_meta( $post->ID, '_orbis_monitor_response_code', true ),
					\get_post_meta( $post->ID, '_orbis_monitor_url', true )
				);
			},
		];

		return $events;
	}
}
