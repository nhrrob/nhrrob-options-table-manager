# Design Spec — NHR Options Manager 2.0 (Free UI)

Status: Draft · Owner: Nazmul Hasan Robin (nhrrob) · Date: 2026-07-06

> Dev-only document. Excluded from distribution (`.distignore` + `.gitattributes export-ignore`).
> Companion to **[PRD.md](./PRD.md)** (free scope) and **[PRD-PRO.md](./PRD-PRO.md)** (monetization).

## 0. Design principles

1. **Native density, own identity.** Match wp-admin *spacing, typography, and information density* so it feels at home — but carry a **colorful, modern brand look** (gradients, colored cards/buttons). Not a gray wp-admin clone. Color is pure CSS — **zero bundle cost**, so it fully respects [[feedback_minimal_footprint]] / PRD §0.1. Still **no CSS framework**: colors ship as a small set of CSS custom properties, hand-authored.
2. **Dashboard-first.** Landing view answers "is my options table healthy, and what should I do next?" in one screen.
3. **One home per action.** Every task lives in exactly one section (PRD §2). No duplicated entry points.
4. **Progressive disclosure.** Show the score and top recommendations first; details are one click away.
5. **Minimal footprint.** Every component is hand-rolled or WP-provided. A library is added only if the bundle delta is negligible and measured.
6. **Accessible.** Keyboard-navigable, ARIA on interactive controls, WCAG-AA contrast, respects `prefers-reduced-motion`.

## 1. App shell

Single admin page under **Tools → Options Table**. React SPA mounts one root; sections are client-side routes (no full reloads).

```
┌──────────────────────────────────────────────────────────────────────┐
│  Options Table Manager                              [⟳ Refresh]  [⚙]   │  ← app bar
├────────────┬─────────────────────────────────────────────────────────┤
│ ◹ Dashboard│                                                          │
│ ▤ Browse   │                  ACTIVE SECTION CONTENT                  │
│ ⚡ Optimize │                                                          │
│ ⚒ Tools    │                                                          │
│ ⇄ Integr.  │                                                          │
│ ⚙ Settings │                                                          │
└────────────┴─────────────────────────────────────────────────────────┘
```

- **Left nav**: 6 items (icon + label), collapses to icons-only < 960px. Active item highlighted with WP admin accent.
- **App bar**: a **collapse toggle** (far left), plugin name (never "NHR" as a visible label — CLAUDE.md branding rule), global Refresh, and a theme toggle.
- **Collapse toggle**: shrinks the left nav to a 60px icon rail (labels hidden, `title=` tooltips retained) so data-heavy screens — chiefly Browse — get the full content width. State persists per user (localStorage / user meta). This is the primary "make the table bigger" affordance; the grid itself also scrolls horizontally inside its own container so it never pushes the page sideways. Below 900px the nav auto-collapses to the same rail.
- Nav is **data-driven from the module registry** (`nhrotm_modules`), so an add-on's sections/badges appear here without core edits — but the free build never renders upsell chrome.

## 2. Dashboard (landing)

```
┌──────────────────────────────────────────────────────────────────────┐
│  Health                                                                │
│   ╭───────╮   Good — a few easy wins available.                        │
│   │  82   │   Autoload 0.6MB · 41 expired transients · 7 orphans       │
│   │ / 100 │   Last backup: 2h ago                                      │
│   ╰───────╯                                                            │
├──────────────────────────────────────────────────────────────────────┤
│  ┌── Autoload ────┐ ┌── Options ─────┐ ┌── Transients ──┐ ┌─ Backup ─┐ │
│  │ 0.6 MB         │ │ 1,204 total    │ │ 128 (41 exp.)  │ │ 2h ago   │ │
│  │ 312 autoloaded │ │ 18 orphaned    │ │                │ │ 6 saved  │ │
│  │ [Review →]     │ │ [Scan →]       │ │ [Clean 41 →]   │ │ [New →]  │ │
│  └────────────────┘ └────────────────┘ └────────────────┘ └──────────┘ │
├──────────────────────────────────────────────────────────────────────┤
│  Recommendations                                                       │
│   ⚠ 41 expired transients are taking 220 KB      [Delete expired]      │
│   ⚡ 12 autoloaded options unused on front-end    [Review in Optimize]  │
│   ◔ No backup in 7 days                           [Create backup]      │
├──────────────────────────────────────────────────────────────────────┤
│  Recent activity                                                       │
│   • Restored option `siteurl`            2h ago                        │
│   • Auto snapshot before Import          1d ago                        │
└──────────────────────────────────────────────────────────────────────┘
```

