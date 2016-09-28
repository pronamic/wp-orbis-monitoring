<?php

class Orbis_Monitoring_Plugin extends Orbis_Plugin {
	public function __construct( $file ) {
		parent::__construct( $file );

		$this->set_name( 'orbis_monitoring' );
		$this->set_db_version( '1.0.0' );

		add_action( 'init', array( $this, 'init' ) );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );

		add_action( 'orbis_monitor', array( $this, 'monitor' ) );

		$post_type = 'orbis_monitor';

		add_filter( 'manage_edit-' . $post_type . '_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		add_action( 'save_post_' . $post_type, array( $this, 'save_post' ) );

		add_filter( 'slack_get_events', array( $this, 'slack_get_events' ) );

		// Tables
		orbis_register_table( 'orbis_monitor_responses' );
	}

	//////////////////////////////////////////////////

	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes( $post_type ) {
		if ( 'orbis_monitor' === $post_type ) {
			add_meta_box(
				'orbis_monitor_details',
				__( 'Monitor Details', 'orbis_monitoring' ),
				array( $this, 'meta_box_details' ),
				$post_type,
				'normal',
				'high'
			);

			add_meta_box(
				'orbis_monitor_responses',
				__( 'Monitor Responses', 'orbis_monitoring' ),
				array( $this, 'meta_box_responses' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public function meta_box_details( $post ) {
		include plugin_dir_path( $this->file ) . '/admin/meta-box-monitor-details.php';
	}

	public function meta_box_responses( $post ) {
		include plugin_dir_path( $this->file ) . '/admin/meta-box-monitor-responses.php';
	}


	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_post( $post_id ) {
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
			'_orbis_monitor_url' => FILTER_VALIDATE_URL,
		);

		$data = filter_input_array( INPUT_POST, $definition );

		foreach ( $data as $key => $value ) {
			if ( empty( $value ) ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
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
		orbis_install_table( 'orbis_monitor_responses', "
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
		" );

		// Parent
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
			case 'orbis_monitor_duration' :
				$duration = get_post_meta( $post_id, '_orbis_monitor_duration', true );

				if ( empty( $duration ) ) {
					echo '—';

					break;
				}

				echo esc_html( number_format_i18n( $duration, 6 ) );

				break;
			case 'orbis_monitor_response_code' :
				echo get_post_meta( $post_id, '_orbis_monitor_response_code', true );

				break;
			case 'orbis_monitor_response_message' :
				echo get_post_meta( $post_id, '_orbis_monitor_response_message', true );

				break;
			case 'orbis_monitor_response_content_length' :
				echo get_post_meta( $post_id, '_orbis_monitor_response_content_length', true );

				break;
			case 'orbis_monitor_response_content_type' :
				echo get_post_meta( $post_id, '_orbis_monitor_response_content_type', true );

				break;
			case 'orbis_monitor_modified_date' :
				the_modified_date( __( 'D j M Y \a\t H:i:s', 'orbis_monitor' ) );

				break;
		}
	}

	public function init() {
		register_post_type( 'orbis_monitor', array(
			'labels'             =>  array(
				'name'               => _x( 'Monitors', 'post type general name', 'your-plugin-textdomain' ),
				'singular_name'      => _x( 'Monitor', 'post type singular name', 'your-plugin-textdomain' ),
				'menu_name'          => _x( 'Monitors', 'admin menu', 'your-plugin-textdomain' ),
				'name_admin_bar'     => _x( 'Monitor', 'add new on admin bar', 'your-plugin-textdomain' ),
				'add_new'            => _x( 'Add New', 'monitor', 'your-plugin-textdomain' ),
				'add_new_item'       => __( 'Add New Monitor', 'your-plugin-textdomain' ),
				'new_item'           => __( 'New Monitor', 'your-plugin-textdomain' ),
				'edit_item'          => __( 'Edit Monitor', 'your-plugin-textdomain' ),
				'view_item'          => __( 'View Monitor', 'your-plugin-textdomain' ),
				'all_items'          => __( 'All Monitors', 'your-plugin-textdomain' ),
				'search_items'       => __( 'Search Monitors', 'your-plugin-textdomain' ),
				'parent_item_colon'  => __( 'Parent Monitor:', 'your-plugin-textdomain' ),
				'not_found'          => __( 'No monitors found.', 'your-plugin-textdomain' ),
				'not_found_in_trash' => __( 'No monitors found in Trash.', 'your-plugin-textdomain' )
			),
			'description'        => __( 'Description.', 'your-plugin-textdomain' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'monitors' ),
			'menu_icon'          => 'dashicons-sos',
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
		) );

		if ( ! wp_next_scheduled ( 'orbis_monitor' ) ) {
			wp_schedule_event( time(), 'every_5_minutes', 'orbis_monitor' );
		}
	}

	public function monitor_post( $post ) {
		global $wpdb;

		$url = get_post_meta( $post->ID, '_orbis_monitor_url', true );

		if ( empty( $url ) ) {
			return;
		}

		$start = microtime( true );

		// @see https://codex.wordpress.org/Function_Reference/wp_remote_get
		$response = wp_remote_get( $url, array(
			'timeout'    => 30,
			// @see http://www.browser-info.net/useragents
			'user-agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.85 Safari/537.36',
		) );

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

		wp_update_post( $post );

		$result = $wpdb->insert(
			$wpdb->orbis_monitor_responses,
			array( 
				'post_id'                 => $post->ID,
				'monitored_date'          => current_time( 'mysql' ),
				'duration'                => $duration,
				'response_code'           => $response_code,
				'response_message'        => $response_message,
				'response_body'           => $body,
				'response_content_length' => strlen( $body ),
				'response_content_type'   => $content_type,
			), 
			array( 
				'%d', 
				'%s',
				'%f',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			) 
		);

		// Custom actions
		$response_code = get_post_meta( $post->ID, '_orbis_monitor_response_code', true );

		if ( '200' !== $response_code ) {
			do_action( 'orbis_monitor_problem', $post );
		}
	}

	public function monitor() {
		global $wpdb;

		$query = new WP_Query( array(
			'post_type'      => 'orbis_monitor',
			'posts_per_page' => 5,
			'orderby'        => 'modified',
			'order'          => 'ASC',
		) );

		if ( $query->have_posts() ) {
			while( $query->have_posts() ) {
				$query->the_post();

				$this->monitor_post( get_post() );

			}

			wp_reset_postdata();
		}
	}

	/**
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
			'message'     => function( $post ) {
				return sprintf(
					__( 'Orbis monitor <%s|%s> was just checked, response code was `%s` » %s.', 'orbis_monitoring' ),
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
