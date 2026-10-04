# PRD — Database Cleaner (Free) — "NHR Database Cleaner & Optimizer", slug `nhrrob-options-table-manager`

Status: **2.0.0 released 2026-09-25** · **2.1.0 built, verified and release-ready 2026-10-04** (uncommitted; Robin reviews, commits and tags) · Owner: Nazmul Hasan Robin (nhrrob)
Last revised: 2026-09-28 (repositioned as a full database cleaner; every database-cleaning feature is free, see §7)

> Dev-only document. Excluded from distribution (`.distignore` + `.gitattributes export-ignore`).
> PRO/monetization scope: **[PRD-PRO.md](./PRD-PRO.md)** · UI/visual spec: **[DESIGN.md](./DESIGN.md)**.
> What already shipped is recorded in `readme.txt` (changelog + feature list), not here.

## 1. Principles (non-negotiable)

### 1.1 Minimal footprint

**The shipped plugin zip must not grow.** Anything that adds weight needs an equal or larger removal to justify it.

**Budget for 2.1.0: the zip must stay no larger than the released 2.0.0 zip (280 KB).** Result: **261 KB** with the whole database-cleaner pass, because removing the Classic UI freed more than the new features cost. Measure after every feature, not only at release.

- No runtime PHP dependencies. `vendor/` is dev-only (autoloader only).
- No JS/CSS frameworks beyond what `@wordpress/scripts` and WP core provide. Hand-rolled data grid, inline-SVG charts, Tabler-icon/CSS approach.
- Measure before adding a library. Run `check:pcp` + a zip-size diff before every release.
- Baseline at 2.0.0: `admin/build/` ≈ 156 KB, legacy `assets/` ≈ 212 KB (removable, see §4).

### 1.2 No PRO in the free codebase

The free plugin carries **no PRO surface and no PRO wording**: no `Pro` tags, no Upgrade screen or nav item, no upgrade URL, no `hasPro`/`proAvailable` boot keys, and no "PRO" in code comments or UI strings. Removed 2026-10-04 (the dormant `ProTag.js`/`UpgradeScreen.js` UI and the `nhrotm_has_pro`, `nhrotm_pro_available` and `nhrotm_upgrade_url` filters); do not reintroduce them.

The free plugin only exposes a neutral **add-on API** (§3). Any discovery of a paid add-on happens outside this plugin (WP.org-independent site, the add-on itself). `ScannerManager` keeps a `nhrotmp_` prefix entry, labelled "Database Cleaner Add-on", so the add-on's own options are not reported as orphans.

**Anything free in another plugin is free here.** The full rule is [PRD-PRO.md](./PRD-PRO.md) §0.

## 2. Product (target IA for 2.1.0)

**Positioning (decided 2026-09-28):** a full **database cleaner and optimizer**, competing directly with Advanced Database Cleaner (100k+ installs) and WP-Optimize (1M+), while keeping the options/autoload depth neither has (usage tracker, per-option history and restore, snapshots before every risky action, import preview).

**One home per action.** A new feature must slot into one section; no duplicate entry points. The nav grows from six to seven sections (Cleanup is new); Cron and Tables go inside existing sections rather than adding more nav.

| Section | Owns |
|---|---|
| **Dashboard** | Health score, database size, stat cards, recommendations, recent activity |
| **Browse** | Options · Usermeta · Postmeta · Commentmeta · Termmeta · Transients (CRUD, per-option History) |
| **Cleanup** *(new)* | Posts/comments/meta/transient/oEmbed/Action Scheduler cleanup with counts + preview, and all cleanup schedules |
| **Optimize** | Autoload health + Usage Tracker · Orphaned options · **Tables** (size, overhead, optimize/repair, leftover tables) · Options analytics |
| **Tools** | Search & Replace · Import/Export · Backups · **Cron** (scheduled events) |
| **Integrations** | Third-party tables, shown only when the table exists |
| **Settings** | All settings in one `nhrotm_settings` object |
| **Activity** | Unified change log (reached from Dashboard "View all") |
| *Network Admin (multisite)* | Separate network screen: site switcher, network options (`wp_sitemeta`), per-site overview |

