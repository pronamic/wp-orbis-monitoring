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
final readonly class TemplateController {
	/**
	 * Construct.
	 *
	 * @param string             $file                Plugin file.
	 * @param IncidentController $incident_controller Incident controller.
	 */
	public function __construct(
		private string $file,
		private IncidentController $incident_controller
	) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'wp_enqueue_scripts', $this->register_scripts( ... ) );

		\add_filter( 'template_include', $this->template_include( ... ) );

		\add_action( 'orbis_before_side_content', $this->maybe_include_monitor_details( ... ) );
		\add_action( 'orbis_before_main_content', $this->maybe_include_monitor_graph( ... ) );
		\add_action( 'orbis_before_main_content', $this->maybe_include_monitor_incidents( ... ) );
		\add_action( 'orbis_before_main_content', $this->maybe_include_monitor_responses( ... ) );
	}

	/**
	 * Register scripts.
	 *
	 * @link https://www.chartjs.org/
	 * @return void
	 */
	private function register_scripts(): void {
		\wp_register_script(
			'orbis-monitoring-chartjs',
			\plugins_url( 'assets/chart.js/chart.umd.min.js', $this->file ),
			[],
			'4.6.1',
			true
		);
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

		$incident_controller = $this->incident_controller;

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
	 * Maybe include monitor incidents.
	 *
	 * @return void
	 */
	private function maybe_include_monitor_incidents(): void {
		if ( ! \is_singular( 'orbis_monitor' ) ) {
			return;
		}

		$incident_controller = $this->incident_controller;

		include __DIR__ . '/../templates/monitor-incidents.php';
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
