<?php
/**
 * Post types controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Post types controller class
 */
final class PostTypesController {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'init', $this->register_post_types( ... ) );
	}

	/**
	 * Register post types.
	 *
	 * @return void
	 */
	private function register_post_types(): void {
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
	}
}
