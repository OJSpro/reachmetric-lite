# Reachmetric Lite

Reachmetric Lite is a free Google Analytics 4 (GA4) / OJS usage statistics plugin for Open Journal Systems (OJS 3.4+). It cleanly displays "Abstract Views" and "PDF Downloads" badges directly on your Issue TOC and Article Details pages, keeping your authors and readers informed about manuscript impact.

## Features
- **Zero-Load Time:** Uses OJS's built-in metrics system for instant rendering.
- **Dynamic Styling:** Automatically inherits your OJS theme's layout padding and margins for perfect alignment.
- **Customizable Badges:** Change badge colors, backgrounds, and hover effects directly from the plugin settings.

## Installation Instructions

1. Download the latest `reachmetricLite.tar.gz` release.
2. In your OJS Dashboard, go to **Settings > Website > Plugins**.
3. Click **Upload A New Plugin** and upload the `.tar.gz` file.
4. Once installed, find **Reachmetric Lite** in the list of generic plugins and check the box to **Enable** it.
5. Click the blue arrow next to the plugin name and select **Settings** to configure your badges.

> **Note on Permissions:** If the OJS web uploader fails with a "Failed to open stream" error, your server has restrictive file permissions. You can install it manually by extracting the archive and uploading the `reachmetricLite` folder to `plugins/generic/` via FTP/cPanel.

## Upgrade to Reachmetric Pro

Want advanced, real-time Google Analytics 4 (GA4) data directly in your OJS dashboard? 

**[Upgrade to Reachmetric Pro](https://ojspro.com/plugin/ojs/reachmetric-pro/?utm_medium=plugin_setting_page&utm_campaign=direct)** to unlock:
- **Real-time GA4 Sync:** Accurate readership analytics pulled directly from Google.
- **Country & City Analytics:** See exactly where your readers are located geographically.
- **Author Dashboards:** Let authors track their own article performance.
- **PDF Tracking:** Track precise PDF downloads accurately using GA4 events.
- **Export & Reporting:** Generate comprehensive readership reports for editorial boards.

## License
This plugin is licensed under the GNU General Public License v3.0 (GPLv3). See the `LICENSE` file for details.
