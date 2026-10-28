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
	 * @param ResponseController $response_controller Response controller.
	 */
	public function __construct(
		private string $file,
		private IncidentController $incident_controller,
		private ResponseController $response_controller
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
		\add_action( 'orbis_before_main_content', $this->maybe_include_monitor_checks( ... ) );
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

		\wp_register_style(
			'orbis-monitoring',
			\plugins_url( 'assets/css/orbis-monitoring.css', $this->file ),
			[],
			(string) \filemtime( __DIR__ . '/../assets/css/orbis-monitoring.css' )
		);

		/**
		 * Bootstrap tooltips for the check bars, the `title` attribute is the fallback.
		 *
		 * @link https://getbootstrap.com/docs/5.3/components/tooltips/
		 */
		\wp_register_script( 'orbis-monitoring-tooltips', false, [], '1.0.0', true );

		\wp_add_inline_script(
			'orbis-monitoring-tooltips',
			'document.addEventListener( "DOMContentLoaded", () => { if ( window.bootstrap && window.bootstrap.Tooltip ) { document.querySelectorAll( ".orbis-monitor-checks [data-bs-toggle=tooltip]" ).forEach( ( element ) => window.bootstrap.Tooltip.getOrCreateInstance( element ) ); } } );'
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
	 * Maybe include monitor checks.
	 *
	 * @return void
	 */
	private function maybe_include_monitor_checks(): void {
		if ( ! \is_singular( 'orbis_monitor' ) ) {
			return;
		}

		$response_controller = $this->response_controller;

		include __DIR__ . '/../templates/monitor-checks.php';
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
