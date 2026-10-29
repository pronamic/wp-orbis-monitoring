<?php
/**
 * Status
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Status enum
 *
 * The outcome of the checks of a monitor: does the monitored URL respond as
 * expected? A deviation, such as an unexpected status code, is not necessarily
 * an outage. The value is stored in the `status` column of the monitors table.
 *
 * phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- False positive, `$this` is the enum case.
 */
enum Status: string {
	/**
	 * Not checked yet, or the monitor is not published.
	 */
	case Unknown = 'unknown';

	/**
	 * The last check matched the expectations.
	 */
	case Ok = 'ok';

	/**
	 * One or more checks failed, but not enough in a row to raise an alert.
	 */
	case Pending = 'pending';

	/**
	 * Enough checks failed in a row, an alert is raised and an incident is open.
	 */
	case Alert = 'alert';

	/**
	 * Get the label.
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Unknown => \__( 'Unknown', 'orbis-monitoring' ),
			self::Ok      => \__( 'OK', 'orbis-monitoring' ),
			self::Pending => \__( 'Pending', 'orbis-monitoring' ),
			self::Alert   => \__( 'Alert', 'orbis-monitoring' ),
		};
	}

	/**
	 * Get the icon.
	 *
	 * @return string
	 */
	public function icon(): string {
		return match ( $this ) {
			self::Unknown => '⚪',
			self::Ok      => '🟢',
			self::Pending => '🟠',
			self::Alert   => '🔴',
		};
	}

	/**
	 * Get the Bootstrap theme color.
	 *
	 * @link https://getbootstrap.com/docs/5.3/customize/color/#theme-colors
	 * @return string
	 */
	public function color(): string {
		return match ( $this ) {
			self::Unknown => 'secondary',
			self::Ok      => 'success',
			self::Pending => 'warning',
			self::Alert   => 'danger',
		};
	}

	/**
	 * Get the Bootstrap badge class.
	 *
	 * @return string
	 */
	public function badge_class(): string {
		return 'text-bg-' . $this->color();
	}
}
