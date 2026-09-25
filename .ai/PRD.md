# PRD — NHR Options Manager 2.0 (Free)

Status: Draft for sign-off · Owner: Nazmul Hasan Robin (nhrrob) · Date: 2026-06-29
Last revised: 2026-09-07 (Browse gained postmeta/commentmeta/termmeta parity with usermeta, plus a search-as-you-type post/user filter on those two tabs — see §4, §8.1)

> Dev-only document. Excluded from distribution (`.distignore` + `.gitattributes export-ignore`).
> PRO/monetization scope lives in a separate doc: **[PRD-PRO.md](./PRD-PRO.md)**.

## 0. Locked decisions (free scope)

| Decision | Choice |
|---|---|
| Frontend revamp | **Full React rewrite** (`@wordpress/scripts` SPA), built as parallel `2.0` |
| Autoload Usage Tracker | **Fully free** (acquisition hook — no paywalled tier) |
| Extensibility | **Module registry** with `nhrotm_modules` filter — the single hook the PRO add-on consumes |
| Backend | **REST-only** (`nhrotm/v1`); retire the monolithic `AjaxHandler` switch |
| PRO awareness in free UI | **Tasteful, low-touch** — subtle `Pro` tags on locked features + one in-plugin **Upgrade** screen. No banners/nags. See §0.2 |

Monetization, pricing, licensing (Freemius), and the free-vs-PRO split are **out of scope for this doc** → see [PRD-PRO.md](./PRD-PRO.md).

## 0.2 PRO awareness in the free UI (policy)

Users **should be able to discover that a PRO version exists**, without the free plugin feeling like an ad. This replaces the earlier "zero upsell surface" stance — the new rule is *quiet discoverability*, not *invisibility*.

Allowed (and the only allowed) PRO surfaces in the free build:

1. **`Pro` tags** — a small, muted pill next to features that are free-manual today but gain automation/scale in PRO (e.g. "Scheduled cleanup", "Auto-disable autoload", "Off-site backups"). The tag is informational; the underlying free feature still works. Clicking a tag routes to the Upgrade screen.
2. **One `Upgrade` screen** — a single, calm section (last nav item, visually de-emphasized) with a plain free-vs-PRO comparison table and **one** outbound button ("View plans →"). No countdown timers, no "SALE", no repeated modals.

Forbidden: dashboard banners, popups/toasts pushing PRO, "limited time" language, more than one outbound CTA per view, colored/animated nag chrome, or gating any 1.5.x-parity feature behind the tag.

WP.org compliance: this is the same pattern used by compliant freemium plugins (single upgrade menu + inert feature tags). The `Upgrade` nav item and all `Pro` tags are **rendered by the free build itself** (static, no Freemius in free) so review is trivial. Author-identity/branding rules (no visible "NHR" label) still apply.

**Currently OFF — PRO doesn't exist yet.** All of it (Upgrade nav item + every `Pro` tag) is gated on one flag, `apply_filters('nhrotm_pro_available', false)` in `AppPage.php`, localized to React as `boot.proAvailable`. It defaults `false`, so none of this UI renders in the shipped build today — no vaporware upsell. **To switch it on once PRO ships:** flip that one `false` to `true` in `AppPage.php` (`includes/Admin/AppPage.php`), rebuild (`npm run build`), release. No other file needs to change. This is separate from `hasPro` (`nhrotm_has_pro` filter), which stays about whether *this specific install* owns a PRO license once PRO exists.

Design realization of this policy → [DESIGN.md](./DESIGN.md) §12.

## 0.1 Hard constraint — minimal footprint (non-negotiable)

**The shipped plugin zip must not grow.** Minimal code, minimal dependencies, minimal build output. This governs every decision below; if a choice adds weight, it needs an equal-or-larger removal to justify it.