- **Health score gauge**: single number 0–100 in a ring. Color bands — red < 50, amber 50–79, green ≥ 80. Formula per PRD §6 (autoload size vs 1 MB budget, expired-transient ratio, orphan count, options count, backup age). Ring rendered as inline SVG (no chart lib).
- **Stat cards** (4): Autoload, Options, Transients, Last Backup. Each = one headline metric, one sub-metric, one primary action that deep-links into the owning section. Cards are contributed by modules (`dashboard_cards()`), so PRO can append more.
- **Recommendations feed**: prioritized, actionable rows (severity icon + text + single button). Empty state: "Nothing to fix — your options table is healthy. 🎉"
- **Recent activity**: last ~5 events from Option History. Links to the full history.

## 3. Browse

Unified data browser merging Options · Usermeta · Transients.

```
┌──────────────────────────────────────────────────────────────────────┐
│ [ Options ▾ ]   🔎 Search…                    [+ Add]  [Bulk ▾]        │  ← type switcher + toolbar
├──────────────────────────────────────────────────────────────────────┤
│ ☐ │ Name                │ Value (preview)   │ Size  │ Autoload │  ⋯     │
│ ☐ │ siteurl             │ https://…         │ 24 B  │ ● yes    │ ✎ 🗑    │
│ ☐ │ _transient_feed_…   │ a:3:{…}           │ 1.2KB │  —       │ ✎ 🗑    │
│   │ …server-paginated, 50/page…                                        │
├──────────────────────────────────────────────────────────────────────┤
│  1–50 of 1,204                                   ‹  1  2  3 … 25  ›     │
└──────────────────────────────────────────────────────────────────────┘
```

- **Type switcher** (Options / Usermeta / Transients) as a dropdown or segmented control; drives which REST collection loads.
- **Data grid**: hand-rolled or minimal `@tanstack/react-table`, **server-side paginated** via `nhrotm/v1` (replaces DataTables). Columns: select, name, value preview, size, autoload badge, row actions.
- **Autoload column is read-only here** — a badge with a "Manage in Optimize →" link (PRD duplication rule). No toggle on this screen.
- **Row actions**: Edit (modal), Delete (confirm) as **compact icon buttons**, right-aligned, revealed/emphasised on row hover (not stacked red/black text links — that read as unfinished). Core-protected options show a muted `protected` label and expose **Edit only**, no Delete.
- **Edit modal**: serialized/JSON values shown as a structured, editable tree (parity with 1.5.x); "Allow HTML in values" setting respected.
- Transients view: shows status (persistent/expired/active) + expiry; delete individually. Bulk-by-scope lives in Optimize/Cleanup, not here (one home rule) — Browse keeps only per-row delete.

### 3.1 Table typography & density (the grid must read as a pro data tool)

The grid is the most-used surface, so its type discipline defines how professional the whole plugin feels. Rules:

