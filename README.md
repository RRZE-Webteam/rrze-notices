[![Aktuelle Version](https://img.shields.io/github/package-json/v/rrze-webteam/r
rze-notices/main?label=Version)](https://github.com/RRZE-Webteam/rrze-notices)
[![Release                                                                      
Version](https://img.shields.io/github/v/release/rrze-webteam/rrze-notices?label
=Release+Version)](https://github.com/rrze-webteam/rrze-notices/releases/)      
[![GitHub License](https://img.shields.io/github/license/rrze-webteam/rrze-notic
es)](https://github.com/RRZE-Webteam/rrze-notices)                              
[![GitHub issues](https://img.shields.io/github/issues/RRZE-Webteam/rrze-notices
)](https://github.com/RRZE-Webteam/rrze-notices/issues)

# RRZE Notices

Role-based admin notices for WordPress Multisite with per-user dismissal.

## Features

- Notice types: info, warning
- Role-based visibility (all site roles, standard roles for network-wide notices)
- Per-user dismissal via AJAX
- Network-wide notices for Multisite (super admins only)
- Automatic expiration via scheduled display period
- Admin bar badge with notice count and dropdown

## Documentation

https://www.wp.rrze.fau.de/plugins/externe-daten-einbinden/rrze-notices/

## Requirements

- WordPress 6.5+
- PHP 8.2+

## Installation

Download the latest release from https://github.com/RRZE-Webteam/rrze-notices               
Network-activate the plugin on Multisite, then enable it in Network Settings > Notices.

## Multisite

Fully Multisite-capable. Network activation required. 
Super admins can enable the plugin and create network-wide notices via Network Settings > Notices. 
Site admins create local notices visible only on their site.

## Accessibility

Admin notices use semantic WordPress markup. Dismiss buttons are     
keyboard-operable.

## License

[GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html)

## Maintainer

RRZE Webteam ([webmaster@fau.de](mailto:webmaster@fau.de))
                                                                                  
---      