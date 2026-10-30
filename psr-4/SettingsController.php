<?php
/**
 * Settings controller
 *
 * @author    Pronamic
 * @copyright 2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Monitoring
 */

declare(strict_types=1);

namespace Pronamic\Orbis\Monitoring;

/**
 * Settings controller class
 */
final readonly class SettingsController {
	/**
	 * Construct.
	 *
	 * @param string $file Plugin file.
	 */
	public function __construct(
		private string $file
	) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'admin_menu', $this->admin_menu( ... ) );
		\add_action( 'init', $this->register_settings( ... ) );
		\add_action( 'admin_init', $this->add_settings_fields( ... ) );
		\add_filter( 'plugin_action_links_' . \plugin_basename( $this->file ), $this->plugin_action_links( ... ) );
	}

	/**
	 * Get the user agent to use for the monitor requests.
	 *
	 * @link https://github.com/pronamic/wp-orbis-monitoring/issues/10
	 * @return string
	 */
	public function get_user_agent(): string {
		$user_agent = (string) \get_option( 'orbis_monitoring_user_agent' );

		// An explicitly emptied setting also falls back to the default.
		if ( '' === $user_agent ) {
			return $this->get_default_user_agent();
		}

		return $user_agent;
	}

	/**
	 * Get the number of seconds to wait for a response of a monitor request.
	 *
	 * @return int
	 */
	public function get_timeout(): int {
		return \min( 60, \max( 1, (int) \get_option( 'orbis_monitoring_timeout', 10 ) ) );
	}

	/**
	 * Get the number of failed checks in a row before an alert is raised.
	 *
	 * @return int
	 */
	public function get_failure_threshold(): int {
		return \max( 1, (int) \get_option( 'orbis_monitoring_failure_threshold', 3 ) );
	}

	/**
	 * Get the reminder interval in minutes while an alert is open, `0` disables reminders.
	 *
	 * @return int
	 */
	public function get_reminder_interval(): int {
		return \max( 0, (int) \get_option( 'orbis_monitoring_reminder_interval', 0 ) );
	}

	/**
	 * Get the number of days to keep individual check responses.
	 *
	 * @return int
	 */
	public function get_response_retention_days(): int {
		return \max( 1, (int) \get_option( 'orbis_monitoring_response_retention_days', 30 ) );
	}

	/**
	 * Get the default user agent, an honest product token identifying Orbis Monitoring.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9110#name-user-agent
	 * @return string
	 */
	private function get_default_user_agent(): string {
		$data = \get_file_data( $this->file, [ 'Version' => 'Version' ] );

		return 'OrbisMonitoring/' . $data['Version'];
	}

	/**
	 * Admin menu.
	 *
	 * @return void
	 */
	private function admin_menu(): void {
		\add_options_page(
			\__( 'Orbis Monitoring', 'orbis-monitoring' ),
			\__( 'Orbis Monitoring', 'orbis-monitoring' ),
			'manage_options',
			'orbis-monitoring',
			$this->page( ... )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	private function register_settings(): void {
		\register_setting(
			'orbis-monitoring',
			'orbis_monitoring_user_agent',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $this->get_default_user_agent(),
			]
		);

		\register_setting(
			'orbis-monitoring',
			'orbis_monitoring_timeout',
			[
				'type'              => 'integer',
				'sanitize_callback' => fn( $value ) => \min( 60, \max( 1, (int) $value ) ),
				'default'           => 10,
			]
		);

		\register_setting(
			'orbis-monitoring',
			'orbis_monitoring_failure_threshold',
			[
				'type'              => 'integer',
				'sanitize_callback' => fn( $value ) => \max( 1, (int) $value ),
				'default'           => 3,
			]
		);

		\register_setting(
			'orbis-monitoring',
			'orbis_monitoring_reminder_interval',
			[
				'type'              => 'integer',
				'sanitize_callback' => fn( $value ) => \max( 0, (int) $value ),
				'default'           => 0,
			]
		);

		\register_setting(
			'orbis-monitoring',
			'orbis_monitoring_response_retention_days',
			[
				'type'              => 'integer',
				'sanitize_callback' => fn( $value ) => \max( 1, (int) $value ),
				'default'           => 30,
			]
		);
	}

	/**
	 * Add settings fields.
	 *
	 * @return void
	 */
	private function add_settings_fields(): void {
		\add_settings_section(
			'orbis_monitoring_general',
			\__( 'General', 'orbis-monitoring' ),
			'__return_false',
			'orbis-monitoring'
		);

		\add_settings_field(
			'orbis_monitoring_user_agent',
			\__( 'User Agent', 'orbis-monitoring' ),
			$this->field_user_agent( ... ),
			'orbis-monitoring',
			'orbis_monitoring_general',
			[
				'label_for' => 'orbis_monitoring_user_agent',
			]
		);

		\add_settings_field(
			'orbis_monitoring_timeout',
			\__( 'Timeout', 'orbis-monitoring' ),
			$this->field_timeout( ... ),
			'orbis-monitoring',
			'orbis_monitoring_general',
			[
				'label_for' => 'orbis_monitoring_timeout',
			]
		);

		\add_settings_section(
			'orbis_monitoring_notifications',
			\__( 'Notifications', 'orbis-monitoring' ),
			'__return_false',
			'orbis-monitoring'
		);

		\add_settings_field(
			'orbis_monitoring_failure_threshold',
			\__( 'Failure Threshold', 'orbis-monitoring' ),
			$this->field_failure_threshold( ... ),
			'orbis-monitoring',
			'orbis_monitoring_notifications',
			[
				'label_for' => 'orbis_monitoring_failure_threshold',
			]
		);

		\add_settings_field(
			'orbis_monitoring_reminder_interval',
			\__( 'Reminder Interval', 'orbis-monitoring' ),
			$this->field_reminder_interval( ... ),
			'orbis-monitoring',
			'orbis_monitoring_notifications',
			[
				'label_for' => 'orbis_monitoring_reminder_interval',
			]
		);

		\add_settings_section(
			'orbis_monitoring_data',
			\__( 'Data', 'orbis-monitoring' ),
			'__return_false',
			'orbis-monitoring'
		);

		\add_settings_field(
			'orbis_monitoring_response_retention_days',
			\__( 'Response Retention', 'orbis-monitoring' ),
			$this->field_response_retention_days( ... ),
			'orbis-monitoring',
			'orbis_monitoring_data',
			[
				'label_for' => 'orbis_monitoring_response_retention_days',
			]
		);
	}

	/**
	 * Plugin action links.
	 *
	 * @param array<string, string> $links Links.
	 * @return array<string, string>
	 */
	private function plugin_action_links( $links ) {
		$settings_link = \sprintf(
			'<a href="%s">%s</a>',
			\esc_url( \admin_url( 'options-general.php?page=orbis-monitoring' ) ),
			\esc_html__( 'Settings', 'orbis-monitoring' )
		);

		return [ 'settings' => $settings_link ] + $links;
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	private function page(): void {
		?>
		<div class="wrap">
			<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">
				<?php

				\settings_fields( 'orbis-monitoring' );

				\do_settings_sections( 'orbis-monitoring' );

				\submit_button();

				?>
			</form>
		</div>
		<?php
	}

	/**
	 * User agent field.
	 *
	 * @return void
	 */
	private function field_user_agent(): void {
		$default_user_agent = $this->get_default_user_agent();

		$current_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		\printf(
			'<input id="orbis_monitoring_user_agent" name="orbis_monitoring_user_agent" value="%s" placeholder="%s" type="text" class="large-text code" />',
			\esc_attr( (string) \get_option( 'orbis_monitoring_user_agent' ) ),
			\esc_attr( $default_user_agent )
		);

		?>
		<p class="description">
			<?php

			\printf(
				/* translators: %s: default user agent */
				\esc_html__( 'The user agent used for the monitor requests. Leave empty to use the default user agent: %s', 'orbis-monitoring' ),
				'<code>' . \esc_html( $default_user_agent ) . '</code>'
			);

			?>
		</p>
		<p class="description">
			<?php

			\printf(
				/* translators: %s: link to list of the latest browser user agents */
				\esc_html__( 'Tip: some websites or firewalls block or treat unknown bots differently. You can also simulate a widely used web browser, see for example %s.', 'orbis-monitoring' ),
				'<a href="https://www.whatismybrowser.com/guides/the-latest-user-agent/" target="_blank">' . \esc_html__( 'the latest user agents', 'orbis-monitoring' ) . '</a>'
			);

			?>
		</p>

		<?php if ( '' !== $current_user_agent ) : ?>

			<p class="description">
				<?php

				\printf(
					/* translators: %s: user agent of the current visitor */
					\esc_html__( 'Your current user agent is: %s', 'orbis-monitoring' ),
					'<code>' . \esc_html( $current_user_agent ) . '</code>'
				);

				?>
			</p>

		<?php endif; ?>
		<?php
	}

	/**
	 * Timeout field.
	 *
	 * @return void
	 */
	private function field_timeout(): void {
		\printf(
			'<input id="orbis_monitoring_timeout" name="orbis_monitoring_timeout" value="%s" type="number" min="1" max="60" step="1" class="small-text" /> %s',
			\esc_attr( (string) $this->get_timeout() ),
			\esc_html__( 'seconds', 'orbis-monitoring' )
		);

		?>
		<p class="description">
			<?php \esc_html_e( 'The maximum number of seconds to wait for a response. A check that takes longer counts as a failed check. A short timeout keeps the queue moving when a site responds slowly.', 'orbis-monitoring' ); ?>
		</p>
		<?php
	}

	/**
	 * Failure threshold field.
	 *
	 * @return void
	 */
	private function field_failure_threshold(): void {
		\printf(
			'<input id="orbis_monitoring_failure_threshold" name="orbis_monitoring_failure_threshold" value="%s" type="number" min="1" step="1" class="small-text" />',
			\esc_attr( (string) $this->get_failure_threshold() )
		);

		?>
		<p class="description">
			<?php \esc_html_e( 'The number of failed checks in a row before an alert is raised and a notification is sent. Monitors check every minute, so a single hiccup does not trigger a notification.', 'orbis-monitoring' ); ?>
		</p>
		<?php
	}

	/**
	 * Reminder interval field.
	 *
	 * @return void
	 */
	private function field_reminder_interval(): void {
		\printf(
			'<input id="orbis_monitoring_reminder_interval" name="orbis_monitoring_reminder_interval" value="%s" type="number" min="0" step="1" class="small-text" /> %s',
			\esc_attr( (string) $this->get_reminder_interval() ),
			\esc_html__( 'minutes', 'orbis-monitoring' )
		);

		?>
		<p class="description">
			<?php \esc_html_e( 'Send a reminder while an alert is still open. Use 0 to only send a notification when the alert is raised and when it is resolved.', 'orbis-monitoring' ); ?>
		</p>
		<?php
	}

	/**
	 * Response retention field.
	 *
	 * @return void
	 */
	private function field_response_retention_days(): void {
		\printf(
			'<input id="orbis_monitoring_response_retention_days" name="orbis_monitoring_response_retention_days" value="%s" type="number" min="1" step="1" class="small-text" /> %s',
			\esc_attr( (string) $this->get_response_retention_days() ),
			\esc_html__( 'days', 'orbis-monitoring' )
		);

		?>
		<p class="description">
			<?php \esc_html_e( 'The number of days to keep the response of each individual check. Hourly summaries of the response times are kept indefinitely.', 'orbis-monitoring' ); ?>
		</p>
		<?php
	}
}
