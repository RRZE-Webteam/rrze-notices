[![Aktuelle Version](https://img.shields.io/github/package-json/v/rrze-webteam/rrze-notices/main?label=Version)](https://github.com/RRZE-Webteam/rrze-notices)
[![Release Version](https://img.shields.io/github/v/release/rrze-webteam/rrze-notices?label=Release+Version)](https://github.com/rrze-webteam/rrze-notices/releases/)
[![GitHub License](https://img.shields.io/github/license/rrze-webteam/rrze-notices)](https://github.com/RRZE-Webteam/rrze-notices)
[![GitHub issues](https://img.shields.io/github/issues/RRZE-Webteam/rrze-notices)](https://github.com/RRZE-Webteam/rrze-notices/issues)

# RRZE Notices

Display important notices on the WordPress dashboard.  
Target notices to specific user roles and let each user dismiss them individually. Supports both single site and
Multisite networks.

## Documentation

https://www.wp.rrze.fau.de/plugins/benutzer-und-zugriffsverwaltung/rrze-notices/

## Features

- Three notice types: Info, Warning, and Critical
- Role-based visibility — target all users, specific roles, or standard roles for network-wide notices
- Per-user dismissal via AJAX — each user can hide a notice independently
- Network-wide notices visible across all sites (super admins only)
- Optional event date display (start and end) shown on the notice
- Automatic expiration via a configurable display period (visible-until date)
- Admin bar badge showing the current notice count with a dropdown list
- Read-only overview of active network notices for site admins

## How It Works

RRZE Notices adds a custom post type called "Notice" to the WordPress admin. Each notice has a title, a short message, a type (Info, Warning, or Critical), an optional expiration date, and visibility settings that control which user roles can see it.

Notices appear as colored bars at the top of the WordPress dashboard. Users can dismiss them individually. A badge in the admin bar shows how many active notices are available.

On a Multisite network the plugin must be activated network-wide. A super admin enables it and chooses whether site admins may create their own notices. Super admins can additionally mark a notice as network-wide so it appears on every site in the network.

## Requirements

- WordPress 6.5 or later
- PHP 8.2 or later

## Installation

Install and update via [RRZE Updater](https://github.com/RRZE-Webteam/rrze-updater) or download the latest release from the [GitHub repository](https://github.com/RRZE-Webteam/rrze-notices).

1. Network-activate the plugin on your Multisite installation.
2. Go to **Network Admin > Settings > RRZE Notices**.
3. Set "Notices is enabled" and choose the access level.
4. Save changes.

The plugin menu ("Notices") now appears on each site for authorized users.

## Roles and Permissions

| Role | Permissions |
| --- | --- |
| **Super Admin** | Enable/disable the plugin network-wide. Set the access level. Create, edit, and delete notices on any site. Mark notices as network-wide. |
| **Site Admin** | Create, edit, and delete local notices on their own site (only when the network access setting allows it). View a read-only list of active network-wide notices. |
| **Editor and below** | See notices on the dashboard according to visibility settings. Dismiss notices individually. Cannot create or edit notices. |

The access level is configured in the network settings. It maps to the WordPress capability `manage_options` (site admins) or `manage_network_options` (super admins only).

## Multisite

RRZE Notices is designed for WordPress Multisite. It can be activated on a single site, but the network settings page and network-wide notices are only available on Multisite installations.

- **Network settings** (enable, access level) are stored as a single network option (`rrze_notices_site`) and can only be changed by super admins in Network Admin.
- **Local notices** are regular posts of the `notice` custom post type, stored on the site where they are created. They are visible only on that site.
- **Network-wide notices** are created on one site and displayed on all other public sites in the network. Only super admins can create them. A lightweight index in the network option stores which notices are network-wide; the actual content is read from the originating site via `switch_to_blog()`.
- **Expiration** is handled by an hourly cron job that sets expired notices to a custom `expired` post status and removes them from the network index.
- **Uninstall** removes all notice posts, user dismissal metadata, the network option, and the cron event.

## Accessibility

- Dashboard notices use semantic HTML with WordPress-native notice markup.
- Dismiss buttons are keyboard-operable and use the standard WordPress dismiss pattern.
- Notice types are distinguished by border color and a text label, not by color alone.
- The admin bar dropdown is navigable with keyboard.

## External Dependencies

- [CMB2](https://github.com/CMB2/CMB2) (bundled in `vendor/`) — metabox framework for the notice editor fields. Will be replaced by native metaboxes in a future release.
- No external services, CDNs, or remote API calls.

## Cookies and Browser Storage

This plugin does not set cookies, use browser local/session storage, or load external tracking resources.

## Hooks

The plugin provides one filter for extending the HTML allowlist used in notice content:

```php
apply_filters('rrze_notices_allowed_html', array $allowed);
```

This filter receives the `wp_kses`-compatible tag/attribute array and must return one in the same format.

## Support and Feedback

- Issues: https://github.com/RRZE-Webteam/rrze-notices/issues
- Contact: RRZE Webteam ([webmaster@fau.de](mailto:webmaster@fau.de))

## License

[GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html)

## Maintainer

RRZE Webteam ([webmaster@fau.de](mailto:webmaster@fau.de))