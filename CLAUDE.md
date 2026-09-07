# CLAUDE.md

## Commands

```bash
# JS (admin/src/ → admin/build/, React 2.0 SPA)
npm run build      # wp-scripts build admin/src/index.js --output-path=admin/build
npm run start       # watch mode
npm run lint         # wp-scripts lint-js admin/src
npx wp-scripts lint-js admin/src --fix   # auto-fix prettier/eslint issues

# PHP
composer install --no-dev   # production autoload (ships in the zip)
./vendor/bin/phpunit        # PHPUnit
```

Always run `npm run lint` (fixing anything it flags) **and** `npm run build` after any change under `admin/src/` — the compiled `admin/build/` output is what actually ships and renders; editing `admin/src/` alone changes nothing live.

## Architecture

- Main plugin class: `Nhrotm_Options_Table_Manager` (prefix `Nhrotm_`, namespace `Nhrotm\OptionsTableManager\`, PSR-4 → `includes/`).
- REST namespace: `nhrotm/v1` (routes: `nhrotm/v1/dashboard`, `nhrotm/v1/dashboard/activity`, `nhrotm/v1/browse`, `nhrotm/v1/browse/lookup`, `nhrotm/v1/browse/save`, `nhrotm/v1/browse/bulk-delete`, `nhrotm/v1/browse/{id}`, `nhrotm/v1/optimize` + `/disable-autoload` + `/delete-orphans` + `/reset-usage` + `/clean-transients`, `nhrotm/v1/tools/backups*`, `nhrotm/v1/tools/search-replace`, `nhrotm/v1/tools/export`, `nhrotm/v1/tools/import`, `nhrotm/v1/integrations` + `/{slug}`, `nhrotm/v1/settings`).
- Browse covers six record types: `options`, `usermeta`, `postmeta`, `commentmeta`, `termmeta`, `transients` — same CRUD + protected-key pattern across all of them (`BrowseService`).
- Usermeta/postmeta tabs have a "filter by user/post" search-as-you-type combobox (`admin/src/components/IdLookupFilter.js`) that resolves a typed name/title to an id via `GET nhrotm/v1/browse/lookup?target=post|user&search=`; the actual grid filter is always a plain `WHERE user_id = %d` / `WHERE post_id = %d` in `BrowseService::query_usermeta()`/`query_postmeta()` — the combobox exists only so a user isn't expected to know that id.
- Admin React SPA (`@wordpress/scripts`, no CSS framework, hand-rolled components) mounts at `#nhrotm-app` on `Tools → Options Table`. Source in `admin/src/`, compiled output in `admin/build/` (the only thing actually enqueued — `AppPage.php::enqueue()` reads `admin/build/index.asset.php` for the content-hash version, so cache-busting is automatic on every build; a stale-looking browser is almost never a caching bug, verify against a fresh build before assuming so).
- CSS: single hand-authored `admin/src/style.scss`, all custom properties (`--nhrotm-*`) scoped under `.nhrotm-app`. See **§14.4 below** — anything rendered outside that DOM subtree loses every token silently.
- `includes/Services/BrowseService.php` queries `wp_options`/`wp_usermeta` directly (prepared statements, whitelisted `ORDER BY` via `resolve_order()`). Note: `wp_options` has **no timestamp column** — `option_id`/`umeta_id` (auto-increment) is the only available proxy for "creation order," used for the default newest-first sort.

## UI implementation preferences (read before touching admin/src/ or style.scss)

This is the condensed, load-bearing checklist distilled from a full UI/UX polish pass. Full rationale, code-level detail, and the specific bugs each rule was born from: **`.ai/DESIGN.md` §14** (`UI implementation gotchas`) — read that section in full before making non-trivial CSS changes; this is the quick-reference version.

1. **Component consistency is non-negotiable.** Same button height/padding/radius/shape as existing `.nhrotm-btn`/`.nhrotm-iconbtn`/`.nhrotm-segmented__item` siblings — never introduce one-off pixel values for a "new" button. Every clickable element gets an explicit `:hover`. No exceptions — this has been audited and fixed once already app-wide.
2. **Every native form control (`input`, `textarea`, `select`, checkboxes, `a`) needs an explicit `:focus` override, and it must reset `outline`, `border-color`, *and* `box-shadow` together.** wp-admin core sets real styles on these elements (not just resets) — including a themed `box-shadow` ring — and winning the cascade for one property (e.g. `outline`) does nothing for the others core also sets. Half-fixing this is the single most repeated bug this session. See DESIGN.md §14.1.
3. **Focus convention: hover darkens the border, focus changes nothing.** `:hover { border-color: var(--nhrotm-text-muted); }`, `:focus { border-color: var(--nhrotm-border-strong); outline: none; box-shadow: none; }` — deliberately *no* visible ring or colour change on focus for text-like inputs. This was a considered reversal partway through the polish pass; don't add a focus ring back to a text input without checking with the user first.
4. **Checkboxes: never use `accent-color`.** Fully custom-drawn (`appearance: none` + `clip-path` checkmark) — `accent-color` checkboxes get a native focus halo Chrome paints that no CSS can suppress. Also explicitly reset `min-width: 0` (wp-admin sets `min-width: 1rem` with no matching `min-height`, which silently produces a non-square box) alongside the usual `outline`/`box-shadow` reset. See DESIGN.md §14.2 — this exact rule has regressed twice already from an incomplete rewrite.
5. **`.nhrotm-btn` variants need a real, visible `border-color` — never rely on the transparent base border.** A shadow alone (e.g. on hover) blends into a similarly-toned fill with nothing to anchor the button's shape.
6. **CSS custom properties (`--nhrotm-*`) only resolve inside `.nhrotm-app`'s DOM subtree.** Any provider/portal that renders UI (modals, toasts, tooltips) must be mounted as a *descendant* of `<AppShell>` (nest it under `children`), never as a wrapper around `<AppShell>` — a wrapper renders as a DOM *sibling* of `.nhrotm-app` and silently loses every token (fully transparent modal, no error). See DESIGN.md §14.4.
7. **Success feedback → `useToast()` (fixed, auto-dismissing, non-layout-shifting).** Never an inline notice that shifts the toolbar. Wording: **"X successfully."**
8. **Destructive/irreversible actions → `useConfirm()`.** Never `window.confirm`. Always pass a `description` (either a natural second sentence, or "This action cannot be undone." for plain deletes) — a confirm dialog with only a title reads as unfinished.
9. **Tables:** bold (`700`) headers, normal-weight (`400`) body content including name/key columns, zebra-striped rows (`nth-child(even)`, `--nhrotm-bg`) with a stronger `:hover` tint layered on top. Row actions are icon + text label, never icon-only.
10. **If a screen has default sort/filter state, check every code path that can reset it** (tab switches, type changes, not just the initial `useState`) — this regressed once when a tab-switch handler reset sort to a different default than the one on mount.
11. **A toolbar/header row's line count must never depend on whether its content happens to fit.** If different tabs/states render different amounts of toolbar content (Browse: some types have 0 extra filters, some 1, Transients has 2), letting `flex-wrap` decide per case means each type wraps at a different width, so the grid header sits at a different height per tab and switching tabs "jumps." Force a fixed row count instead (Browse's `.nhrotm-browse__tools` now carries `flex-basis: 100%` so it's always its own row on every type). See DESIGN.md §14.8 — this bit us once already via the loading-skeleton row count (`typeRowCountRef`) and came back in a new form when the Usermeta/Postmeta id-lookup filter was added.

## Documentation sync — required after every fix or feature change

Per the global doc-sync rule (`~/.claude/CLAUDE.md`): in the same turn as any fix or feature change here, update every doc that describes the changed behavior — before reporting the task done. For this plugin that means checking:

- `readme.txt` — the **Key Features / Description list**, not just the changelog entry.
- `.ai/PRD.md`, `.ai/PRD-PRO.md`, `.ai/DESIGN.md` — feature specs, Browse type/tab lists, comparison tables, roadmap/backlog.
- This file (`CLAUDE.md`) — the REST route list and Architecture section above.
- `README.md` — currently a stub; skip unless it grows real content.

Grep for the old state (old tab list, old route list, old feature name) across all of these in one pass rather than fixing one file and waiting to be pointed at the next stale one.

## Skills

- `.ai/skills/release_plugin.md` — version bump, PR, tag, publish procedure.
