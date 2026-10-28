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
	 * Get the default user agent, an honest product token identifying Orbis Monitoring.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9110#name-user-agent
	 * @return string
	 */
	private function get_default_user_agent(): string {
		$data = \get_file_data( $this->file, [ 'Version' => 'Version' ] );

		return \sprintf(
			'OrbisMonitoring/%s (+https://www.pronamic.eu/plugins/orbis-monitoring/)',
			$data['Version']
		);
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
}