Health score gains cleanup inputs (revisions, spam, orphaned meta, table overhead) so the Dashboard reflects the new work. Nothing in the free UI carries a PRO surface (§1.2).

## 3. Architecture constraints

- **Extension point:** `ModuleRegistry` → `apply_filters( 'nhrotm_modules', $modules )`. New features are modules (`ModuleInterface`) with their own REST routes and dashboard cards.
- **Add-on API.** The complete contract an add-on relies on. There is no API-level constant (removed 2026-10-04): the add-on checks `NHROTM_VERSION` against its own minimum, so a breaking change here means raising that minimum in the add-on:
  - PHP filters: `nhrotm_modules`, `nhrotm_app_boot` (whole `window.nhrotmApp` payload), `nhrotm_health_recommendations`, `nhrotm_activity_describe`.
  - PHP action: `nhrotm_app_enqueued` (add-ons enqueue their bundle here with `nhrotm-app` as a dependency).
  - JS: `window.nhrotm` = `{ components: { Panel, ScreenHeader, DataTable, JumpNav, Icon }, useToast, useConfirm, registerIcons }`, and the `nhrotm.screens` filter (`@wordpress/hooks`) applied once when `App` mounts.
- **REST only** (`nhrotm/v1`). No AJAX actions; the Classic UI and its `AjaxHandler` were removed in 2.1.
- **Two-layer auth:** route capability gate (`manage_options`) + per-object `current_user_can` on any ID-taking route.
- **Multisite (2.1):** user meta is one table for the whole network, so the Usermeta type and user lookup are served only to users with `manage_network_users` (`BrowseService::can_manage_usermeta()`, enforced in `BrowseController::can_manage()`; the React tab hides via `boot.canUsermeta`). Role/capability user meta is protected for every site and any table prefix (`GlobalTrait::is_protected_usermeta()`). Released 2.0.0 let a site admin make themselves administrator of another site; reproduced and fixed 2026-09-28. Per-site lifecycle: `ensure_crons()` schedules each site's jobs on its first admin/cron request (network activation only runs the activation hook for the main site); `deactivate_plugin( $network_wide )` and `uninstall.php` loop every site. Raw HTML follows `unfiltered_html` (a site admin's values are always stripped; Import and live Search & Replace are refused), the per-site roles option is protected, and table actions on the main site need `manage_network`. Not in 2.1: a Network Admin screen and `wp_sitemeta` (network options / site transients).
- **Ships:** `admin/build/` and `admin/src/` (WP.org source requirement). Never exclude either.
- **Stable contracts:** WP-CLI `wp nhrotm` (primary since 2.1.0) with `wp nhr-options` kept as a working alias for pre-2.1 scripts — `list`/`delete` behave identically under both, stored data in options `nhrotm_history`, `nhrotm_snapshots` and `nhrotm_snapshot_{id}` (the plugin creates **no database tables**, decided 2026-10-04; the two pre-2.1 tables are migrated and dropped on update; a change of stored shape needs a migration: bump `Nhrotm_Options_Table_Manager::DB_VERSION`, add the step to `maybe_upgrade_db()`).
- **History rows carry `record_type`** (`options` | `usermeta` | `postmeta` | `commentmeta` | `termmeta` | `transients` | `event` | `unknown`). Only `options` rows with an `update`/`delete`/`restore*` action are restorable (`HistoryManager::is_restorable()`); callers logging meta `update`/`create` must pass the type explicitly to `ActivityService::record()`.
- **Settings live only in `nhrotm_settings`**, one small autoloaded option that also holds the data version; the front-end tracker reads its switch from it, so there is no separate flag option (decided 2026-10-04). The plugin's whole footprint is four fixed options (`nhrotm_settings`, `nhrotm_usage`, `nhrotm_history`, `nhrotm_snapshots`) plus one per snapshot.

## 4. Next release — 2.1.0 (one big release)

