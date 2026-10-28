<?php
/**
 * Template controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Template controller class
 */
final class TemplateController {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_filter( 'template_include', $this->template_include( ... ) );

		\add_action( 'orbis_before_side_content', $this->maybe_include_monitor_details( ... ) );
		\add_action( 'orbis_before_main_content', $this->maybe_include_monitor_graph( ... ) );
		\add_action( 'orbis_before_main_content', $this->maybe_include_monitor_responses( ... ) );
	}

	/**
	 * Template include.
	 *
	 * Uses the monitor archive template of this plugin, unless the theme has one.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	private function template_include( $template ) {
		if ( \is_post_type_archive( 'orbis_monitor' ) && '' === \locate_template( 'archive-orbis_monitor.php' ) ) {
			return __DIR__ . '/../templates/archive-orbis_monitor.php';
		}

		return $template;
	}

	/**
	 * Maybe include monitor details.
	 *
	 * @return void
	 */
	private function maybe_include_monitor_details(): void {
		if ( ! \is_singular( 'orbis_monitor' ) ) {
			return;
		}

		include __DIR__ . '/../templates/monitor-details.php';
	}

	/**
	 * Maybe include monitor graph.
	 *
	 * @return void
	 */
	private function maybe_include_monitor_graph(): void {
		if ( ! \is_singular( 'orbis_monitor' ) ) {
			return;
		}

		include __DIR__ . '/../templates/monitor-graph.php';
	}

	/**
	 * Maybe include monitor responses.
	 *
	 * @return void
	 */
	private function maybe_include_monitor_responses(): void {
		if ( ! \is_singular( 'orbis_monitor' ) ) {
			return;
		}

		include __DIR__ . '/../templates/monitor-responses.php';
	}
}
