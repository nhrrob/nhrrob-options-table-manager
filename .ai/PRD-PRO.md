# PRD — NHR Options Manager PRO (Monetization)

Status: Draft for sign-off · Owner: Nazmul Hasan Robin (nhrrob) · Date: 2026-06-29
Last revised: 2026-08-05 (aligned with free-side PRO-awareness policy, PRD §0.2)

> Dev-only document. Excluded from distribution (`.distignore` + `.gitattributes export-ignore`).
> Free/core scope lives in a separate doc: **[PRD.md](./PRD.md)**.

## 0. Locked decisions (PRO scope)

| Decision | Choice |
|---|---|
| Monetization | **Hybrid** — annual subscription (MRR) + lifetime tier |
| Delivery | **Separate PRO add-on plugin** hooking core via `nhrotm_modules` |
| Licensing/billing | **Freemius** (in the PRO add-on only — never in free) |
| Free-side discoverability | **Quiet, allowed** — inert `Pro` feature tags + one static `Upgrade` screen (free renders these itself). See [PRD.md](./PRD.md) §0.2 |

The PRO add-on depends on the free 2.0 core (module registry + REST + settings service) shipping first — see [PRD.md](./PRD.md) §3, §7.

**Handoff between free and PRO.** When the PRO add-on is active it registers a `nhrotm_pro` capability flag; the free build reads it and (a) hides the `Upgrade` nav item, (b) suppresses all `Pro` tags, and (c) lets PRO replace the inert tag targets with live controls. So a user never sees upgrade chrome once they've upgraded. The free→PRO deep-link carries the source feature (e.g. `?nhrotm_from=scheduled-cleanup`) so PRO can land the user on the right panel.

## 1. Strategy

There is a proven paid market for WP database/options tooling (Advanced Database Cleaner PRO, Perfmatters, WP Reset PRO). The free plugin is the acquisition funnel; PRO monetizes **automation, scale, and safety** on top of it. The free Autoload Usage Tracker is a deliberate acquisition hook and is *not* paywalled.

Guiding rule: **manual one-off actions stay free; automation (scheduling), scale (multisite/agency), and safety (snapshots/recovery/off-site) are paid.**

## 2. Competitive map (monetization blueprint)

| Product | Model | Price | Paywalled features (= our PRO map) |
|---|---|---|---|
| Advanced Database Cleaner PRO | Lifetime | $39 / $59 / $149 | Scheduled cleanup, categorize options/tables by creator, orphaned tables |
| Perfmatters | Annual | $24.95 / $54.95 / $124.95 | Script manager, disable autoload, limit revisions |
| WP Reset PRO | Sub + lifetime | ~$59–$199 | Snapshots, collections, emergency recovery, agency/multisite |

## 3. Free vs PRO

| Capability | FREE | PRO |
|---|---|---|
| Browse/edit options, usermeta, transients | ✅ | ✅ |
| Autoload health + manual toggle | ✅ | ✅ |
| Autoload Usage Tracker | ✅ full | + auto-disable after N days, per-page breakdown |
| Orphan scanner | ✅ manual | Categorize by source plugin/theme; orphaned tables |
| Search & Replace | ✅ manual, dry-run | Regex + all tables + scheduled |
| Import/Export | ✅ | Scheduled export to Drive/S3/Dropbox |
| Backups / snapshots | ✅ last 15, local | Unlimited + scheduled + off-site + emergency recovery |
| Cleanup | ✅ manual | Scheduled (hourly→monthly); revisions/drafts/spam |
| Reports & alerts | — | Email health reports, autoload-threshold alerts |
| Multisite / network | — | ✅ |
| Audit log retention | short | extended + export |
| Support / sites | community | licensed, priority |

## 4. PRO feature specs

- **Auto-disable autoload** — after an option is unused for N front-end loads/days (Usage Tracker feeds it), flip autoload off automatically; per-page breakdown of which options load where.
- **Source categorization** — group options/orphans/tables by originating plugin/theme; detect orphaned custom tables.
- **Scheduled cleanup** — hourly→monthly cron for expired transients, revisions, auto-drafts, spam; scoped rules.
- **Scheduled + regex Search & Replace** — across all tables, dry-run first, cron-able.
- **Off-site backups + emergency recovery** — unlimited snapshots, scheduled, pushed to Drive/S3/Dropbox; one-click recovery point restore.
- **Reports & alerts** — email health reports; autoload-threshold and orphan-growth alerts.
- **Multisite/network** — network-level management and per-site rollups.
- **Extended audit log** — longer retention + export.

All registered via the core `nhrotm_modules` filter; PRO adds its own REST controllers and dashboard cards/recommendations through the same module contract.

## 5. Pricing (hybrid)

- Annual: **$39/yr** (1 site) · **$79/yr** (5) · **$149/yr** (unlimited).
- Lifetime: ≈ 3× annual per tier.
- Delivered via **Freemius** (licensing, updates, checkout, tax/VAT, affiliates, analytics) — in the PRO add-on plugin only. Free plugin stays clean: no Freemius SDK, no marketing chrome. Its only PRO surface is the static, self-rendered `Upgrade` screen + inert feature tags per [PRD.md](./PRD.md) §0.2 — all of which disappear once PRO is active.

## 6. Roadmap (PRO)

- **Phase 3** — PRO 1.0: separate add-on + Freemius + scheduling, source categorization, multisite, off-site backups.
- **Phase 4** — Launch: pricing page, docs, marketing, affiliate program.

Depends on free Phases 1–2 (core + dashboard) from [PRD.md](./PRD.md).

## 7. Open questions (PRO)

- Off-site backup storage providers for PRO v1 (Drive/S3/Dropbox — which first?).
- Lifetime-tier multiplier (3× vs 4× annual) and refund window.
- Freemius trial length / free-trial-without-card decision.

## 8. Sources

- Advanced Database Cleaner PRO — https://wordpress.org/plugins/advanced-database-cleaner/ , https://sigmaplugin.com/downloads/wordpress-advanced-database-cleaner/
- Perfmatters pricing — https://perfmatters.io/pricing/
- WP Reset PRO — https://wpexperts.in/wp-reset-pro/
- Freemius — https://freemius.com/
