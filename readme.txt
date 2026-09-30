=== RRZE Notices ===
Contributors: rrzewebteam
Tags: notices, admin notices, dashboard, multisite
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 4.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Role-based admin notices for WordPress Multisite with per-user dismissal.

== Description ==

RRZE Notices lets administrators create dashboard notices with role-based
visibility. Users can dismiss notices individually.

Features:

* Notice types: info, warning
* Role-based visibility (all site roles, standard roles for network-wide notices)
* Per-user dismissal via AJAX
* Network-wide notices for Multisite (super admins only)
* Automatic expiration via scheduled display period
* Admin bar badge with notice count and dropdown

== Installation ==

Install and update via RRZE Updater or download the latest release from the repository.
Network-activate the plugin on Multisite, then enable it in Network Settings > Notices.

== Changelog ==

= 4.0.0 =
* Removed event system (recurring dates, iCal export)
* Added display period (visible-until date with automatic expiration)
* Simplified data flow (removed notice cache)
* Fixed capabilities (explicit manage_options)
* Security hardening (escaping, inline scripts replaced)
* Added uninstall.php for clean removal