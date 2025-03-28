=== NHR Options Table Manager ===
Contributors: nhrrob  
Tags: wp options, wp_options, transients, usermeta, development  
Requires at least: 6.0  
Tested up to: 6.8  
Requires PHP: 7.4  
Stable tag: 1.1.7
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Manage the wp_options table and get analytics on options usage.

== Description ==

🚀 [GitHub Repository](https://github.com/nhrrob/nhrrob-options-table-manager) – Found a bug or have a feature request? Let us know!  
💬 [Slack Community](https://join.slack.com/t/nhrrob/shared_invite/zt-2m3nyrl1f-eKv7wwJzsiALcg0nY6~e0Q) – Got questions or just want to chat? Come hang out with us on Slack!

https://www.youtube.com/watch?v=le89m1qfb0U

Are you fed up with the size of wp otions table? You are not alone! 
Install this plugin and get a fine view of the table and analytics.

`<?php echo 'Small WP Options Table, Clean Database!'; ?>`

### 🚀 A Powerful Yet Simple Solution to Manage wp_options
Tired of an overloaded `wp_options` table slowing down your WordPress site? **NHR Options Table Manager** provides a clean, organized, and optimized way to view and manage your options table efficiently. Get detailed analytics, edit and delete options, and keep your database lean and performant.

### ✨ Key Features
- **Manage Options** – Add, edit, and delete options easily using a secure, optimized modal system.
- **Usermeta Table Support** – Edit and delete user meta entries just like options.
- **Better Payment Table Support** – View and manage custom Better Payment data.
- **Serialized Data Handling** – Edit serialized data seamlessly; it appears as a structured object or array for easy modifications.
- **Options Usage Analytics** – Get insights into which prefixes dominate your options table.
- **Live Search & Pagination** – Search without reloading and navigate large datasets efficiently.
- **Security & Optimization** – Core options are protected, ensuring safe management of critical data.

### 🚀 Coming Soon
We're constantly improving NHR Options Table Manager! Here's what's on the way:
- **Bulk Deletion** – Quickly remove multiple options and user meta entries at once.
- **WP Recipe Maker Table Support** – Manage recipe-related data efficiently.
- **Expired Transient Deletion** – Automatically clean up expired transients to free up database space.
- **More Exciting Features** – Stay tuned for additional enhancements!

### ⚡ Easy Installation & Instant Setup
No complex configurations needed! Just install, activate, and head to **Tools → Options Table** for a detailed DataTable view of your options.

### 🎯 Optimize Performance & Reduce Bloat
Analyze, clean, and optimize your database by removing unnecessary options, improving site performance significantly.

### 🌟 Join Thousands of Happy Users
Get started today and take control of your WordPress options like never before!

== Installation ==

1. Upload the NHR Options Table Manager plugin to your blog.
2. Activate it.

That's it! You're done.

== Frequently Asked Questions ==

**Does this plugin require any dependencies?**  
No, it works as a standalone plugin.

**Will it affect my website's performance?**  
No, but it will help you optimize your database for better performance.

**Can I edit, delete, and add options easily?**  
Absolutely! Everything is managed through a user-friendly UI with modals.

**Does it support serialized data?**  
Yes! Serialized data is automatically formatted for easy editing and saved back in a structured format.

**Can I delete expired transients?**  
Not yet, but this feature is coming soon!

== Screenshots ==

1. Plugin features overview
2. DataTable view of the wp_options table  
3. Add option modal  
4. Live search functionality  
5. Edit modal for serialized data  
6. Options usage analytics

== Changelog ==

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
- Added: Class exists check for Better Payment table
- Added: Toast notification added replacing alert messages
- Fixed: Fatal error due to PHPUnit vendor file missing
- Fixed: Usermeta table pagination issue
- Few minor bug fixing & improvements

= 1.1.4 - 12/03/2025 =
- Few minor bug fixing & improvements

= 1.1.3 - 09/03/2025 =
- Added: Better Payment table support added
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

= 1.0.0 =
- This is the initial release. Feel free to share any feature request at the plugin support forum page.