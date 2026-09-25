=== NHR Advanced Options Table Manager & Autoload Optimizer ===
Contributors: nhrrob  
Tags: database, autoload, wp_options, transients, cleanup
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Clean up wp_options: find autoload bloat, delete expired transients and orphaned options, and back up the table from one dashboard.

== Description ==

🚀 [GitHub Repository](https://github.com/nhrrob/nhrrob-options-table-manager) – Found a bug or have a feature request? Let us know!  
💬 [Slack Community](https://join.slack.com/t/nhrrob/shared_invite/zt-2m3nyrl1f-eKv7wwJzsiALcg0nY6~e0Q) – Got questions or just want to chat? Come hang out with us on Slack!

https://www.youtube.com/watch?v=le89m1qfb0U

Is your `wp_options` table bloated and slowing down your site? You're not alone!
Install this plugin to get a clear view of the table, plus the tools to clean and optimize it.

`<?php echo 'Small WP Options Table, Clean Database!'; ?>`

### 🚀 A Powerful Yet Simple Solution to Manage wp_options
Tired of an overloaded `wp_options` table slowing down your WordPress site? **NHR Options Table Manager** provides a clean, organized, and optimized way to view and manage your options table efficiently. Get detailed analytics, edit and delete options, and keep your database lean and performant.

### ✨ Key Features
- **Database Health Dashboard** – An at-a-glance scorecard (0–100) summarizing autoload size, transient and orphan counts, and last backup, with prioritized one-click recommendations.
- **Activity Log** – A recent-activity summary on the Dashboard plus a dedicated, searchable and filterable Activity tab showing every change and who made it.
- **Modern React Interface** – A fast, dashboard-first admin app organized into Dashboard, Browse, Optimize, Tools, Integrations, and Settings.
- **Autoload Usage Tracker** – Records which autoloaded options are actually used on real front-end page loads, then flags the ones that are never used so you can safely turn off their autoload; filter the list by usage status and bulk-disable every unused option at once.
- **Autoload Health Check** – Analyze total autoloaded data size and identify heavy options that slow down your site.
- **Transients Manager** – Dedicated view of every transient with size, expiration, status, and its guessed owner (a plugin, theme, or WordPress core); filter by status or owner, add and edit values (with expiration) directly, and select rows to bulk delete.
- **Manage Options** – Add, edit, and delete options with sortable columns, search, pagination, and bulk delete; filter the list by guessed owner (a plugin, theme, or WordPress core).
- **Usermeta, Postmeta, Commentmeta & Termmeta Support** – Browse, add, edit, and delete user, post, comment, and term meta entries just like options; narrow usermeta or postmeta to a single user or post by searching its name/title in a type-ahead picker.
- **Serialized Data Handling** – Serialized and JSON values open as structured data you can edit safely, and are saved back in their original format.
- **Scheduled Backups & Snapshots** – Create restorable snapshots of your `wp_options` table manually or on a daily/weekly schedule, with automatic snapshots taken before Search & Replace and Import.
- **Option History & Rollback** – Track all changes to individual options and restore previous versions instantly.
- **Orphan Scanner** – Find and clean up leftovers from uninstalled plugins.
- **Options Table Analytics** – See every option grouped by its name prefix with a row count, sorted highest first, so you can spot which plugin's options weigh the most on your `wp_options` table.
- **Automated Daily Cleanup** – Schedule automated daily deletion of expired transients via WP Cron.
- **Global Search & Replace** – Safely replace strings across the options table with a dry-run preview that lists every matching option.
- **Import / Export** – Move settings between sites easily with JSON support.
- **Integrations** – Browse third-party plugin tables (WP Recipe Maker, Better Payment) from the same interface when those plugins are installed.
- **Core Option Protection** – WordPress core options can't be deleted by accident.
- **WP-CLI Support** – Manage options (wp nhr-options list, wp nhr-options delete) from the command line.

### ⚡ Easy Installation & Instant Setup
No complex configurations needed! Just install, activate, and head to **Tools → Options Table** for the dashboard-first interface. The previous DataTables view is still one click away via the **Classic view** link at the top of the app.

### 🎯 Optimize Performance & Reduce Bloat
Analyze, clean, and optimize your database by removing unnecessary options, improving site performance significantly.

### 🌟 Join Thousands of Happy Users
Get started today and take control of your WordPress options like never before!

== Installation ==

1. Install the plugin from **Plugins → Add New**, or upload the plugin folder to `/wp-content/plugins/`.
2. Activate it.
3. Go to **Tools → Options Table**.

That's it! You're done.

== Frequently Asked Questions ==

= Where do I find the plugin after activating it? =
Go to **Tools → Options Table**. The previous DataTables interface is still available through the **Classic view** link at the top of the app.

= Does this plugin require any dependencies? =
No, it works as a standalone plugin.

= Will it affect my website's performance? =
No, but it will help you optimize your database for better performance. The optional Autoload Usage Tracker records which autoloaded options are read on front-end page loads, and stops writing once it has sampled 10,000 page loads.

= Is it safe to delete options? What if I remove something important? =
WordPress core options are protected and can't be deleted. Every change is recorded in Option History, so you can roll an option back to a previous value, and you can take a snapshot of the whole `wp_options` table before any cleanup. The plugin also takes one automatically before Search & Replace and Import.

= Can I edit, delete, and add options easily? =
Absolutely! Add and edit options in a modal, or select rows to bulk delete. The same works for usermeta, postmeta, commentmeta, termmeta, and transients.

= Does it support serialized data? =
Yes! Serialized and JSON values open as structured data for editing and are saved back in their original format.

= Can I delete expired transients? =
Yes. Delete them manually from Optimize → Cleanup or the Transients tab, or turn on the automated daily cleanup in Settings.

= Can it find and remove leftover options from plugins I've already uninstalled? =
Yes, the Orphan Scanner cross-references `wp_options` prefixes against your installed plugins and flags anything left behind so you can clean it up safely.

= Does it help with autoloaded data specifically? =
Yes. The Autoload Health Check shows total autoload size and your heaviest autoloaded options, and the Autoload Usage Tracker flags autoloaded options that are never read on the front end so you know which ones are safe to turn off.

= How long should I let the Autoload Usage Tracker run? =
Let it collect data across normal traffic for a few days, so rarely visited pages are covered too. An option marked "Unused" was never read during that period, which makes it a good candidate. Review the name before you turn off autoload.

= Can I back up my options table before making changes? =
Yes. You can create manual snapshots or schedule daily/weekly backups, and the plugin automatically snapshots before Search & Replace and Import operations.

= Is there a command-line interface? =
Yes, WP-CLI is supported (`wp nhr-options list`, `wp nhr-options delete`).

= I used the menu capability filter in 1.x. Do I need to change anything? =
Yes. In 2.0.0 the filter was renamed from `nhrotm-options-table-manager/menu/capability` to `nhrotm_menu_capability`. Update your callback to use the new name.

= What happens to my data when I delete the plugin? =
Deleting the plugin removes its own settings, history, and backup tables. It never touches the options you managed with it.

== Screenshots ==

1. Dashboard — database health score, stat cards, and prioritized recommendations
2. Browse — options with owner filter, sortable columns, and bulk actions
3. Edit modal — structured editing for serialized and JSON values
4. Optimize — autoload health and the Autoload Usage Tracker
5. Optimize — Orphan Scanner for leftovers from uninstalled plugins
6. Browse → Transients — size, expiration, status, and owner
7. Tools — backups & snapshots and Search & Replace with dry run
8. Activity — searchable log of every change and who made it

== Changelog ==

= 2.0.0 - 25/09/2026 =
- New: Complete React interface rewrite — a dashboard-first admin app with Dashboard, Browse, Optimize, Tools, Integrations, and Settings sections. The previous DataTables view stays available via the "Classic view" link.
- New: Database Health Dashboard with a 0–100 score, stat cards, prioritized recommendations, and a recent-activity summary.
- New: Activity tab — a searchable, filterable log of every change and who made it.
- New: Unified Browse view for options, usermeta, postmeta, commentmeta, termmeta, and transients with sortable columns, owner filter, add, inline edit (structured serialized/JSON editing), bulk delete, and search.
- New: Filter Browse's postmeta/usermeta tabs to a single post or user via a type-ahead picker.
- New: Autoload Usage Tracker — flags autoloaded options never used on front-end page loads; filter by usage and bulk-disable autoload for every unused option.
- New: Transients Manager — size, expiration, status, and guessed owner for every transient; add, edit, and bulk delete.
- New: Scheduled Backups & Snapshots of the wp_options table (manual, daily/weekly cron) with restore; automatic snapshot before Search & Replace and Import.
- New: Options Table Analytics panel — every option grouped by name prefix with a row count.
- New: Integrations tab for WP Recipe Maker and Better Payment tables.
- New: REST API backend (nhrotm/v1) with centralized settings.
- Improved: Search & Replace dry run now lists every matching option, and WordPress core cache/transient data is excluded by default.
- Improved: Owner attribution and the Orphan Scanner are more accurate — real WordPress core options are no longer flagged as orphans, and hyphenated plugin prefixes are recognized.
- Improved: Tools → Import has a drag-and-drop file dropzone.
- Fixed: The Orphan Scanner no longer flags data from active plugins it couldn't match by folder name (WooCommerce's `wc_`/`product_` options, Yoast SEO's `wpseo_`/`yoast_`, the bundled Action Scheduler and Jetpack packages) or WordPress core's `fresh_site`/`recovery_keys` as orphans.
- Fixed: "Last backup" on the Dashboard showed the wrong age (measured from midnight of the backup day).
- Improved: The plugin's own settings no longer autoload on every front-end page load.
- Fixed: Uninstall now removes every option the plugin creates.
- Developer: The whole PHP codebase now passes WordPress Coding Standards.
- Developer: Renamed the menu capability filter from `nhrotm-options-table-manager/menu/capability` to `nhrotm_menu_capability` to follow WordPress hook naming. If you filtered the old name, update your callback.

= 1.4.3 - 14/05/2026 =
- Enhancement: Add GitHub Actions workflow for automated plugin checks
- Improvement: Improve input sanitization
- Updated: Settings description for HTML preservation option

= 1.4.2 - 09/05/2026 =
- Fixed: Database error when history table was missing; added lazy table creation logic.

= 1.4.1 - 09/05/2026 =
- Few minor bug fixes & improvements

= 1.4.0 - 09/05/2026 =
- Added: "Allow HTML in Option Values" setting to preserve HTML tags when adding or editing options
- Few minor bug fixing & improvements

= 1.3.0 - 30/01/2026 =
- Added: Export/Import feature allowing JSON configuration portability
- Added: Global Search & Replace utility with safe serialization handling and Dry Run mode
- Added: Orphaned Options Scanner to identifying bloat from uninstalled plugins
- Added: Option History Pruning (Automated via Cron & Manual control)
- Added: WP-CLI Support (`nhr-options list`, `nhr-options delete`)

= 1.2.0 - 19/01/2026 =
- Added: Option History & Rollback system with change tracking
- Added: Autoload Health Check (Optimizer) with size analysis
- Added: Automated Daily Cleanup for expired transients (via settings)
- Rebuilt: Scalable Tab Architecture for robust third-party integration
- Improved: Modern UI with toggle switches and polished card layouts
- Fixed: Resolved empty results bug in Autoload Optimizer
- Performance: Enhanced server-side DataTables processing


= 1.1.9 - 05/01/2026 =
- Added: Bulk delete options feature

= 1.1.8 - 30/11/2025 =
- WordPress tested up to version is updated to 6.9
- Few minor bug fixing & improvements

= 1.1.7 - 28/03/2025 =
- Added: Column search feature
- Added: Filter by option type - option or transient
- Added: Delete all expired transients button and functionality
- Added: WP Recipe Maker tables (ratings, analytics, changelog) added. Props @abidhasan112
- Revamped: Codebase updated for better performance
- WordPress tested up to version is updated to 6.8
- Few minor bug fixing & improvements

= 1.1.6 - 15/03/2025 =
- Fixed: Fatal error due to composer dev files
- Few minor bug fixing & improvements

= 1.1.5 - 14/03/2025 =
- Added: Protected option and usermeta now having tooltip on edit and delete button
- Added: Toast notification added replacing alert messages
- Fixed: Fatal error due to PHPUnit vendor file missing
- Fixed: Usermeta table pagination issue
- Few minor bug fixing & improvements

= 1.1.4 - 12/03/2025 =
- Few minor bug fixing & improvements

= 1.1.3 - 09/03/2025 =
- Added: Security improvements
- Few minor bug fixing & improvements

= 1.1.2 - 05/01/2025 =
- Added: Serialize data edit support. Props @mdnahidhasan
- Few minor bug fixing & improvements
- Happy New Year 2025!

= 1.1.1 - 13/11/2024 =
- Added: Usermeta table support added
- Added: Modal close when clicked outside. Props @mdnahidhasan
- Added: Edit, delete feature for usermeta table
- Few minor bug fixing & improvements

= 1.1.0 - 30/10/2024 =
- Added: Serialize data support
- Added: Showing all options regardless their autoload status
- Revamped: Full DataTable revamped. Props @scriptertoufiq
- Revamped: Add/Edit option using modal
- Revamped: Options usage analytics
- Few minor bug fixing & improvements

= 1.0.7 - 18/10/2024 =
- WordPress tested up to version is updated to 6.7
- Few minor bug fixing & improvements

= 1.0.6 - 26/07/2024 =
- WordPress tested up to version is updated to 6.6
- Few minor bug fixing & improvements

= 1.0.5 - 09/07/2024 =
- Added: Add new option feature. Now adding option becomes much easier directly from Dashboard.
- Improved: JSON data are being saved now correctly without adding extra slashes. Props @hrrarya
- Few minor bug fixing & improvements

= 1.0.4 - 07/07/2024 =
- Added: Edit feature to update existing options. Props @arrasel403 and @obayedmamur
- Added: Delete feature to delete existing options. Props @mehrazmorshed
- Few minor bug fixing & improvements

= 1.0.3 - 05/07/2024 =
- Added: Author URI updated using org profile. Props @jakariaistauk
- Added: GitHub and Slack community links in readme.
- Improved: Scroll bar added for very long contents in the table. Props @jakariaistauk 
- Improved: Table UI fully revamped. Now prefix count is shown using a table too.
- Fixed: Settings page not shown as active after clicking from plugins page
- Fixed: Menu design breaks for some plugins due to conflict with tailwind css. Props Md Toufiqul Islam (scriptertoufiq)
- Fixed: Pagination select box spacing issue. Props Md Toufiqul Islam (scriptertoufiq)
- Few minor bug fixing & improvements

= 1.0.2 - 30/06/2024 =
- Added: Settings page link on plugins page. Props @himadree12
- Fixed: Long text breaks design. Props @mehrazmorshed 
- Few minor bug fixing & improvements

= 1.0.1 - 26/06/2024 =
- Prefix updated
- Few minor bug fixing & improvements

= 1.0.0 - 12/04/2024 =
- Initial beta release. Cheers!


== Upgrade Notice ==

= 2.0.0 =
Major update: a new dashboard-first interface under Tools → Options Table (the old view is still available via "Classic view"). If you used the `nhrotm-options-table-manager/menu/capability` filter, rename it to `nhrotm_menu_capability`.

= 1.0.0 =
- This is the initial release. Feel free to share any feature request at the plugin support forum page.