- **Fixed column layout** (`table-layout: fixed`): checkbox 40px · name ~24% · **value = flex/remaining** · size 84px · autoload 128px · actions 88px. Only the value column flexes; everything else is stable so the eye tracks columns cleanly.
- **Value column truncates to a single line** — `white-space:nowrap; overflow:hidden; text-overflow:ellipsis`, full value on `title=` hover (and in the Edit modal). **Never wrap serialized blobs to 2–3 lines** — that was the main thing making the old table look loose and tall.
- **Type**: names in **mono 12.5px / 600** (identifiers), values in **mono 12px muted** (data), headers in **11px uppercase, .5px tracking, muted** on a `--nhrotm-bg` strip. Sizes/counts use `tabular-nums`, right-aligned.
- **Density**: row padding `9px 16px`, header row 40px. Denser than the 1.5.x DataTables view, comfortably scannable, not cramped.
- **Rows**: hairline `--nhrotm-border` separators, no zebra (zebra + colored badges = visual noise); a soft `--nhrotm-gradient-soft` hover tint instead.
- **Badges**: autoload `on/off` and `protected` are pills, not text. `protected` is a dot-less muted label (a state you can't change, not a status to watch).

## 4. Optimize

Sole owner of autoload editing + cleanup.

```
┌──────────────────────────────────────────────────────────────────────┐
│  Autoload health          0.6 MB / 1 MB budget   ▓▓▓▓▓▓░░░░  60%       │
│   Heaviest autoloaded options                     [Sort: size ▾]       │
│   • cron                     84 KB   ● yes   [Disable autoload]        │
│   • rewrite_rules            41 KB   ● yes   [Disable autoload]        │
├──────────────────────────────────────────────────────────────────────┤
│  Usage Tracker (front-end sampling)          Tracking since: 3d ago    │
│   12 autoloaded options never used on 340 sampled loads               │
│   • some_plugin_cache        6 KB    [Disable autoload]               │
│   [Reset tracking data]                                    ⓘ how it works│
├──────────────────────────────────────────────────────────────────────┤
│  Orphan scanner                                          [Scan now]     │
│   7 options look like leftovers from removed plugins                   │
├──────────────────────────────────────────────────────────────────────┤
│  Cleanup                                                                │
│   Expired transients: 41 (220 KB)   [Delete expired] [Delete all] …    │
│   Auto-cleanup:  ◯ off  ● daily                                        │
└──────────────────────────────────────────────────────────────────────┘
```

- Four stacked panels: **Autoload health**, **Usage Tracker**, **Orphan scanner**, **Cleanup**. Each panel is a module-owned card so it stays independent.
- Autoload budget bar: green→amber→red vs the 1 MB budget.
- Usage Tracker copy states plainly that it samples real front-end loads and never disables anything automatically (auto-disable is a PRO capability — but the free UI shows **no** PRO badge/upsell; it simply omits it).
- Bulk transient delete lives here (scope: expired / persistent / all).

## 5. Tools

Search & Replace · Import/Export · Backups (tabbed or stacked cards).

- **Search & Replace**: from/to fields, table scope, **Dry-run preview by default**, then Run. A pre-run snapshot is taken automatically (surfaced as a note: "A backup was saved before this change").
- **Import / Export**: JSON download / upload; auto-snapshot before import.
- **Backups**: list (label, type, option count, size, age) + Create, Restore (confirm), Delete. Shows the 15-snapshot local cap.

## 6. Integrations

Conditional section — only rendered when Better Payment / WPRM tables exist. Quarantined here so third-party tables never inflate the main nav. Same grid component as Browse, scoped to the integration's tables.

## 7. Settings

All settings centralized (PRD §2): Allow HTML in values, Auto-cleanup frequency, Usage-tracking on/off, Backup frequency (off/daily/weekly), **History retention** (migrated out of Optimizer). One `nhrotm_settings` object saved via REST.

## 8. Component inventory (build once, reuse)

| Component | Used by | Notes |
|---|---|---|
| `AppShell` (nav + app bar) | all | Registry-driven nav; collapsible icon rail (persisted) to widen content |
| `ScoreGauge` | Dashboard | Inline SVG ring, no chart lib |
| `StatCard` | Dashboard | metric + sub + action |
| `RecommendationRow` | Dashboard | severity + text + action |
| `DataGrid` | Browse, Integrations | Server-paginated; the one size-risk item — measure |
| `EditModal` | Browse | Serialized/JSON tree editor |
| `Panel` | Optimize, Tools, Settings | Titled card container |
| `BudgetBar` | Optimize | Autoload vs 1 MB |
| `ConfirmDialog` | delete/restore | Reused everywhere destructive |
| `Toast` | global | Replaces alerts (parity with 1.5.x) |

Keep the inventory this small — every new component is bundle weight.

## 9. Visual system — colorful & modern

The look is branded and vibrant, delivered entirely through a small set of CSS custom properties. All values below are the **starting palette** — easily retuned since they're tokens.

### Color tokens

```css
:root {
  /* Brand — violet/indigo primary (WPDeveloper-family energy) */
  --nhrotm-primary:        #6d5efc;   /* buttons, active nav, links */
  --nhrotm-primary-600:    #5b4ce6;   /* hover */
  --nhrotm-gradient:       linear-gradient(135deg, #6d5efc 0%, #9b5cff 100%);
  --nhrotm-gradient-soft:  linear-gradient(135deg, #eef0ff 0%, #f6eeff 100%);

  /* Status (also used for score bands & recommendation severity) */
  --nhrotm-success:        #12b76a;   /* green  */
  --nhrotm-warning:        #f79009;   /* amber  */
  --nhrotm-danger:         #f04438;   /* red    */
  --nhrotm-info:           #2e90fa;   /* blue   */

  /* Per-card accent tints (dashboard stat cards feel distinct, not gray) */
  --nhrotm-card-autoload:   #eef4ff;  /* accent #2e90fa */
  --nhrotm-card-options:    #f4f0ff;  /* accent #6d5efc */
  --nhrotm-card-transients: #fff4ec;  /* accent #f79009 */
  --nhrotm-card-backup:     #ecfdf3;  /* accent #12b76a */

  /* Neutrals */
  --nhrotm-surface:        #ffffff;
  --nhrotm-bg:             #f7f8fc;   /* app canvas — soft, not white */
  --nhrotm-border:         #eaecf5;
  --nhrotm-text:           #101828;
  --nhrotm-text-muted:     #667085;
  --nhrotm-radius:         12px;      /* rounded, modern */
  --nhrotm-shadow:         0 1px 3px rgba(16,24,40,.08), 0 1px 2px rgba(16,24,40,.06);
}
```

### Application

- **App bar**: `--nhrotm-gradient` header band with white plugin name + light controls (the signature "hero" strip modern plugins use).
- **Left nav**: active item filled with `--nhrotm-primary` (or a soft gradient pill); inactive = muted text with an accent hover.
- **Score gauge**: ring stroke uses the band color (danger/warning/success); the number sits on a `--nhrotm-gradient-soft` disc.
- **Stat cards**: each on its own tinted background (`--nhrotm-card-*`) with a matching accent icon and a colored primary button — so the 4 cards read as a lively row, not gray boxes.
- **Buttons**: primary = gradient/solid `--nhrotm-primary` with white text; secondary = outline; destructive = `--nhrotm-danger`. All with `--nhrotm-radius` corners and the soft shadow on hover.
- **Recommendations**: left severity stripe in the status color; action button in `--nhrotm-primary`.
- **Badges**: autoload/status pills use tinted status colors, not plain gray.
- **Canvas**: `--nhrotm-bg` (soft off-white), cards on `--nhrotm-surface` with `--nhrotm-shadow` + rounded corners for depth.

### Foundations
- **Type/spacing**: inherit wp-admin density (`13–14px` base, system font stack), 8px grid — colorful but not bloated.
- **Icons**: Tabler (in-house set), tree-shaken; no icon-font; icons inherit accent colors.
- **Contrast**: all colored buttons/text meet WCAG-AA against their background (verify `--nhrotm-primary` on white ≥ 4.5:1; darken to `--nhrotm-primary-600` for text if needed).
- **Motion**: subtle (150ms) fades + gradient-button hover lift only; disabled under `prefers-reduced-motion`.
- **Consistency**: reuse the same token set the PRO add-on will inherit, so free and PRO look like one product family.

## 10. States & responsiveness

- Every async view has **loading (skeleton) · empty · error · success** states.
- Empty states are encouraging and actionable, never dead ends.
- Errors show the REST message + a retry; never a blank screen.
- Breakpoints: ≥1200 full · 960–1199 icons-only nav · <960 nav becomes a top dropdown, grid → stacked cards.

## 12. PRO awareness — visual realization (PRD §0.2)

The free build must let users *discover* PRO without feeling like an ad. Two — and only two — surfaces:

### `Pro` tag

A small, low-contrast pill placed inline next to a feature label whose free version is manual and whose PRO version adds automation/scale.

```
Auto-cleanup:  ◯ off  ● daily        Scheduled hourly→monthly  [ Pro ]
```

- Style: `--nhrotm-pro` tint background, no gradient, no icon-fill, 11px, uppercase, `letter-spacing`. **Muted, not brand-loud** — it should read as metadata, not a button. Deliberately quieter than a status badge.
- Behaviour: the underlying free control still works. Clicking the pill routes to `#/upgrade?from=<feature>`. Cursor `help`/`pointer`; `title="Available in Pro"`; `aria-label` announces it.
- Placement budget: at most one tag per panel. Tags allowed on — Scheduled cleanup, Auto-disable autoload, Regex/all-table Search & Replace, Off-site/unlimited backups, Reports & alerts, Multisite. Never on a plain free feature.

### `Upgrade` screen

Reached from the pinned, de-emphasized bottom nav item (a hairline separates it from the six sections; muted color, not the active-gradient treatment). It is one calm screen:

```
┌──────────────────────────────────────────────────────────────────────┐
│  Options Table Manager Pro                                             │
│  Automation, scale, and safety on top of everything you already have.  │
├──────────────────────────────────────────────────────────────────────┤
│  Capability                     Free            Pro                    │
│  Browse / edit / autoload        ✓               ✓                     │
│  Usage Tracker                   ✓  full         + auto-disable        │
│  Cleanup                         manual          scheduled             │
│  Search & Replace                dry-run         + regex / all tables  │
│  Backups                         15, local       unlimited + off-site  │
│  Reports & alerts                —               email + thresholds    │
│  Multisite                       —               ✓                     │
├──────────────────────────────────────────────────────────────────────┤
│                         [ View plans → ]                               │
│         One outbound link. No price countdowns, no second CTA.         │
└──────────────────────────────────────────────────────────────────────┘
```

- The comparison table reuses `--nhrotm-*` tokens; the header strip may use `--nhrotm-gradient-soft` (not the full hero gradient) so it stays understated.
- Exactly **one** primary button (`View plans →`, outbound). No timers, no "SALE", no testimonials carousel, no repeated CTA.
- When the PRO add-on is active, `boot.hasPro === true` → the free build hides this nav item and suppresses every `Pro` tag (PRD-PRO §0 handoff).

### Token addition

```css
--nhrotm-pro:      #f4f0ff;  /* pill tint — soft, shares the options-card family */
--nhrotm-pro-text: #6d5efc;  /* pill text — brand, but small + uppercase = quiet */
```

## 13. Dark theme

The mockup and the SPA both support dark. Because the whole system is token-driven, dark is a token remap under `@media (prefers-color-scheme: dark)` **and** a `[data-theme="dark"]` override on the app root (a small header toggle stamps it) — no per-component work.

```css
/* dark remap (surfaces/text/border invert; brand + status hues hold) */
--nhrotm-surface: #1b1e2b;  --nhrotm-bg: #14161f;  --nhrotm-border: #2a2e3f;
--nhrotm-text: #e7e9f2;     --nhrotm-text-muted: #9aa1b8;
--nhrotm-gradient-soft: linear-gradient(135deg,#242a4d 0%,#2c2350 100%);
/* card tints darken to ~12% alpha of their accent; --nhrotm-shadow deepens */
```

Rules: brand violet and the four status hues are **theme-invariant** (recognisable in both). Card tints become low-alpha accent washes rather than pastel fills. Contrast re-checked for AA in dark (muted text lightened). `prefers-color-scheme` is the default; the explicit toggle wins in both directions.

## 11. Open questions

- ~~`DataGrid`: hand-roll vs `@tanstack/react-table`~~ → **hand-rolled** (PRD §8: bundle budget). 
- Health-score band thresholds — validate 50/80 against real sites before locking.
- Type switcher: dropdown vs segmented control (a11y + narrow-width behavior). Mockup uses a **segmented control** (clearer state, keyboard-navigable); revisit for very narrow widths.
- `Pro` tag exact hue vs the options-card tint — confirm they're distinguishable side by side (Optimize shows both).
