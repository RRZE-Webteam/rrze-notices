=== RRZE Notices ===
Contributors: rrzewebteam
Tags: notices, admin notices, dashboard, multisite
Requires at least: 6.5
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 4.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Display important notices on the WordPress dashboard.
Target notices to specific user roles and let each user dismiss them individually. Supports both single site and
Multisite networks.

== Description ==

RRZE Notices lets administrators create dashboard notices that appear as colored
bars at the top of the WordPress admin area. Each notice has a type (Info,
Warning, or Critical), an optional expiration date, and visibility settings that
control which user roles can see it. Users can dismiss notices individually.

The plugin is built for WordPress Multisite. It must be network-activated and
enabled by a super admin in the network settings. Once enabled, the super admin
can choose whether site admins may also create notices on their own sites.

**Features:**

* Three notice types: Info, Warning, and Critical
* Modal confirmation for critical notices — blocks the admin area until acknowledged
* Role-based visibility — target all users or specific roles
* Per-user dismissal via AJAX
* Network-wide notices visible across all sites (super admins only)
* Optional event date display (start and end) shown on the notice
* Automatic expiration via a configurable visible-until date
* Admin bar badge with notice count and dropdown
* Read-only overview of active network notices for site admins

**Roles and permissions:**

* Super admins: Enable the plugin, set the access level, create network-wide
  notices, manage notices on any site.
* Site admins: Create and manage local notices on their own site (when the
  network setting allows it). View active network-wide notices in a read-only
  list.
* Editors and other roles: See notices on the dashboard according to visibility
  settings. Dismiss notices individually. Cannot create or edit notices.

== Installation ==

1. Install via RRZE Updater or download the latest release from the GitHub
   repository.
2. Network-activate the plugin on your Multisite installation.
3. Go to Network Admin > Settings > RRZE Notices.
4. Set "Notices is enabled" and choose the access level.
5. Save changes.

The plugin menu ("Notices") now appears on each site for authorized users.

== Frequently Asked Questions ==

= Why do I not see the Notices menu? =

The plugin must be network-activated and enabled in Network Admin > Settings >
RRZE Notices. If the access level is set to "Super Admins only", site admins
will not see the menu either.

= Can editors create notices? =

No. Only users with the `manage_options` capability (site admins) or
`manage_network_options` (super admins) can create notices, depending on the
network access setting.

= What happens when a notice expires? =

An hourly cron job checks all published notices. When the visible-until date has
passed, the notice is moved to a custom "Expired" status and removed from the
dashboard and the network index.

= Can users see dismissed notices again? =

Site admins can view active network-wide notices at any time via the "Network
Notices" submenu. Dismissed local notices reappear when the notice is
republished.

= Does this plugin work without Multisite? =

The plugin can be activated on a single site, but the network settings page and
network-wide notices are only available on Multisite installations.

== Changelog ==

= 4.0.0 =
* Removed event system (recurring dates, iCal export)
* Added display period (visible-until date with automatic expiration)
* Simplified data flow (removed notice cache, direct post/meta reads)
* Fixed capabilities (explicit manage_options, no unintended editor access)
* Security hardening (escaping, inline scripts replaced with enqueued assets)
* Added uninstall.php for clean removal
* Added read-only network notices overview for site admins
* Added admin bar badge with notice count and dropdown
* Added modal confirmation mode for critical notices