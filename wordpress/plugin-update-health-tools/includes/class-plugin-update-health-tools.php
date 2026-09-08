<?php
/**
 * WordPress-managed updates and optional diagnostic tools.
 *
 * @package Plugin_Update_Health_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PUHT_Dashboard {
	const PAGE = 'plugin-update-health-tools';

	private static function capability() {
		return is_multisite() ? 'manage_network_plugins' : 'manage_options';
	}

	private static function page_url() {
		return is_multisite()
			? network_admin_url( 'plugins.php?page=' . self::PAGE )
			: admin_url( 'tools.php?page=' . self::PAGE );
	}

	public static function register_site_menu() {
		if ( ! is_multisite() ) {
			add_management_page(
				__( 'Plugin Update Checker / Health Tools', 'plugin-update-health-tools' ),
				__( 'Plugin Health Tools', 'plugin-update-health-tools' ),
				self::capability(),
				self::PAGE,
				array( __CLASS__, 'render' )
			);
		}
	}

	public static function register_network_menu() {
		add_submenu_page(
			'plugins.php',
			__( 'Plugin Update Checker / Health Tools', 'plugin-update-health-tools' ),
			__( 'Plugin Health Tools', 'plugin-update-health-tools' ),
			self::capability(),
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	private static function authorize() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to manage plugin health tools.', 'plugin-update-health-tools' ), '', array( 'response' => 403 ) );
		}
	}

	private static function has_update_results( $updates ) {
		return is_object( $updates )
			&& isset( $updates->response, $updates->no_update )
			&& is_array( $updates->response )
			&& is_array( $updates->no_update );
	}

	public static function check_updates() {
		self::authorize();
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to check plugin updates.', 'plugin-update-health-tools' ), '', array( 'response' => 403 ) );
		}
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'Use the dashboard form to check updates.', 'plugin-update-health-tools' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( 'puht_check_updates' );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$previous = get_site_transient( 'update_plugins' );
		wp_clean_plugins_cache( false );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		$updates = get_site_transient( 'update_plugins' );
		$success = self::has_update_results( $updates );

		// Core records an attempt timestamp even on failure; retain the last known results.
		if ( ! $success && is_object( $previous ) ) {
			set_site_transient( 'update_plugins', $previous );
		}

		wp_safe_redirect( add_query_arg( 'puht_check', $success ? 'success' : 'failed', self::page_url() ) );
		exit;
	}

	private static function tools() {
		return array(
			'wp-rollback' => array(
				'name'        => 'WP Rollback',
				'file'        => 'wp-rollback/wp-rollback.php',
				'description' => __( 'Return a supported WordPress.org plugin or theme to a previous version after a problematic update. Back up files and the database first; a rollback does not undo database migrations.', 'plugin-update-health-tools' ),
				'help'        => __( 'Use the Rollback action on the Installed Plugins screen. Theme rollback availability depends on WP Rollback and the theme source.', 'plugin-update-health-tools' ),
			),
			'health-check' => array(
				'name'        => 'Health Check & Troubleshooting',
				'file'        => 'health-check/health-check.php',
				'description' => __( 'Isolate plugin and theme conflicts in a troubleshooting session for your logged-in user without changing the normal experience for visitors.', 'plugin-update-health-tools' ),
				'help'        => __( 'Open Tools → Site Health in the site dashboard, then use the Troubleshooting tab. Follow that tool’s instructions to enable plugins one at a time and exit troubleshooting when finished.', 'plugin-update-health-tools' ),
			),
			'query-monitor' => array(
				'name'        => 'Query Monitor',
				'file'        => 'query-monitor/query-monitor.php',
				'description' => __( 'Inspect PHP errors, deprecated notices, database queries, and other diagnostics for the current request.', 'plugin-update-health-tools' ),
				'help'        => __( 'After activation, visit the affected page while logged in as an administrator (super administrator on multisite) and open Query Monitor from the admin toolbar. It is not a historical error log; disable it when diagnostics are finished.', 'plugin-update-health-tools' ),
			),
		);
	}

	private static function tool_action( $slug, $tool, $installed ) {
		$file = $tool['file'];
		if ( ! isset( $installed[ $file ] ) ) {
			if ( current_user_can( 'install_plugins' ) ) {
				$url = wp_nonce_url(
					add_query_arg(
						array( 'action' => 'install-plugin', 'plugin' => $slug ),
						network_admin_url( 'update.php' )
					),
					'install-plugin_' . $slug
				);
				self::button( $url, __( 'Install from WordPress.org', 'plugin-update-health-tools' ) );
			} else {
				esc_html_e( 'Ask an administrator with plugin installation permission to install this tool.', 'plugin-update-health-tools' );
			}
			return;
		}

		$active = is_multisite() ? is_plugin_active_for_network( $file ) : is_plugin_active( $file );
		if ( ! $active ) {
			if ( current_user_can( 'activate_plugin', $file ) ) {
				$args = array( 'action' => 'activate', 'plugin' => $file );
				if ( is_multisite() ) {
					$args['networkwide'] = 1;
				}
				self::button(
					wp_nonce_url( add_query_arg( $args, network_admin_url( 'plugins.php' ) ), 'activate-plugin_' . $file ),
					is_multisite() ? __( 'Network activate', 'plugin-update-health-tools' ) : __( 'Activate', 'plugin-update-health-tools' )
				);
			} else {
				esc_html_e( 'You do not have permission to activate this tool.', 'plugin-update-health-tools' );
			}
			return;
		}

		if ( 'health-check' === $slug && current_user_can( 'view_site_health_checks' ) ) {
			self::button( admin_url( 'site-health.php?tab=troubleshoot' ), __( 'Open troubleshooting', 'plugin-update-health-tools' ) );
		} elseif ( 'query-monitor' === $slug && current_user_can( 'view_query_monitor' ) ) {
			self::button( admin_url( 'index.php' ), __( 'Open dashboard and use Query Monitor toolbar', 'plugin-update-health-tools' ) );
		} else {
			self::button( network_admin_url( 'plugins.php' ), __( 'Open Installed Plugins', 'plugin-update-health-tools' ) );
		}
	}

	private static function button( $url, $label ) {
		printf( '<a class="button" href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
	}

	public static function render() {
		self::authorize();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$installed = get_plugins();
		$updates   = get_site_transient( 'update_plugins' );
		$checked   = self::has_update_results( $updates );
		$available = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response )
			? array_intersect_key( $updates->response, $installed )
			: array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Plugin Update Checker / Health Tools', 'plugin-update-health-tools' ); ?></h1>
			<p><?php esc_html_e( 'Review WordPress plugin update information and manage optional rollback, troubleshooting, and diagnostic tools from one place.', 'plugin-update-health-tools' ); ?></p>
			<?php if ( isset( $_GET['puht_check'] ) && 'success' === $_GET['puht_check'] ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Plugin update information refreshed. No plugins were updated.', 'plugin-update-health-tools' ); ?></p></div>
			<?php elseif ( isset( $_GET['puht_check'] ) && 'failed' === $_GET['puht_check'] ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'WordPress could not refresh plugin updates. Check connectivity to WordPress.org and try again. Any previous cached results have been retained.', 'plugin-update-health-tools' ); ?></p></div>
			<?php endif; ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Before updating or rolling back, back up your database and files and test on staging. Older releases may contain security vulnerabilities. These tools do not replace a backup or repair database migrations.', 'plugin-update-health-tools' ); ?></p></div>

			<h2><?php esc_html_e( 'Plugin updates', 'plugin-update-health-tools' ); ?></h2>
			<?php if ( $checked && ! empty( $updates->last_checked ) ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: localized time of the most recent WordPress update check attempt. */
						esc_html__( 'Last WordPress check attempt: %s. Results are cached and may be out of date.', 'plugin-update-health-tools' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $updates->last_checked ) )
					);
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'No completed update check is cached. Check for updates before relying on this list.', 'plugin-update-health-tools' ); ?></p>
			<?php endif; ?>
			<p>
				<?php
				printf(
					/* translators: %d: number of plugins with a cached update offer. */
					esc_html__( 'Available plugin updates in the cache: %d', 'plugin-update-health-tools' ),
					count( $available )
				);
				?>
			</p>
			<?php if ( current_user_can( 'update_plugins' ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="puht_check_updates">
					<?php wp_nonce_field( 'puht_check_updates' ); ?>
					<?php submit_button( __( 'Check for plugin updates', 'plugin-update-health-tools' ), 'secondary', 'submit', false ); ?>
					<?php self::button( network_admin_url( 'update-core.php' ), __( 'Review and apply updates in WordPress', 'plugin-update-health-tools' ) ); ?>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Update checks and installation are restricted by your permissions or WordPress configuration.', 'plugin-update-health-tools' ); ?></p>
			<?php endif; ?>
			<p><?php esc_html_e( 'Checking uses the WordPress update service and sends the standard installed-plugin information to WordPress.org. Updates, filesystem credentials, and compatibility checks are handled by WordPress. A missing update offer is not a security or compatibility guarantee.', 'plugin-update-health-tools' ); ?></p>
			<table class="widefat striped">
				<caption class="screen-reader-text"><?php esc_html_e( 'Installed plugins and cached update information', 'plugin-update-health-tools' ); ?></caption>
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Plugin', 'plugin-update-health-tools' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Installed version', 'plugin-update-health-tools' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'plugin-update-health-tools' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Available version', 'plugin-update-health-tools' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $installed as $file => $plugin ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $plugin['Name'] ); ?></th>
						<td><?php echo esc_html( $plugin['Version'] ); ?></td>
						<td>
							<?php
							if ( is_plugin_active_for_network( $file ) ) {
								esc_html_e( 'Network active', 'plugin-update-health-tools' );
							} elseif ( is_multisite() ) {
								esc_html_e( 'Not network active (may be active on individual sites)', 'plugin-update-health-tools' );
							} else {
								echo is_plugin_active( $file ) ? esc_html__( 'Active', 'plugin-update-health-tools' ) : esc_html__( 'Inactive', 'plugin-update-health-tools' );
							}
							?>
						</td>
						<td>
							<?php
							if ( isset( $available[ $file ]->new_version ) ) {
								echo esc_html( $available[ $file ]->new_version );
							} else {
								$version_checked = $checked && isset( $updates->checked[ $file ] ) && (string) $updates->checked[ $file ] === (string) $plugin['Version'];
								// WordPress 6.5–6.7 may omit checked after a successful refresh.
								$no_update = $checked && isset( $updates->no_update[ $file ]->new_version ) && (string) $updates->no_update[ $file ]->new_version === (string) $plugin['Version'];
								echo $version_checked || $no_update
									? esc_html__( 'No update reported', 'plugin-update-health-tools' )
									: esc_html__( 'Not checked', 'plugin-update-health-tools' );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Rollback and health tools', 'plugin-update-health-tools' ); ?></h2>
			<p><?php esc_html_e( 'These are separate, optional third-party plugins, not bundled copies. Install or activate each explicitly below. Installation requires internet access and the permissions allowed by your host.', 'plugin-update-health-tools' ); ?></p>
			<?php foreach ( self::tools() as $slug => $tool ) : ?>
				<div class="card">
					<h3><?php echo esc_html( $tool['name'] ); ?></h3>
					<p><?php echo esc_html( $tool['description'] ); ?></p>
					<p><strong>
						<?php
						if ( ! isset( $installed[ $tool['file'] ] ) ) {
							esc_html_e( 'Not installed', 'plugin-update-health-tools' );
						} elseif ( is_plugin_active_for_network( $tool['file'] ) ) {
							esc_html_e( 'Network active', 'plugin-update-health-tools' );
						} elseif ( is_multisite() ) {
							esc_html_e( 'Installed; not network active', 'plugin-update-health-tools' );
						} else {
							echo is_plugin_active( $tool['file'] ) ? esc_html__( 'Active', 'plugin-update-health-tools' ) : esc_html__( 'Installed; inactive', 'plugin-update-health-tools' );
						}
						?>
					</strong></p>
					<p><?php self::tool_action( $slug, $tool, $installed ); ?></p>
					<p><?php echo esc_html( $tool['help'] ); ?></p>
					<p><a href="<?php echo esc_url( 'https://wordpress.org/plugins/' . $slug . '/' ); ?>"><?php esc_html_e( 'Details on WordPress.org', 'plugin-update-health-tools' ); ?></a></p>
				</div>
			<?php endforeach; ?>
			<h2><?php esc_html_e( 'Safe troubleshooting workflow', 'plugin-update-health-tools' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Create and verify a full backup, then reproduce the problem on staging if possible.', 'plugin-update-health-tools' ); ?></li>
				<li><?php esc_html_e( 'Use Health Check troubleshooting mode to isolate a plugin or theme conflict for your session. Keep its Site Health tab open: this dashboard may disappear when other plugins are disabled in that session. Must-use plugins remain enabled and network plugin isolation may be limited.', 'plugin-update-health-tools' ); ?></li>
				<li><?php esc_html_e( 'Inspect the affected request with Query Monitor. Do not publish diagnostic output that may contain private data.', 'plugin-update-health-tools' ); ?></li>
				<li><?php esc_html_e( 'If an update caused the problem, review a supported previous release in WP Rollback, confirm the rollback there, and retest. Plan to return to a secure supported version.', 'plugin-update-health-tools' ); ?></li>
				<li><?php esc_html_e( 'Exit troubleshooting mode and disable diagnostic tools when finished. If wp-admin is inaccessible, use your host’s recovery tools or restore your backup instead.', 'plugin-update-health-tools' ); ?></li>
			</ol>
		</div>
		<?php
	}
}
