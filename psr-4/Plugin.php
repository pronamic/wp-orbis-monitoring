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
	 * Incident controller.
	 *
	 * @var IncidentController
	 */
	public readonly IncidentController $incident_controller;

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
		$settings_controller = new SettingsController( $file );
		$incident_controller = new IncidentController( $settings_controller );
		$monitor_controller  = new MonitorController( $settings_controller, $incident_controller );

		$this->incident_controller = $incident_controller;

		( new InstallController() )->setup();
		( new PostTypesController() )->setup();
		$settings_controller->setup();
		$incident_controller->setup();
		$monitor_controller->setup();
		( new AdminController( $file, $monitor_controller, $incident_controller ) )->setup();
		( new SlackController() )->setup();
		( new TemplateController( $file, $incident_controller ) )->setup();
	}
}
