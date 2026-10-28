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
		$settings_controller = new SettingsController( $file );
		$monitor_controller  = new MonitorController( $settings_controller );

		( new InstallController() )->setup();
		( new PostTypesController() )->setup();
		$settings_controller->setup();
		$monitor_controller->setup();
		( new AdminController( $file, $monitor_controller ) )->setup();
		( new SlackController() )->setup();
		( new TemplateController( $file ) )->setup();
	}
}