- **No new runtime PHP dependencies.** `vendor/` stays dev-only (autoloader only). No Composer prod libs.
- **React rewrite must be net-neutral or smaller.** `@wordpress/scripts` externalizes React + all `wp-*` packages (provided by WP core — not bundled). We *remove* jQuery + DataTables from our assets; the React build replaces them. Target: `admin/build/` ≤ the current `assets/` payload it supersedes.
- **Prefer a tiny/no data-grid library** — build a minimal grid over `@tanstack/react-table` only if the bundle delta is negligible; otherwise hand-roll. Measure before adding.
- **No JS/CSS frameworks** beyond what `@wordpress/scripts` + WP core already provide. Reuse the Tabler-icon/CSS approach already in-house.
- **CI size gate**: track the built zip size; a PR that increases it must document why. Run `check:pcp` + a size diff before cutover.

## 1. Problem & goals

The free plugin reached feature sprawl: ~11 tabs with real duplication (transients, autoload editing, cleanup, and settings each have multiple entry points). The IA grew tab-by-tab per release with no consolidation pass, so related actions are scattered and some appear in two places.

Goals:
1. **Consolidate** 11 tabs → 6 sections with a dashboard-first UX, removing all duplication.
2. **Re-architect** for extensibility (module registry + REST + centralized settings) so features are self-contained and a future add-on can extend core without modifying it — keeping any PRO awareness to the quiet, single-surface pattern in §0.2 (WP.org rule).
3. **Modernize the frontend** to a React SPA for a faster, more maintainable UI with a modern, professional look (see [DESIGN.md](./DESIGN.md)).

Non-goals: changing author identity; dropping existing DB tables; breaking existing WP-CLI commands; adding *marketing-heavy* upsell chrome (banners/popups/nags) — quiet PRO discoverability per §0.2 is in scope, aggressive upsell is not.

## 2. Information architecture (target)

11 tabs → 6 sections:

1. **Dashboard** — health score (0–100), recommendations feed, quick actions, recent activity. (NEW)
2. **Browse** — Options · Usermeta · Postmeta · Commentmeta · Termmeta · Transients in one data browser with a type switcher. (merges 3 tabs; removes the duplicate transient filter on the Options tab)
3. **Optimize** — Autoload health · Usage Tracker · Orphan Scanner · Cleanup (+ schedule). Sole owner of the autoload toggle.
4. **Tools** — Search & Replace · Import/Export · Backups.
5. **Integrations** — third-party tables such as WPRM (conditional; quarantined so it stops inflating core nav).
6. **Settings** — all settings centralized (incl. history retention, currently misplaced inside Optimizer).

Plus one **de-emphasized** nav item pinned to the bottom, visually separated from the six functional sections:

7. **Upgrade** — the single PRO-awareness surface (§0.2). Not a "feature"; a quiet comparison + one outbound CTA. Rendered by the free build; never registered through the `nhrotm_modules` add-on hook (so an installed PRO add-on can hide it).

### Duplication to eliminate
- **Transients**: one home (Browse); remove the "All Transients" filter + "Delete Expired" button from Options.
- **Autoload editing**: Optimize owns it; Browse shows a read-only autoload badge linking to Optimize.
- **Cleanup**: single home under Optimize (manual + scheduled).
- **Settings**: single Settings section; migrate history-retention out of Optimizer.

## 3. Architecture (free core)

- **Module registry**: every feature implements `ModuleInterface` (`id`, `label`, `capability`, `register_routes`, `dashboard_cards`, `render`). `ModuleRegistry` exposes `apply_filters('nhrotm_modules', $modules)` — the single extension point an add-on can use. Core modules: Dashboard, Browse, Optimize, Tools, Integrations, Settings.
- **REST API only** (`nhrotm/v1`), per-module controllers; retire the monolithic `AjaxHandler` switch. Two-layer auth: route capability gate (`manage_options`) + per-object `current_user_can` on ID-taking routes.
- **Centralized settings**: collapse scattered `nhrotm_*` options into one `nhrotm_settings` array behind a Settings service; migrate on upgrade (see §5).
- **Frontend**: full React SPA via `@wordpress/scripts`, mounted on the admin page; reuse the Tabler-icon/CSS approach from `nhrrob-smart-media-manager`. Drop jQuery + DataTables (replace tables with a React data-grid, server-side paginated via REST).
- **Build/dist**: ship `admin/build/` (never excluded); keep `admin/src/` in the zip per WP.org. `vendor/` dev-only; plugin self-autoloads (`--no-dev` if a prod dep is added).