### 4.0 Already done, unreleased (2026-09-27/28)
History `record_type` + restore guard · compressed, transient-free snapshots (skip unchanged, size shown, 15-cap shown) · Classic UI removed with full parity (per-option History, selective export, import preview + checksum, WPRM tables, Integrations search/sort, Prune now, Allow HTML enforced) · Activity → Restore · settings that 2.0.0 ignored fixed · 1.x export import fixed · **multisite**: user-meta privilege-escalation fix, per-site crons, network deactivation + network-wide uninstall · history size shown in Settings · version already bumped to 2.1.0, screenshots retaken. Details in `readme.txt` changelog.

### 4.1 Built 2026-10-04 (all 13 done; kept as the record of what shipped and why)

1. ✅ **Done 2026-10-04** (also removed the unused `ValidationService`, three unused `GlobalTrait` helpers and a duplicate history-prune method; no `$_POST`/`$_GET` read is left anywhere in the plugin). **Remove dead Classic code.** `Interfaces/TableManagerInterface`, the `get_data()`/`edit_record()`/`delete_record()` stubs in the managers that implement it, `BaseTableManager` leftovers, `OptimizationManager::toggle_autoload()` (still reads `$_POST`; unreachable but exactly what WP.org's scanner flags) and `OptionsTableManager::perform_cleanup()` callers. Keep `SearchReplaceManager::preview_search()` (item 9).
2. ✅ **Rename** once §6 picks the name: plugin header `Plugin Name`, readme title + short description + tags (max 5), in-app `pluginName`, Tools menu label, screenshot captions, banner/icon text if any. The slug `nhrrob-options-table-manager`, text domain, prefixes and REST namespace **never change**.
3. ✅ **Cleanup section (new nav item).** Each cleanup type is a row with a live count and the space it frees, a **Preview** (paged list of exactly what will be deleted), **Clean**, and a per-type **keep last N days** rule. Types:
   - post revisions · auto-drafts · trashed posts
   - spam comments · trashed comments · pingbacks/trackbacks
   - orphaned post/comment/term/user meta (parent row gone) · exact-duplicate meta rows
   - orphaned term relationships · oEmbed caches (`_oembed_*` postmeta)
   - expired transients (moves here from Optimize → Cleanup)
   - *(parity with ADC free)* orphaned post-type content: posts whose post type is no longer registered by any active plugin or theme (list per type with counts; delete behind a confirm)
   - *(optional, off by default in schedules)* pending comments older than N days
   Rules: use core delete functions where they exist (`wp_delete_post_revision()`, `wp_delete_post()`, `wp_delete_comment()`) so hooks fire; batch large deletes; auto-snapshots don't cover posts/meta tables, so every Clean needs a confirm that names the count; record every run in Activity (`cleanup_<type>` events).
4. ✅ **Scheduled cleanups.** Per cleanup type: off / hourly / twice daily / daily / weekly / monthly, with its keep-N-days rule. No task limit (ADC caps free at 5). Replaces the single `auto_cleanup_enabled` switch — migrate it to "expired transients: daily". One cron hook per type; `ensure_crons()` and uninstall must cover them (per site on multisite).
5. ✅ **Action Scheduler cleanup** (shown only when `{prefix}actionscheduler_actions` exists): completed / failed / canceled actions and their logs, older than N days, batched. Manual + schedulable via item 4. (ADC charges for this.)
6. ✅ **Tables** (in Optimize). Every table in the database: rows, data + index size, overhead, engine, owner guess (core / installed plugin / **leftover** from a removed plugin, via `ScannerManager` prefix matching). Actions: optimize (only where overhead > 0), repair, convert MyISAM → InnoDB, empty a leftover table, and **drop a leftover table** behind a typed confirmation (the user types the table name). Core tables can never be dropped or emptied. Multisite: only the current site's tables unless in Network Admin.
7. ✅ **Cron** (in Tools). Every scheduled event: hook, next run, recurrence, owner guess. Actions: run now, delete, delete all events of a hook. Flag hooks with **no callback whose owner is unknown or removed** as "possibly orphaned" — only a flag, because many plugins attach cron callbacks only during cron requests (lesson from PRO). Core hooks are protected.
8. ✅ **Multisite Network Admin screen.** A Network Admin → Settings → Database Cleaner page for super admins: site switcher (open any site's app), a per-site overview table (health score, DB size, autoload size, last backup), and Browse for **network options + site transients** (`wp_sitemeta`) with the same protected-key rules. Routes gated on `manage_network_options`.
9. ✅ **Search & Replace live match count:** "N options, M occurrences" as you type, debounced, via `preview_search()` over REST. Not an option-name autocomplete — it searches values.
10. ✅ **Integrations: leftover tables** get a "plugin inactive" badge instead of being hidden (item 6's owner guess reused).
11. ✅ **Dashboard + health score:** database size card; cleanup counts and table overhead feed the score and recommendations ("1,240 post revisions — Clean").
12. ✅ **WP-CLI for cleanup** (WP-Optimize charges for CLI): `wp nhrotm cleanup list` (types + counts), `cleanup run <type> [--older-than=<days>] [--dry-run]`, `tables optimize [--all]`. Existing `list`/`delete` commands stay unchanged (stable contract, §3).
13. ✅ **Positioning + WP.org listing:** readme rewrite (description, Key Features, FAQ for the cleaner features and their safety), tags, screenshots for the new screens (Cleanup, Tables, Cron, Network Admin), PRO Upgrade rows unchanged (still 3 features, still off).

**Deviations from the plan above (deliberate):**
- **Database size** went into the Dashboard's stats strip, not a fifth card (four cards is the grid).
- **Empty / Drop** are offered only for tables no installed plugin claims (`leftover` / `unknown`), not for every non-core table: a button to drop an active plugin's table is a foot-gun.
- **Network options** are view + delete (protected keys refused) + "delete expired site transients". No add/edit of network options in 2.1.
- **Health score** gained one new penalty: up to 10 points for cleanable rows (`cleanable / 2000`). Table overhead is shown but not scored.
- **Snapshots no longer contain or restore the `cron` option** (found by the upgrade test: restoring an old snapshot rewound every plugin's schedule). After a restore, `SettingsService::migrate()` and `CleanupService::sync_cron()` re-normalise our own settings and events.
- Risky cleanup types (unregistered post types, duplicate meta) are **manual only**, and carry a plain-language caveat in the UI.

### 4.1a Feature parity check (2026-10-04)

After §4.1 ships, versus **Advanced Database Cleaner free**: everything it has, except its debug-log viewer (not a database feature; skipped). Versus **ADC Premium**: we give Action Scheduler cleanup, unlimited schedules, owner filter and per-site multisite free; we skip growth charts and the activation timeline (§5), and our owner detection is local prefix matching, not their cloud scan. Versus **WP-Optimize**: all of its *database* features incl. the premium ones (scheduling, preview, per-table optimize, multisite, WP-CLI). **Deliberately out of scope:** WP-Optimize's other two pillars (page caching, image compression, minify) and its WooCommerce query tweak / postmeta indexing — that is a caching plugin's job, and schema changes on other plugins' tables are too risky.

### 4.2 Release gate — passed 2026-10-04 on the final code
Results: PHPCS clean · lint clean · 23 unit tests · probe 80 checks / 0 failures (single site) + 76 / 0 (multisite) · Semgrep 0 findings · PHPStan clean · Plugin Check clean (only the two known `update_plugins` false positives) · PHP 7.4 compatible · upgrade from released 2.0.0 verified on otm-shots (snapshots 1,224 → 67 KB, history backfilled, settings kept, old daily-cleanup switch → daily expired-transients schedule) · multisite verified on otm-ms · zip 261 KB · 10 screenshots retaken. Checklist that was run: PHPCS + lint + PHPUnit (new unit tests for every cleanup query builder and the protected-table/hook lists) · build · security probe (every new route, anonymous + subscriber) · Semgrep `p/php` · PHPStan · Plugin Check on a production build · PHP 7.4 compatibility · upgrade test from the released 2.0.0 on otm-shots · multisite test on `~/Sites/otm-ms` (network activation, subsite crons, Network Admin, uninstall) · zip ≤ 287 KB (§1.1) · doc sync (readme, this PRD, DESIGN, CLAUDE.md) · screenshots. Then Robin reviews, commits and tags.

## 5. Not planned (decided 2026-09-28 — would add weight without real demand)

Revisit only on real user requests. Anything here is still free if it ever ships (PRD-PRO §0).

- **AI layer** (explain option / score, plain-language query, Abilities API, MCP write): needs WP 7.0 + the user's own AI provider; small audience.
- **Database growth charts, weekly email digest, plugin/theme activation log:** nice to have, not why people install a cleaner.
- **Backups — download, compare, configurable retention, off-site copy:** snapshots already cover rollback; off-site conflicts with §1.1.
- **Tree/GUI value editor, serialized-data repair, regex / all-table Search & Replace, cron execution monitoring.**
- **Integrations as a separate add-on:** not worth a second plugin to maintain.

## 6. Open decisions

- **Health panel weight** and **gauge warning color** (minor UI, carry over).

## 7. Decided (don't relitigate)

- **Positioning (2026-09-28):** full database cleaner/optimizer (§2). **Every database-cleaning feature is free** — the market check (ADC, WP-Optimize) showed that everything either charges for is free in another plugin (§8). PRO stays the three features nobody offers (PRD-PRO §3) and launches after this release has grown the install base (PRD-PRO §6).
- **Classic UI:** removed in 2.1 with full feature parity; nothing it did may be lost.
- **Name (2026-10-04):** display name **NHR Database Cleaner & Optimizer – Revisions, Transients, Autoload & Options Manager** (short form "NHR Database Cleaner & Optimizer"; the tail lists real features only — it both cleans and optimizes, via autoload tuning and table optimize/repair, and "Options Manager" keeps the editing side and the original identity in the title); in-app title and menu label **Database Cleaner** (Tools → Database Cleaner; Network Admin → Settings → Database Cleaner). The slug `nhrrob-options-table-manager`, text domain, `nhrotm` prefixes, and REST namespace never change. The CLI command is `wp nhrotm` from 2.1.0 (full plugin prefix); `wp nhr-options` stays registered as an alias. "Database cleaner" is the WordPress term for removing unused data (ADC uses it); the readme states it is not a malware scanner.

- **Free vs PRO line:** anything free in another plugin, or listed in this PRD, is free. See PRD-PRO §0. (It settled "Download snapshot: free or PRO?" → free.)
- **Data grid:** hand-rolled, no `@tanstack/react-table` (§1.1).
- **Safe Redirect Manager in Integrations:** no. It stores redirects as a CPT + postmeta, and has no standalone table for `IntegrationsService` to point at.
- **`nhrotm-options-table-manager/menu/capability` → `nhrotm_menu_capability`:** shipped in 2.0.0 as a hard break with no shim (changelogged).

## 8. Sources

- Advanced Database Cleaner free vs premium (checked 2026-09-28): https://wordpress.org/plugins/advanced-database-cleaner/ — free: general cleanup, orphaned/duplicate meta, tables optimize/repair, cron, options, transients, 5 scheduled tasks, multisite; premium: cloud ownership scan, Action Scheduler cleanup, unlimited tasks, filters, growth charts, activation timeline, per-site multisite.
- WP-Optimize free vs premium (checked 2026-09-28): https://wordpress.org/plugins/wp-optimize/ — premium: exact-time scheduling, multisite, per-table optimization, WP-CLI, preview before delete, WooCommerce query tweak, postmeta indexing.

- AAA Option Optimizer: https://wordpress.org/plugins/aaa-option-optimizer/
- Perfmatters (autoload/disable patterns): https://perfmatters.io/
- Competitor gap analysis: [[project_otm_competitor_analysis]]
