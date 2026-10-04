=== NHR Database Cleaner & Optimizer – Revisions, Transients, Autoload & Options Manager ===
Contributors: nhrrob  
Tags: database, cleanup, optimize, autoload, revisions
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Clean, optimize and manage your database: revisions, spam, transients and leftovers. Edit options and meta, tune autoload, optimize tables.

== Description ==

🚀 [GitHub Repository](https://github.com/nhrrob/nhrrob-options-table-manager) – Found a bug or have a feature request? Let us know!  
💬 [Slack Community](https://join.slack.com/t/nhrrob/shared_invite/zt-2m3nyrl1f-eKv7wwJzsiALcg0nY6~e0Q) – Got questions or just want to chat? Come hang out with us on Slack!

A WordPress database collects data it no longer needs: old post revisions, auto-drafts, trashed posts, spam comments, expired transients, meta left behind by deleted content, and options and tables left behind by plugins you removed. This plugin shows you all of it, lets you preview exactly what would be deleted, and cleans it up on demand or on a schedule.

It is a database cleaner and optimizer, not a malware scanner: it removes unused data, it does not look for malicious code.

`<?php echo 'Small database, fast site.'; ?>`

### 🚀 Everything in One Place
Cleanup, table optimization, a scheduled-events (cron) manager, and the deepest `wp_options` and autoload tools available: a tracker that finds autoloaded options your site never actually reads, per-option history with one-click restore, and automatic snapshots before risky changes.

### ✨ Key Features
- **Database Cleanup** – Remove post revisions, auto-drafts, trashed posts, spam, trashed and pending comments, pingbacks and trackbacks, orphaned post/comment/term/user meta, duplicate meta, orphaned term relationships, oEmbed caches, expired transients, and content left behind by post types that no longer exist. Every type shows a live count.
- **Preview Before You Delete** – See exactly which rows a cleanup would remove, and keep the last N days of anything with a date.
- **Scheduled Cleanups** – Give each cleanup type its own schedule (hourly, twice daily, daily, weekly or monthly) with its own keep-the-last-N-days rule. No limit on the number of schedules.
- **Action Scheduler Cleanup** – Clear finished, failed and canceled actions and their logs (WooCommerce and many other plugins fill these tables).
- **Tables: Sizes, Optimize, Repair** – Every database table with its rows, size, overhead and engine. Optimize and repair tables, convert MyISAM to InnoDB, and find tables left behind by removed plugins; those can be emptied or dropped after you type the table name to confirm.
- **Scheduled Events (Cron) Manager** – See every scheduled event with its next run and owner, run one now, delete it, and spot events that may belong to plugins you removed.
- **Database Health Dashboard** – An at-a-glance scorecard (0–100) with database size, autoload size, cleanable rows, transient and orphan counts and last backup, with prioritized one-click recommendations.
- **Activity Log** – A recent-activity summary on the Dashboard plus a dedicated, searchable and filterable Activity tab showing every change and who made it. The log keeps the latest 300 changes within your retention period.
- **No Extra Tables** – The plugin adds no database tables of its own. Its history and snapshots are stored as non-autoloaded options with fixed size limits, and all settings live in a single option.
- **Modern React Interface** – A fast, dashboard-first admin app organized into Dashboard, Browse, Optimize, Tools, Integrations, and Settings.
- **Autoload Usage Tracker** – Records which autoloaded options are actually used on real front-end page loads, then flags the ones that are never used so you can safely turn off their autoload; filter the list by usage status and bulk-disable every unused option at once.
- **Autoload Health Check** – Analyze total autoloaded data size and identify heavy options that slow down your site.
- **Transients Manager** – Dedicated view of every transient with size, expiration, status, and its guessed owner (a plugin, theme, or WordPress core); filter by status or owner, add and edit values (with expiration) directly, and select rows to bulk delete.
- **Manage Options** – Add, edit, and delete options with sortable columns, search, pagination, and bulk delete; filter the list by guessed owner (a plugin, theme, or WordPress core).
- **Usermeta, Postmeta, Commentmeta & Termmeta Support** – Browse, add, edit, and delete user, post, comment, and term meta entries just like options; narrow usermeta or postmeta to a single user or post by searching its name/title in a type-ahead picker. On multisite, user meta (shared by every site in the network) is available only to network administrators.
- **Serialized Data Handling** – Serialized and JSON values open as structured data you can edit safely, and are saved back in their original format.
- **Scheduled Backups & Snapshots** – Create restorable snapshots of your `wp_options` table manually or on a daily/weekly schedule, with automatic snapshots taken before Search & Replace and Import. Snapshots are compressed and skip transients, so they stay a small fraction of the table they protect; a scheduled snapshot is skipped when nothing has changed. The latest 15 are kept (the oldest is removed automatically), and the Backups panel shows how much space they use.
- **Option History & Rollback** – Every option edit and delete is recorded with its previous value. Open any option's History from its row in Browse, or use the Activity tab, and restore a previous version in one click.
- **Orphan Scanner** – Find and clean up leftovers from uninstalled plugins.
- **Options Table Analytics** – See every option grouped by its name prefix with a row count, sorted highest first, so you can spot which plugin's options weigh the most on your `wp_options` table.
- **History Retention** – Keep the change history for as many days as you choose, pruned daily or on demand with "Prune history now". Settings shows how many entries the history holds and how much space it uses.
- **Global Search & Replace** – Safely replace strings across the options table, with a live count of matching options as you type and a dry-run preview that lists every match.
- **Import / Export** – Export every option, or search and pick just the ones you need. Import shows a preview first (new, modified, unchanged or protected, with the current value) so you choose exactly which options to bring in; files are checksum-verified, and exports from older versions import too.
- **Integrations** – Browse third-party plugin tables (WP Recipe Maker ratings, analytics and changelog; Better Payment) from the same interface with search and sortable columns. Tables whose plugin is no longer active are flagged.
- **Allow HTML in Option Values** – Off by default: HTML is stripped from option values you add or edit. Turn it on to store values exactly as entered. Follows WordPress's own rule: only users allowed to save unfiltered HTML can store it.
- **Core Option Protection** – WordPress core options can't be deleted by accident.
- **Multisite Ready** – Network-activate it and every site gets its own Database Cleaner for its own data, history, snapshots and schedules. A Network Admin screen lists every site with its database and autoload size, and lets network administrators browse and clean network-wide options and site transients. User meta, which is shared across the network, is limited to network administrators, and uninstalling cleans up every site.
- **WP-CLI Support** – `wp nhrotm list` and `delete` for options, `wp nhrotm cleanup list` and `cleanup run <type> [--older-than=<days>] [--dry-run]` for cleanups, and `wp nhrotm tables list` and `tables optimize`. The earlier `wp nhr-options` command name still works.

### ⚡ Easy Installation & Instant Setup
No complex configurations needed! Just install, activate, and head to **Tools → Database Cleaner** for the dashboard-first interface.

### 🎯 Safe by Design
WordPress core options, tables and scheduled events are protected. Cleanups show a count and a preview before anything is deleted, options changes can be restored from history, and a snapshot of your options is taken automatically before Search & Replace and Import. Cleanups of posts, comments and meta cannot be undone from the plugin, so keep a full database backup as you would before any maintenance.

== Installation ==

1. Install the plugin from **Plugins → Add New**, or upload the plugin folder to `/wp-content/plugins/`.
2. Activate it.
3. Go to **Tools → Database Cleaner**.

That's it! You're done.

== Frequently Asked Questions ==

= Where do I find the plugin after activating it? =
Go to **Tools → Database Cleaner**. On multisite, network administrators also get **Network Admin → Settings → Database Cleaner**.

= Is this a malware or virus scanner? =
No. It cleans up unused data (revisions, drafts, spam, expired transients, leftovers from removed plugins) and optimizes tables. It does not scan for or remove malicious code.

= What exactly can it clean? =
Post revisions, auto-drafts, trashed posts, spam, trashed and pending comments, pingbacks and trackbacks, orphaned and duplicate meta, orphaned term relationships, oEmbed caches, expired transients, Action Scheduler logs, and content of post types that are no longer registered. Each one shows a count and a preview first.

= Can I undo a cleanup? =
Changes to options can be restored from Activity, and snapshots cover the options table. Cleanups of posts, comments, meta and tables are permanent, which is why each one shows a preview and asks for confirmation. Take a full database backup before a large cleanup.

= Will a schedule delete things without asking? =
Yes, that is what a schedule is for. Schedules are off until you turn one on, each has its own keep-the-last-N-days rule, and the riskier cleanups (duplicate meta, unregistered post types) can only be run manually.

= Is it safe to drop a table? =
Only tables that no installed plugin or theme claims can be emptied or dropped, and you have to type the table name to confirm. WordPress core tables and tables of installed plugins are protected.

= Does this plugin require any dependencies? =
No, it works as a standalone plugin.

= Will it affect my website's performance? =
No, but it will help you optimize your database for better performance. The optional Autoload Usage Tracker records which autoloaded options are read on front-end page loads, and stops writing once it has sampled 10,000 page loads.

= Is it safe to delete options? What if I remove something important? =
WordPress core options are protected and can't be deleted. Every option edit and delete is recorded in Activity, so you can restore an option to its previous value, and you can take a snapshot of the whole `wp_options` table before any cleanup. The plugin also takes one automatically before Search & Replace and Import.

= Can I edit, delete, and add options easily? =
Absolutely! Add and edit options in a modal, or select rows to bulk delete. The same works for usermeta, postmeta, commentmeta, termmeta, and transients.

= Does it support serialized data? =
Yes! Serialized and JSON values open as structured data for editing and are saved back in their original format.

= Can I delete expired transients? =
Yes. Delete them from Cleanup or the Transients tab, or give expired transients a schedule on the Cleanup screen.

= Can it find and remove leftover options from plugins I've already uninstalled? =
Yes, the Orphan Scanner cross-references `wp_options` prefixes against your installed plugins and flags anything left behind so you can clean it up safely.

= Does it help with autoloaded data specifically? =
Yes. The Autoload Health Check shows total autoload size and your heaviest autoloaded options, and the Autoload Usage Tracker flags autoloaded options that are never read on the front end so you know which ones are safe to turn off.

= How long should I let the Autoload Usage Tracker run? =
Let it collect data across normal traffic for a few days, so rarely visited pages are covered too. An option marked "Unused" was never read during that period, which makes it a good candidate. Review the name before you turn off autoload.

= Can I back up my options table before making changes? =
Yes. You can create manual snapshots or schedule daily/weekly backups, and the plugin automatically snapshots before Search & Replace and Import operations.

= Is there a command-line interface? =
Yes. `wp nhrotm list` and `delete` manage options, `wp nhrotm cleanup list` and `cleanup run <type>` run cleanups (with `--older-than=<days>` and `--dry-run`), and `wp nhrotm tables list` and `tables optimize` cover tables. The earlier `wp nhr-options` command name still works as an alias.

= I used the menu capability filter in 1.x. Do I need to change anything? =
Yes. In 2.0.0 the filter was renamed from `nhrotm-options-table-manager/menu/capability` to `nhrotm_menu_capability`. Update your callback to use the new name.

= What happens to my data when I delete the plugin? =
Deleting the plugin removes its own settings, schedules, history and snapshots (on every site of a multisite network). The plugin creates no database tables of its own. It never touches the data you managed with it.

== Screenshots ==

1. Dashboard — database health score, stat cards, and prioritized recommendations
2. Cleanup — every cleanup type with its live count, keep-the-last rule, schedule, preview and clean
3. Browse — options with owner filter, sortable columns, per-option history, and bulk actions
4. Edit modal — structured editing for serialized and JSON values
5. Optimize — autoload health and the Autoload Usage Tracker
6. Optimize → Tables — size, overhead, engine and owner, with leftover tables flagged
7. Optimize — Orphan Scanner for leftover options from uninstalled plugins
8. Tools — backups & snapshots and Search & Replace with dry run
9. Tools → Scheduled events — every cron event with next run, owner, run now and delete
10. Activity — searchable log of every change and who made it, with one-click restore

== Changelog ==

= 2.1.0 - 04/10/2026 =
- New name: NHR Database Cleaner & Optimizer. The menu item is now Tools → Database Cleaner. Nothing else changes: same plugin and same settings.
- New: Cleanup section. Remove post revisions, auto-drafts, trashed posts, spam, trashed and pending comments, pingbacks and trackbacks, orphaned and duplicate meta, orphaned term relationships, oEmbed caches, expired transients and content of unregistered post types, each with a live count, a preview of exactly what would be deleted, and a keep-the-last-N-days rule.
- New: Scheduled cleanups. Each cleanup type can run hourly, twice daily, daily, weekly or monthly. The old "automated daily cleanup" setting becomes a daily expired-transients schedule automatically.
- New: Action Scheduler cleanup for finished, failed and canceled actions and their logs.
- New: Tables in Optimize. Rows, size, overhead and engine for every table; optimize, repair and convert to InnoDB; leftover tables from removed plugins are flagged and can be emptied or dropped after typing the table name.
- New: Scheduled events in Tools. List every cron event, run one now or delete it; events that may belong to removed plugins are flagged.
- New: Multisite Network Admin screen with every site's database and autoload size, and network-wide options and site transients.
- New: WP-CLI commands `cleanup list`, `cleanup run` and `tables list`, `tables optimize`.
- Improved: The WP-CLI command is now `wp nhrotm`. The earlier `wp nhr-options` name keeps working, so existing scripts need no change.
- New: Search & Replace shows how many options and occurrences match as you type.
- Improved: The Dashboard shows the database size and counts cleanable rows in the health score and recommendations.
- Improved: Integrations flags tables whose plugin is no longer active.
- Improved: The plugin now keeps everything it stores in four options plus one per snapshot, and only its small settings option is autoloaded.
- Improved: The plugin no longer creates database tables. History and snapshots are now stored as non-autoloaded options, and the two tables from earlier versions are moved over and removed automatically on update. History keeps the latest 300 changes (values over 100 KB are recorded without their content and cannot be restored).
- Security: On multisite, a site administrator could read and edit the user meta of every user in the network, including another site's role keys (for example making themselves administrator of a different site). User meta is now available only to users who can manage network users, and role and capability keys are protected for every site and any database table prefix.
- Improved: Snapshots are now compressed and no longer include transients (they are cache and regenerate), cutting their size by over 95%. Existing snapshots are shrunk automatically on update, and the Backups panel shows the total space snapshots use.
- Improved: A scheduled snapshot is skipped when nothing changed since the previous one.
- Improved: The Backups panel says how many of the 15 kept snapshots exist and how much space they use, and Settings shows how many entries the change history holds and its size.
- New: Restore an option to its previous value straight from the Activity tab, or from the new History button on each option in Browse.
- New: Export only the options you pick (search and add them to an export list), and preview an import before running it: every option shows whether it is new, modified, unchanged or protected, and you choose which to import. Export files now carry a checksum that import verifies.
- New: Integrations shows the WP Recipe Maker Analytics and Changelog tables, and every integration table can be searched and sorted.
- New: "Prune history now" button in Settings.
- Fixed: Importing a file exported by version 1.x skipped every option.
- Fixed: "Allow HTML in option values" had no effect in the 2.0 interface. With it off, HTML is now stripped from option values on save (keys and numbers are left untouched); with it on, values are saved exactly as entered.
- Improved: The plugin no longer runs any database query on front-end page loads. Usage tracking, when switched on, records a sample of page loads instead of writing on every one.
- Improved: Deleting many options at once is much faster.
- Improved: Scheduled cleanups no longer clear the whole object cache.
- Security: Raw HTML in values now follows WordPress's unfiltered HTML permission. On a multisite network, a site administrator's values are always stripped of HTML, Import and a live Search & Replace need a network administrator, table actions on the main site need a network administrator, and each site's roles option is protected.
- Fixed: Search & Replace handled only the first 100 matching options. It now handles every match, and the live count shows the same number.
- Fixed: After a Search & Replace, WordPress could keep serving the old values from its cache.
- Fixed: Activity showed the wrong time ("6 hours ago" for a change made just now) on sites not set to UTC.
- Fixed: Restoring a snapshot did not put back an option's autoload setting when its value had not changed.
- Fixed: An option created in Browse could stay invisible to WordPress (still read as missing) until the object cache was cleared, when its name had been looked up before it existed.
- Fixed: Restoring a history entry recorded for user/post/comment/term meta could write a stray row into `wp_options`. History entries now record which table they belong to, and only option entries can be restored.
- Fixed: Activity labelled meta edits as option edits.
- Fixed: The Usage tracking, Automated daily cleanup and History retention settings had no effect on sites set up with 2.0.0.
- Fixed: Reactivating the plugin could switch scheduled backups off.
- Fixed: On multisite, only the main site got the daily cleanup and history pruning jobs, so other sites' history was never pruned. Every site now schedules its own jobs, including sites created later.
- Fixed: Uninstalling on multisite left every other site's tables and settings behind. Uninstall now cleans up every site, and network deactivation clears every site's scheduled jobs.
- Removed: The Classic (DataTables) view, its admin-ajax endpoints and assets. Every feature it had is now in the main app.

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
- Security: The Classic view's orphan scan now requires a valid nonce and the `manage_options` capability; it previously returned option-name prefixes to any logged-in user.
- Security: Import skips WordPress core options and rejects values containing serialized PHP objects (other than plain stdClass), so a crafted import file can't plant an object-injection payload.
- Security: Search & Replace and snapshot/history restore no longer instantiate PHP classes when reading serialized option values.
- Fixed: Searching the WP Recipe Maker ratings table in the Classic view caused a fatal error.
- Security: The Classic view's table queries now build sorting and search SQL only from whitelisted values and single prepared statements.
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

= 2.1.0 =
Security fix for multisite networks. New name and menu: Tools → Database Cleaner, now with cleanup of revisions, spam, transients and orphaned data. History and snapshots move from the plugin's two tables into options automatically. The Classic view has been removed.

= 2.0.0 =
Major update: a new dashboard-first interface under Tools → Options Table (the old view is still available via "Classic view"). If you used the `nhrotm-options-table-manager/menu/capability` filter, rename it to `nhrotm_menu_capability`.

= 1.0.0 =
- This is the initial release. Feel free to share any feature request at the plugin support forum page.