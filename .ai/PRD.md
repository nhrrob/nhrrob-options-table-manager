# PRD — NHR Options Manager 2.0 (Free)

Status: Draft for sign-off · Owner: Nazmul Hasan Robin (nhrrob) · Date: 2026-06-29
Last revised: 2026-08-05 (PRO-awareness policy added; see §0.2)

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
2. **Browse** — Options · Usermeta · Transients in one data browser with a type switcher. (merges 3 tabs; removes the duplicate transient filter on the Options tab)
3. **Optimize** — Autoload health · Usage Tracker · Orphan Scanner · Cleanup (+ schedule). Sole owner of the autoload toggle.
4. **Tools** — Search & Replace · Import/Export · Backups.
5. **Integrations** — Better Payment · WPRM (conditional; quarantined so it stops inflating core nav).
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
| Browse/edit options, usermeta, transients | Single data browser w/ type switcher; React data-grid, REST-paginated. |
| Autoload health + manual toggle | Sole owner of autoload toggle; size analysis vs 1 MB budget. |
| **Autoload Usage Tracker** | Fully free. Records used autoloaded options on real front-end loads; flags never-used ones with one-click disable. Front-end-only sampling (non-admin/ajax/cron/REST). |
| Orphan scanner | Manual scan for leftovers from uninstalled plugins. |
| Search & Replace | Manual, with dry-run preview; auto-snapshot before a live run. |
| Import / Export | JSON portability between sites; auto-snapshot before import. |
| Backups / snapshots | Local snapshots of `wp_options`, manual + daily/weekly cron, restore, last 15 pruned. |
| Cleanup | Manual + scheduled deletion of expired transients. |
| Option History & Rollback | Per-option change tracking + restore; retention setting moves to Settings. |
| Integrations | Better Payment / WPRM tables, quarantined under Integrations. |
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
- Whether Integrations (Better Payment/WPRM) should eventually move to their own free add-on.
- Deprecation window length for legacy `nhrotm_*` option keys after the settings migration.

## 9. Sources

- AAA Option Optimizer — https://wordpress.org/plugins/aaa-option-optimizer/
- Perfmatters (autoload/disable patterns) — https://perfmatters.io/
- (Monetization/competitive sources live in [PRD-PRO.md](./PRD-PRO.md).)