### Module contract (sketch)

```php
interface ModuleInterface {
    public function id(): string;            // 'optimize'
    public function label(): string;         // 'Optimize'
    public function capability(): string;    // 'manage_options'
    public function register_routes(): void; // REST controllers for this module
    public function dashboard_cards(): array;// cards this module contributes
    public function render(): void;          // React mount metadata / localize
}
```

## 4. Free feature specs (2.0)

Everything shipped in 1.5.x remains free and reaches parity before cutover:

| Feature | Notes for 2.0 |
|---|---|
| Browse/edit options, usermeta, postmeta, commentmeta, termmeta, transients | Single data browser w/ type switcher; React data-grid, REST-paginated. Postmeta/commentmeta/termmeta added 2026-09-07, same CRUD + protected-key pattern as usermeta. Usermeta/postmeta filterable to one user/post via a search-as-you-type name/title picker (added 2026-09-07). |
| Autoload health + manual toggle | Sole owner of autoload toggle; size analysis vs 1 MB budget. |
| **Autoload Usage Tracker** | Fully free. Records used autoloaded options on real front-end loads; flags never-used ones with one-click disable, filterable (All/Unused/Untracked/Used) with bulk-disable across the filtered set. Front-end-only sampling (non-admin/ajax/cron/REST). |
| Orphan scanner | Manual scan for leftovers from uninstalled plugins. |
| Search & Replace | Manual, with dry-run preview; auto-snapshot before a live run. |
| Import / Export | JSON portability between sites; auto-snapshot before import. |
| Backups / snapshots | Local snapshots of `wp_options`, manual + daily/weekly cron, restore, last 15 pruned. |
| Cleanup | Manual + scheduled deletion of expired transients. |
| Option History & Rollback | Per-option change tracking + restore; retention setting moves to Settings. |
| Integrations | Third-party tables (e.g. WPRM), quarantined under Integrations. |
| WP-CLI | Existing `nhr-options list/delete` commands preserved. |

## 5. Migration / rollback (full-rewrite risk control)

- Build 2.0 on a branch; keep 1.x maintained for fixes.
- 2.0 must reach **feature parity** with 1.5.x before cutover.
- One-time settings migration on activate: fold scattered `nhrotm_*` options into `nhrotm_settings`; keep reading legacy keys during a deprecation window.
- DB tables (`nhrotm_option_history`, `nhrotm_option_backups`) unchanged.
- Ship a beta channel (GitHub zip) before the WP.org cutover.

## 6. Dashboard spec (free)

Health score = weighted blend of: autoload size vs 1 MB, expired-transient ratio, orphan count, options count, backup age. Cards: **Autoload**, **Options**, **Transients**, **Last Backup** (each with a primary action). Recommendations feed = prioritized actionable items. Recent activity sourced from history. Add-ons can register extra cards/recommendations through the `nhrotm_modules` hook. **The dashboard itself carries no PRO surface** — no banner, no card, no recommendation pushes PRO (§0.2 confines PRO awareness to inert feature tags + the Upgrade screen).

## 7. Roadmap (free)

- **Phase 0** — PRD sign-off (this doc).
- **Phase 1** — Free 2.0 core: module registry + REST + settings service + React shell, with parity for existing features; consolidated IA. No new features.
- **Phase 2** — Dashboard + health score.
- **Phase 3+** — see [PRD-PRO.md](./PRD-PRO.md).

