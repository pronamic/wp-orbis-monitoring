<?php
/**
 * Monitor status
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Monitor status enum
 *
 * The value is stored in the `status` column of the monitors table.
 *
 * phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- False positive, `$this` is the enum case.
 */
enum MonitorStatus: string {
	/**
	 * Not checked yet, or the monitor is not published.
	 */
	case Unknown = 'unknown';

	/**
	 * The last check succeeded.
	 */
	case Online = 'online';

	/**
	 * One or more checks failed, but not enough in a row to open an incident.
	 */
	case Pending = 'pending';

	/**
	 * Enough checks failed in a row, an incident is open.
	 */
	case Offline = 'offline';

	/**
	 * Get the label.
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Unknown => \__( 'Unknown', 'orbis-monitoring' ),
			self::Online  => \__( 'Online', 'orbis-monitoring' ),
			self::Pending => \__( 'Pending', 'orbis-monitoring' ),
			self::Offline => \__( 'Offline', 'orbis-monitoring' ),
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
			self::Online  => '🟢',
			self::Pending => '🟠',
			self::Offline => '🔴',
		};
	}

	/**
	 * Get the Bootstrap badge class.
	 *
	 * @return string
	 */
	public function badge_class(): string {
		return match ( $this ) {
			self::Unknown => 'text-bg-secondary',
			self::Online  => 'text-bg-success',
			self::Pending => 'text-bg-warning',
			self::Offline => 'text-bg-danger',
		};
	}
}
