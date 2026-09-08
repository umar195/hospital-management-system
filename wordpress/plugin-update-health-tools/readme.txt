=== Plugin Update Checker / Health Tools ===
Tags: updates, health-check, troubleshooting, rollback, diagnostics
Requires at least: 6.5
Tested up to: 7.0.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Check plugin updates and manage optional WP Rollback, Health Check & Troubleshooting, and Query Monitor tools from one admin dashboard.

== Description ==

An administrator-only companion dashboard for WordPress plugin maintenance.

* Lists installed plugins, versions, activation status, and cached update offers.
* Refreshes update information on request without automatically updating plugins.
* Sends update installation to the standard WordPress Updates screen.
* Detects three optional tools and offers native WordPress installation/activation:
  * WP Rollback: select a supported previous release after a problematic update.
  * Health Check & Troubleshooting: isolate plugin/theme conflicts in your session.
  * Query Monitor: investigate PHP errors, deprecated notices, and database queries.
* Provides backup warnings and a safe troubleshooting checklist.

The three third-party tools are NOT bundled, automatically installed, or reimplemented.
Their own interfaces perform rollback, troubleshooting, and diagnostics. Each tool
has its own WordPress/PHP requirements and feature availability. This dashboard
works without them and does not collect or store their diagnostic output.

This is independent of the hospital management application in the source repository.
It does not turn that application into a WordPress plugin or access patient records.

== Installation ==

1. In WordPress, open Plugins > Add New Plugin > Upload Plugin.
2. Upload plugin-update-health-tools.zip (not the entire repository ZIP), install, and activate.
3. Open Tools > Plugin Health Tools.
4. Review updates and explicitly install/activate only the optional tools you need.

Alternatively copy the plugin-update-health-tools directory into wp-content/plugins.
For multisite, network activate this dashboard, then open Network Admin > Plugins >
Plugin Health Tools. Network activation of an optional tool is explicitly labelled.
Site-specific diagnostics and Site Health are accessed from the relevant site's admin.

Only administrators (manage_options), or network plugin administrators on multisite,
can open the dashboard. WordPress capabilities independently control update checks,
installation, and activation. Host restrictions such as DISALLOW_FILE_MODS are respected.

== Safe usage ==

1. Back up files and the database and verify recovery before updates or rollbacks.
2. Reproduce the issue on staging when possible.
3. Open Tools > Site Health > Troubleshooting after activating Health Check.
   Enable plugins individually in that session to find the conflict. Keep Site Health
   open: this dashboard may disappear when troubleshooting disables other plugins.
   Must-use plugins remain enabled; network plugin isolation may be limited.
4. Activate Query Monitor and visit the affected page as an administrator (super
   administrator on multisite). Open its toolbar panel to inspect that request;
   it is not a historical error log.
5. For a problematic update, use the Rollback action on Installed Plugins for a
   supported plugin, review the selected version, and confirm in WP Rollback.
6. Retest, exit troubleshooting mode, and disable diagnostic tools when finished.

Rolling back files does not reverse database changes and is not a backup restore.
Older versions may contain vulnerabilities. Free rollback supports WordPress.org
plugins/themes; premium/custom packages may need separate vendor support.
If wp-admin cannot load, use your host's recovery facilities or restore a backup.
Do not share diagnostics publicly: they can contain private information.

== Privacy and external services ==

The dashboard reads WordPress's shared update_plugins cache. Clicking "Check for
plugin updates" invokes WordPress's built-in update service, which sends standard
plugin inventory, versions, and site information to api.wordpress.org. WordPress
also performs its usual scheduled checks independently of this plugin.

Installing optional tools downloads packages from WordPress.org through WordPress.
Each tool has its own privacy and service behavior; review its official details:

* https://wordpress.org/plugins/wp-rollback/
* https://wordpress.org/plugins/health-check/
* https://wordpress.org/plugins/query-monitor/
* https://wordpress.org/about/privacy/

There is no custom telemetry, remote update endpoint, background scanner, log
storage, or database schema. Deactivating/deleting this dashboard leaves optional
plugins and WordPress's native update cache intact. Manage optional tools separately
from Installed Plugins.

== Frequently Asked Questions ==

= Does "No update reported" mean a plugin is secure or fully compatible? =

No. Results are cached, and plugins without an update provider may never report an
offer. Failed refreshes retain previous results and display a warning. WordPress's
timestamp records a check attempt, not a guarantee of a successful current check.

= Why is an installation or update action unavailable? =

Your role, multisite configuration, filesystem permissions, or host may restrict
plugin changes. WordPress handles credentials, compatibility errors, and download
failures on its native screens. The plugin does not bypass those checks.

= Can I use it offline? =

Cached inventory remains available. Fresh update checks and installation of
optional tools need access to WordPress.org. Install tool ZIPs manually if necessary.

== Changelog ==

= 1.0.0 =
* Initial release: update inventory, explicit refresh, and optional health-tool management.