## 8. Open questions (free)

- ~~React data-grid: build vs library~~ → **Resolved: hand-rolled grid** (no `@tanstack/react-table`). Current `admin/build/index.js` is ~34 KB; a table lib would breach §0.1. Server-side pagination via REST keeps the hand-rolled grid simple.
- ~~PRO awareness in the free UI~~ → **Resolved in §0.2**: quiet discoverability (feature tags + one Upgrade screen), not invisibility.
- ~~Should Safe Redirect Manager be added to Integrations?~~ → **Resolved: no.** Confirmed via its own source — no `CREATE TABLE`/`dbDelta` anywhere; redirects are a `redirect_rule` custom post type (`class-srm-post-type.php:647`) with fields stored as postmeta, entirely inside core's `wp_posts`/`wp_postmeta`. `IntegrationsService` only supports standalone third-party tables (`available()`/`rows()` both key off `SHOW TABLES LIKE`); there's no table to point it at. Making SRM browsable would need a generic custom-post-type viewer, a different feature from Integrations, not a `$definitions()` entry.
- Whether Integrations (WPRM and future third-party tables) should eventually move to their own free add-on.
- Deprecation window length for legacy `nhrotm_*` option keys after the settings migration.
- Should the in-app header's plugin name ("Options Table Manager," `AppPage.php:130`) change? Found this plugin currently has **three different name variants** across its surfaces: the WP.org listing/plugin-header name is "NHR Advanced Options Table Manager & Autoload Optimizer" (`readme.txt` + `nhrrob-options-table-manager.php`, in sync with each other); the WP admin Tools submenu label is the terser "Options Table" (`AppPage.php` ~60-61); the in-app header is "Options Table Manager." Lean: keep the in-app header short — the WP.org title's "& Autoload Optimizer" suffix is discoverability/SEO copy for the plugin repository, not core identity, and doesn't need to be repeated in a compact app header bar; if anything, tightening it to match the even more minimal Tools-menu label ("Options Table") would be more consistent than lengthening it. This is a naming call, not something to decide unilaterally — needs an explicit answer.
- Whether to expose an MCP (Model Context Protocol) server so AI agents can query/edit the options table directly. No prior art in this plugin. In tension with the §0.1 minimal-footprint constraint for the free plugin, and it's a new write-access surface (an agent mutating `wp_options`/usermeta/postmeta/etc.) that needs its own auth story — needs real scoping before it's worth building, likely a PRO/companion-add-on candidate rather than free-core.
- Should Tools → Backups get a manual "Download snapshot" button (a DIY way to get a snapshot off the server, e.g. before a migration or to archive past the last-15-kept prune)? In tension with PRD-PRO.md's guiding rule — "safety (snapshots/recovery/off-site) are paid" — and the free Backups panel's own inline PRO teaser ("Unlimited + off-site backups & emergency recovery"). A manual one-off download arguably stays on the free side of that line (per [[feedback_no_pro_features]], the free plugin shouldn't feel crippled), but it's functionally a DIY off-site copy, so worth a deliberate call rather than building it by default.

## 8.1 Backlog / to-do (unscheduled — planned, not yet phased)

Features to add. Move an item into §7 Roadmap once it's actually scheduled; don't let this list silently become the roadmap.

- [ ] General DB cleanup: post revisions/auto-drafts/trash, spam/pingback comments.
- [ ] Orphan + duplicate meta-row scanner (postmeta/commentmeta/termmeta/usermeta) — finds meta rows pointing at deleted posts/comments/terms/users, plus exact-duplicate meta rows, with bulk delete. Doesn't exist for any meta type yet — the existing Optimize orphan scanner (`ScannerManager`) only scans `wp_options` prefixes, not meta tables; not the same feature as browse+edit parity (already shipped).
- [ ] Table OPTIMIZE/REPAIR.
- [ ] Cron job management (view/edit/delete scheduled cron events).
- [ ] Multisite support (network-admin view, per-site vs. network-wide handling).
- [ ] True tree/GUI value editor (vs. current JSON textarea).
- [ ] Retire the legacy Classic (jQuery/DataTables) UI once 2.0 is proven in the wild — footprint win (~1699-line `assets/js/admin.js` removed).
- [ ] **Breaking:** `nhrotm-options-table-manager/menu/capability` filter renamed to `nhrotm_menu_capability` (WPCS `ValidHookName` requires underscores) — shipped in v1.0.1–v1.4.3, so this breaks any integrator using the old name. Changelogged under unreleased 2.0.0 (readme.txt). Decide: ship as a hard break, or add a back-compat shim (fire both hooks, or alias old→new) before 2.0.0 release.
- [ ] `composer install` fails to fetch `10up/wp_mock` with the current global GitHub PAT — the fine-grained token gets a 403 on the `10up` org specifically (other orgs work fine; anonymous/no-token install also works). Workaround documented in CLAUDE.md (`COMPOSER_HOME=$(mktemp -d) composer install`). Real fix is re-scoping the PAT to include the `10up` org, outside this plugin's repo.
- [ ] Decide whether Integrations (WPRM and future third-party tables) should move to its own free add-on (duplicate of the open question in §8).
- [ ] Decide deprecation-window length for legacy `nhrotm_*` option keys after the settings migration (duplicate of the open question in §8).
- [ ] Retake WP.org screenshots (`.wordpress-org/screenshot-1..6`, dated Jan 2026) — they still show the pre-2.0 DataTables UI, predating both the React rewrite (2026-09-06) and the postmeta/commentmeta/termmeta Browse tabs (2026-09-07). Must ship updated before the 2.0.0 WP.org release; readme.txt Screenshots section copy also needs rewriting to match (currently describes "DataTable view of the wp_options table" etc., not the new Dashboard/Browse/Optimize screens).
- [ ] **Feature (pending the open question above):** If a Backups "Download snapshot" button is approved, it can't just dump `BackupManager`'s raw stored JSON — confirmed the two formats are incompatible with the existing Import flow. A snapshot's `data` column is a flat array (`[{option_name, option_value, autoload}, ...]`), but `ImportExportManager::preview_import()` (`includes/Managers/ImportExportManager.php` ~line 115) requires a wrapped object (`{meta: {...}, options: [{name, value, autoload}, ...], checksum: ...}`) and checks `isset($json_data['options'])` first thing — a raw snapshot download fed straight into Import would fail immediately with "Invalid import file structure," and even past that check the field names don't match (`option_name`/`option_value` vs `name`/`value`). Needs either a conversion step when serving the download (reshape into the Export format) or extending Import to accept both shapes.
- [ ] **Feature idea (needs a decision, not a default build):** Search & Replace could show live "N options, M occurrences" feedback as the user types the Search field, before Dry Run is even clicked. `SearchReplaceManager::preview_search()` (~line 67) already does exactly this — returns `[{option_name, occurrences}, ...]` for a search string — but it's currently only wired into the legacy AJAX handler (`includes/Ajax/AjaxHandler.php` ~line 639), not exposed via the REST API or called anywhere in the current React UI, so it's dead code from the 2.0 rewrite's perspective. Note: an option-*name* autocomplete (the pattern `ImportExportManager::search_options_for_export()` and `IdLookupFilter` already use elsewhere in the app) would NOT fit here — this feature searches inside option *values*, not names, so suggesting matching option names would misrepresent what's being searched. A live match-count via `preview_search()` fits the feature's actual semantics; a name-suggestion dropdown doesn't.
- [ ] **Design gap (not a bug — verified the current 0-row Recipe Maker table is legitimate):** confirmed `wp-recipe-maker` is active and `wp_wprm_ratings` genuinely has 0 rows on this dev site (no ratings submitted yet) — the "0 rows" display is correct. But the check exposed a real gap: `IntegrationsService::available()` decides what to list purely by `SHOW TABLES LIKE '{table}'` (~line 47) — it never checks whether the source plugin is actually active, only whether its table happens to exist. If WP Recipe Maker (or Better Payment) were later deactivated or fully deleted while its table stayed behind, Integrations would keep listing and letting you browse that now-orphaned table indefinitely, with no indication the plugin is gone. Worth deciding whether `available()` should also check `is_plugin_active()`-equivalent, or at least flag a table whose owning plugin isn't currently active.
- [ ] **Bug, found 2026-09-21 while implementing the Activity tab — blocks safely adding a "Restore" action to it:** `HistoryManager::restore_version()` unconditionally calls `update_option( $record['option_name'], ... )` on whatever `option_name` a history row stored — but `BrowseService::save()`/`remove()` log `usermeta`/`postmeta`/`commentmeta`/`termmeta` changes into the *same* table under the *same* generic `'update'`/`'create'` action strings, storing the meta row's `meta_key` in that same `option_name` column (e.g. `BrowseService.php` ~1026, ~1055, ~1084, ~1113 all call `$this->activity->record( 'update', $existing['meta_key'], ... )`). There is no `record_type` column, so a history row's real type (options vs. one of 4 meta tables) can't be recovered from the row alone. Restoring a meta-table row through `restore_version()` would silently call `update_option()` with that meta key as the option name — writing a bogus, unrelated `wp_options` row instead of restoring the actual usermeta/postmeta/commentmeta/termmeta value, with no error or warning. This was previously unreachable in practice (the Classic UI's per-option history view only ever showed `option_name`-scoped history, via `get_history( $option_name )` called from an options-specific screen), but the new unified Activity feed aggregates every record type together, so exposing "Restore" there for the first time would surface this risk broadly. **Needs a schema migration** (add a `record_type` column to `wp_nhrotm_option_history`, backfill best-effort from the distinct `delete_usermeta`/`delete_postmeta`/etc. action strings where determinable, mark the rest `unknown`) before a "Restore" action can be safely added to the Activity tab — restore should refuse (or route to the correct table's update function) for anything not confirmed `options`.
- [ ] **Needs a decision, not a clear bug:** Dashboard's health-score panel (`.nhrotm-health__copy`, `style.scss` ~654-671) already has the headline `h3` bold (`font-weight: 700`) and the description `p` at normal weight — no override needed there, it already matches what was asked for. If it still reads as "too bold" overall, the likely source is one of two *other* bold elements in the same panel: the gauge's center score number (`ScoreGauge.js`, wrapped in `<strong>`, bold via the browser default) and/or the stat-figures strip below it (`.nhrotm-health__stats b`, ~682-686, deliberately bold to set numbers off from labels — same convention as the Dashboard card metrics). Needs the user to say which of these, if either, should also drop to normal weight before a change is made.
- [ ] **Needs a decision, not a clear bug:** the health gauge's warning-band color (`ScoreGauge.js` `band()`, scores 50-79) uses `var(--nhrotm-warning)` (`#f79009`) — the same shared token used for every warning-severity element app-wide (Dashboard's Transients card accent, warning recommendations, etc.), not an inconsistent one-off. Painted as a thick 12px full ring rather than a small icon, the same color reads more intense than it does elsewhere as an accent. Since it's a shared token, changing it affects every warning-context usage in the app at once — needs a decision on whether that's wanted (one consistently softer warning color everywhere) versus giving the gauge its own dedicated shade that diverges from the rest of the app.

## 9. Sources

- AAA Option Optimizer — https://wordpress.org/plugins/aaa-option-optimizer/
- Perfmatters (autoload/disable patterns) — https://perfmatters.io/
- (Monetization/competitive sources live in [PRD-PRO.md](./PRD-PRO.md).)
