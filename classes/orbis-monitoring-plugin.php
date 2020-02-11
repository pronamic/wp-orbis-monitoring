<?php

class Orbis_Monitoring_Plugin extends Orbis_Plugin {
	public function __construct( $file ) {
		parent::__construct( $file );

		$this->set_name( 'orbis_monitoring' );
		$this->set_db_version( '1.0.0' );

		// general hooks
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) ); // phpcs:ignore WordPress.VIP.CronInterval.CronSchedulesInterval
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'slack_get_events', array( $this, 'slack_get_events' ) );

		// orbis_monitor
		add_action( 'orbis_monitor', array( $this, 'monitor' ) );
		add_action( 'save_post_orbis_monitor', array( $this, 'save_post' ) );
		add_filter( 'manage_edit-orbis_monitor_columns', array( $this, 'columns' ) );
		add_action( 'manage_orbis_monitor_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );

		// orbis_monitor_check
		add_action( 'save_post_orbis_monitor_check', array( $this, 'orbis_save_monitor_check' ) );

		// Tables
		orbis_register_table( 'orbis_monitor_responses' );
	}

	//////////////////////////////////////////////////

	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes( $post_type ) {
		add_meta_box(
			'orbis_monitor_details',
			__( 'Monitor Details', 'orbis_monitoring' ),
			array( $this, 'meta_box_details' ),
			'orbis_monitor',
			'normal',
			'high'
		);

		add_meta_box(
			'orbis_monitor_responses',
			__( 'Monitor Responses', 'orbis_monitoring' ),
			array( $this, 'meta_box_responses' ),
			'orbis_monitor',
			'normal',
			'high'
		);

		add_meta_box(
			'orbis_monitor_check_details',
			__( 'Monitor Check Details', 'orbis_monitoring' ),
			array( $this, 'meta_box_check_details' ),
			'orbis_monitor_check',
			'normal',
			'high'
		);
	}

	public function meta_box_details( $post ) {
		include plugin_dir_path( $this->file ) . '/admin/meta-box-monitor-details.php';
	}

	public function meta_box_responses( $post ) {
		include plugin_dir_path( $this->file ) . '/admin/meta-box-monitor-responses.php';
	}

	public function meta_box_check_details( $post ) {
		include plugin_dir_path( $this->file ) . '/admin/meta-box-monitor-check-details.php';
	}

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_post( $post_id ) {

		if ( filter_has_var( INPUT_POST, '_orbis_monitor_check' ) ) {
			$this->orbis_save_monitor_check( $post_id );
			return $post_id;
		}

		// Nonce
		if ( ! filter_has_var( INPUT_POST, 'orbis_monitor_details_meta_box_nonce' ) ) {
			return $post_id;
		}

		check_admin_referer( 'orbis_save_monitor_details', 'orbis_monitor_details_meta_box_nonce' );

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		/* OK, its safe for us to save the data now. */
		$definition = array(
			'_orbis_monitor_url'                    => FILTER_VALIDATE_URL,
			'_orbis_monitor_required_response_code' => FILTER_SANITIZE_STRING,
			'_orbis_monitor_required_location'      => FILTER_SANITIZE_STRING,
			'_orbis_monitor_required_string'        => FILTER_UNSAFE_RAW,
		);

		$data = filter_input_array( INPUT_POST, $definition );

		foreach ( $data as $key => $value ) {
			if ( empty( $value ) ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		$this->monitor_post( $post_id );
	}

	/**
	 * Install
	 *
	 * @mysql UPDATE wp_options SET option_value = 0 WHERE option_name = 'orbis_db_version';
	 *
	 * @see Orbis_Plugin::install()
	 */
	public function install() {
		// Tables
		orbis_install_table( 'orbis_monitor_responses', '
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
		' );

		parent::install();
	}

	public function loaded() {
		$this->load_textdomain( 'orbis_monitoring', '/languages/' );
	}

	public function columns( $columns ) {
		$columns['orbis_monitor_duration']                = 'Duration';
		$columns['orbis_monitor_response_code']           = 'Last Response Code';
		$columns['orbis_monitor_response_message']        = 'Last Response Message';
		$columns['orbis_monitor_response_content_length'] = 'Last Content Length';
		$columns['orbis_monitor_response_content_type']   = 'Last Content Type';
		$columns['orbis_monitor_modified_date']           = 'Last Check';

		return $columns;
	}

	public function custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'orbis_monitor_duration':
				$duration = get_post_meta( $post_id, '_orbis_monitor_duration', true );

				if ( empty( $duration ) ) {
					echo '—';

					break;
				}

				echo esc_html( number_format_i18n( $duration, 6 ) );

				break;
			case 'orbis_monitor_response_code':
				echo esc_html( get_post_meta( $post_id, '_orbis_monitor_response_code', true ) );

				break;
			case 'orbis_monitor_response_message':
				echo esc_html( get_post_meta( $post_id, '_orbis_monitor_response_message', true ) );

				break;
			case 'orbis_monitor_response_content_length':
				echo esc_html( get_post_meta( $post_id, '_orbis_monitor_response_content_length', true ) );

				break;
			case 'orbis_monitor_response_content_type':
				echo esc_html( get_post_meta( $post_id, '_orbis_monitor_response_content_type', true ) );

				break;
			case 'orbis_monitor_modified_date':
				the_modified_date( __( 'D j M Y \a\t H:i:s', 'orbis_monitor' ) );

				break;
		}
	}

	public function init() {
		register_post_type( 'orbis_monitor', array(
			'labels'             => array(
				'name'               => _x( 'Monitors', 'post type general name', 'orbis_monitoring' ),
				'singular_name'      => _x( 'Monitor', 'post type singular name', 'orbis_monitoring' ),
				'menu_name'          => _x( 'Monitors', 'admin menu', 'orbis_monitoring' ),
				'name_admin_bar'     => _x( 'Monitor', 'add new on admin bar', 'orbis_monitoring' ),
				'add_new'            => _x( 'Add New', 'monitor', 'orbis_monitoring' ),
				'add_new_item'       => __( 'Add New Monitor', 'orbis_monitoring' ),
				'new_item'           => __( 'New Monitor', 'orbis_monitoring' ),
				'edit_item'          => __( 'Edit Monitor', 'orbis_monitoring' ),
				'view_item'          => __( 'View Monitor', 'orbis_monitoring' ),
				'all_items'          => __( 'All Monitors', 'orbis_monitoring' ),
				'search_items'       => __( 'Search Monitors', 'orbis_monitoring' ),
				'parent_item_colon'  => __( 'Parent Monitor:', 'orbis_monitoring' ),
				'not_found'          => __( 'No monitors found.', 'orbis_monitoring' ),
				'not_found_in_trash' => __( 'No monitors found in Trash.', 'orbis_monitoring' ),
			),
			'description'        => __( 'Description.', 'orbis_monitoring' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'monitors',
				'with_front' => false,
			),
			'menu_icon'          => 'dashicons-sos',
			'capability_type'    => 'post',
			'has_archive'        => true,
			'show_in_rest'       => true,
			'rest_base'          => 'orbis/monitors',
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array(
				'title',
				'author',
				'comments',
			),
		) );

		register_post_type( 'orbis_monitor_check', array(
			'labels'             => array(
				'name'               => _x( 'Monitor Checks', 'post type general name', 'orbis_monitoring' ),
				'singular_name'      => _x( 'Monitor Check', 'post type singular name', 'orbis_monitoring' ),
				'menu_name'          => _x( 'Monitor Checks', 'admin menu', 'orbis_monitoring' ),
				'name_admin_bar'     => _x( 'Monitor Check', 'add new on admin bar', 'orbis_monitoring' ),
				'add_new'            => _x( 'Add New', 'monitor', 'orbis_monitoring' ),
				'add_new_item'       => __( 'Add New Monitor Check', 'orbis_monitoring' ),
				'new_item'           => __( 'New Monitor Check', 'orbis_monitoring' ),
				'edit_item'          => __( 'Edit Monitor Check', 'orbis_monitoring' ),
				'view_item'          => __( 'View Monitor Check', 'orbis_monitoring' ),
				'all_items'          => __( 'Monitor Checks', 'orbis_monitoring' ),
				'search_items'       => __( 'Search Monitor Checks', 'orbis_monitoring' ),
				'parent_item_colon'  => __( 'Parent Monitor Check:', 'orbis_monitoring' ),
				'not_found'          => __( 'No monitor check found.', 'orbis_monitoring' ),
				'not_found_in_trash' => __( 'No monitor checks found in Trash.', 'orbis_monitoring' ),
			),
			'description'        => __( 'Description.', 'orbis_monitoring' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'monitor_checks' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'show_in_menu'       => 'edit.php?post_type=orbis_monitor',
			'hierarchical'       => true,
			'menu_position'      => null,
			'supports'           => array(
				'title',
				'author',
				'comments',
			),
		) );

		if ( ! wp_next_scheduled( 'orbis_monitor' ) ) {
			wp_schedule_event( time(), 'every_5_minutes', 'orbis_monitor' );
		}
	}

	public function monitor_post( $post ) {
		global $wpdb;

		$post = get_post( $post ); // WPCS: override ok.

		$url = get_post_meta( $post->ID, '_orbis_monitor_url', true );

		if ( empty( $url ) ) {
			return;
		}

		$start = microtime( true );

		// @see https://codex.wordpress.org/Function_Reference/wp_remote_get
		$response = wp_remote_get( $url, // phpcs:ignore WordPress.VIP.RestrictedFunctions.wp_remote_get_wp_remote_get
			array(
				'timeout'     => 30,
				'redirection' => 0,
				// @see http://www.browser-info.net/useragents
				'user-agent'  => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.85 Safari/537.36',
			)
		);

		// Try again if resolving timed out ("cURL error 28: Resolving timed out after XXXXX milliseconds").
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			// Retry if error message contains 'Resolving timed out'.
			if ( false !== stripos( $error_message, 'Resolving timed out' ) ) {
				usleep( 300000 ); // Wait 300ms

				$start = microtime( true );

				$response = wp_remote_get( $url, $request_args );
			}
		}

		$end = microtime( true );

		$duration = $end - $start;

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$body             = wp_remote_retrieve_body( $response );
		$content_type     = wp_remote_retrieve_header( $response, 'content-type' );
		$date             = wp_remote_retrieve_header( $response, 'date' );

		update_post_meta( $post->ID, '_orbis_monitor_duration', $duration );
		update_post_meta( $post->ID, '_orbis_monitor_response_code', $response_code );
		update_post_meta( $post->ID, '_orbis_monitor_response_message', $response_message );
		update_post_meta( $post->ID, '_orbis_monitor_response_body', $body );
		update_post_meta( $post->ID, '_orbis_monitor_response_content_length', strlen( $body ) );
		update_post_meta( $post->ID, '_orbis_monitor_response_content_type', $content_type );
		update_post_meta( $post->ID, '_orbis_monitor_response_date', strtotime( $date ) );

		$result = $wpdb->insert(
			$wpdb->orbis_monitor_responses,
			array(
				'post_id'                 => $post->ID,
				'monitored_date'          => current_time( 'mysql' ),
				'duration'                => $duration,
				'response_code'           => $response_code,
				'response_message'        => $response_message,
				'response_content_length' => strlen( $body ),
				'response_content_type'   => $content_type,
			),
			array(
				'%d',
				'%s',
				'%f',
				'%s',
				'%s',
				'%d',
				'%s',
			)
		);

		// Custom actions
		$required_response_code = get_post_meta( $post->ID, '_orbis_monitor_required_response_code', true );
		$required_response_code = empty( $required_response_code ) ? '200' : $required_response_code;

		$response_code = get_post_meta( $post->ID, '_orbis_monitor_response_code', true );

		$required_location = get_post_meta( $post->ID, '_orbis_monitor_required_location', true );

		// add custom message
		$message = null;

		// required regular expression
		$monitor_checks = new WP_Query( array(
			'post_type'      => 'orbis_monitor_check',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
		) );

		$monitor_checks = $monitor_checks->posts;

		$regex_match = true;

		foreach ( $monitor_checks as $check_id ) {
			$regex_check    = get_post_meta( $check_id, '_orbis_monitor_check_required_string', true );
			$should_contain = intval( get_post_meta( $check_id, '_orbis_monitor_check_should_contain', true ) );

			if ( preg_match( $regex_check, $response['body'] ) !== $should_contain ) {
				$regex_match = false;

				$message = esc_html__( 'The response does not match the check.', 'orbis_monitoring' );
				break;
			}
		}

		if (
			( $required_response_code !== $response_code )
				||
			( ! empty( $required_location ) && wp_remote_retrieve_header( $response, 'location' ) !== $required_location )
				||
			( ! $regex_match )
		) {
			do_action( 'orbis_monitor_problem', $post, $response, $message );
		}

		do_action( 'orbis_monitor_checked', $post, $response );
	}

	public function orbis_save_monitor_check() {
		global $post;

		$required_string = filter_input( INPUT_POST, '_orbis_monitor_check_required_string', FILTER_SANITIZE_STRING );
		$should_contain  = filter_input( INPUT_POST, '_orbis_monitor_check_should_contain', FILTER_SANITIZE_STRING );

		update_post_meta( $post->ID, '_orbis_monitor_check_required_string', $required_string );
		update_post_meta( $post->ID, '_orbis_monitor_check_should_contain', $should_contain );
	}

	public function monitor() {
		$query = new WP_Query( array(
			'post_type'      => 'orbis_monitor',
			'posts_per_page' => 5,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$this->monitor_post( get_post() );

				wp_update_post( array(
					'ID' => get_the_ID(),
				) );
			}

			wp_reset_postdata();
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
	 */
	public function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['every_5_minutes'] ) ) {
			$schedules['every_5_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Once Every 5 Minutes', 'orbis_monitoring' ),
			);
		}

		return $schedules;
	}

	/**
	 * Slack events.
	 *
	 * @see http://gedex.web.id/wp-slack/
	 * @see https://github.com/gedex/wp-slack/blob/0.5.1/includes/event-manager.php#L57-L167
	 * @see https://github.com/gedex/wp-slack-edd/blob/0.1.0/slack-edd.php
	 */
	public function slack_get_events( $events ) {
		$events['orbis_monitor_problem'] = array(
			'action'      => 'orbis_monitor_problem',
			'description' => __( 'When a Orbis monitor problem was detected.', 'orbis_monitoring' ),
			'message'     => function( $post, $response, $extra ) {
				$message = sprintf(
					__( 'Orbis monitor <%s|%s> was just checked, response code was `%s` » %s.', 'orbis_monitoring' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
					get_permalink( $post ),
					get_the_title( $post ),
					get_post_meta( $post->ID, '_orbis_monitor_response_code', true ),
					get_post_meta( $post->ID, '_orbis_monitor_url', true )
				);

				if ( is_wp_error( $response ) ) {
					$message .= "\n";
					$message .= $response->get_error_message();
				}

				if ( ! empty( $extra ) ) {
					$message .= "\n";
					$message .= $extra;
				}

				return $message;
			},
		);

		$events['orbis_monitor_checked'] = array(
			'action'      => 'orbis_monitor_checked',
			'description' => __( 'When a Orbis monitor was checked.', 'orbis_monitoring' ),
			'message'     => function( $post, $response ) {
				return sprintf(
					__( 'Orbis monitor <%s|%s> was just checked, response code was `%s` » %s.', 'orbis_monitoring' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
					get_permalink( $post ),
					get_the_title( $post ),
					get_post_meta( $post->ID, '_orbis_monitor_response_code', true ),
					get_post_meta( $post->ID, '_orbis_monitor_url', true )
				);
			},
		);

		return $events;
	}
}
