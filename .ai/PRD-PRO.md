# PRD — Database Cleaner PRO

Status: **PRO 1.0 = 3 features (trimmed from 10 on 2026-09-28), implemented, pre-launch** · Owner: Nazmul Hasan Robin (nhrrob)
Code: private repo `nhrrob/nhrrob-options-table-manager-pro` (local: `wp-content/plugins/nhrrob-options-table-manager-pro`). Implementation notes live in that repo's `CLAUDE.md`.
Last revised: 2026-09-28 (trimmed to Autopilot + Site Guard + Sandbox after an honest value review; the other seven are parked in §4a)

> Dev-only document. Excluded from distribution (`.distignore` + `.gitattributes export-ignore`).
> Free/core scope: **[PRD.md](./PRD.md)** · UI spec: **[DESIGN.md](./DESIGN.md)**.

## 0. The free-vs-PRO rule (non-negotiable)

1. **If a feature is free in any other WordPress plugin, it is free here.** It goes in the free PRD, not in PRO. Charging for what users can get free elsewhere just sends them to a competitor.
2. **If a feature is listed in the free PRD, it is free,** even if an older PRO draft claimed it.
3. **PRO carries major features only.** Each one must solve a real job someone would pay for. No padding with minor add-ons ("extended retention", "keep last N", …).
4. **Re-check before building.** Competitor tiers drift. Re-verify each feature's market status (§3) before its PRO release, and move it to free if it has gone free elsewhere.

## 1. Locked decisions

| Decision | Choice |
|---|---|
| Delivery | **Separate PRO add-on plugin** extending core via the `nhrotm_modules` filter (PRD §3). Adds its own modules, REST controllers, dashboard cards and recommendations. |
| Licensing/billing | **Freemius**, in the PRO add-on only, never in free |
| Monetization | Hybrid: annual subscription + lifetime tier (§5) |
| Free-side discoverability | None — the free codebase never mentions PRO (PRD §1.2, decided 2026-10-04) |

**Handoff (built 2026-09-27).** PRO boots only when the free core's `NHROTM_VERSION` is at least PRO's `MIN_CORE_VERSION` (2.1.0; PRD §3 lists the whole contract) and declares `Requires Plugins: nhrrob-options-table-manager`. PRO adds three nav sections before Settings (Performance, Site Guard, Sandbox) through `nhrotm_modules`, registers their React screens through the `nhrotm.screens` JS filter, and reuses the free components via `window.nhrotm`, so it ships no duplicate chrome. Since 2026-10-04 the free build has no PRO surface at all (PRD §1.2): no `Upgrade` item, no `Pro` tags and no `nhrotm_has_pro`/`boot.hasPro` — PRO's `add_filter( 'nhrotm_has_pro', … )` in `Core\Integration` is now a harmless no-op and can be dropped.

## 2. Who pays, and why

The free plugin is deliberately generous. PRO sells **one story: keep the options table fast without babysitting it, and know the moment something changes it.** It never holds back basics.

| Buyer | Job | PRO features |
|---|---|---|
| Performance consultants, speed-focused owners | "Make and keep the database fast without babysitting it, and prove it." | Autoload Autopilot (with the Profiler as its proof) |
| Business sites, cautious owners | "Warn me and let me undo it when a critical setting changes behind my back, and let me try a risky change safely." | Site Guard, Option Sandbox |

**Why not agencies/enterprise in 1.0 (review 2026-09-28):** agencies already run MainWP/ManageWP/WP Umbrella and won't adopt a second fleet hub for options alone; the signed site-to-site admin channel is a large attack surface for a $39 plugin; Team Controls could never be a real security boundary inside wp-admin. Enterprise is blocked on multisite, which is free-side work (PRD §5).

Malware scanning is **not** an OTM feature. It belongs to **nhrrob-secure**, which already does vulnerability and file scanning. An option-value scan (injected scripts, spam links, encoded payloads in `wp_options`) should be proposed there.

## 3. PRO 1.0 features (market-checked 2026-09-27)

Three features, **all shipping in 1.0**. Each lists its **market check**: what competitors do, and at which tier. Items marked *verify* need a closer look before build (§0 rule 4).

### A. Performance

#### 3.1 Autoload Autopilot
Builds on the free Usage Tracker (stays fully free).
- **Auto-disable** autoload for options with no front-end use for N days, with exclusions (core, prefixes, per-plugin allowlist).
- **Auto-enable** the reverse case: non-autoloaded options read on nearly every request (each costs an extra query).
- **Self-healing:** re-enable automatically if a disabled option starts being read again.
- **Object-cache guard:** warn before `alloptions` crosses the Memcached 1 MB item limit (a known cause of sites silently losing their options cache).
- Every automatic change goes through History; undo individually or as a batch.

- **Proof (the Performance Profiler, folded in 2026-09-28 — it is Autopilot's evidence, not a product of its own):** samples real front-end traffic and aggregates per URL/template (options read, uncached option queries, `notoptions` misses, autoload memory per page), tracks transient churn, and shows before/after markers around every Autopilot run or manual change.

*Market:* AAA Option Optimizer and Autoload Cleaner & Analyser (both free) are **manual** only. No plugin, free or paid, automates this. Query Monitor (free) profiles one request at a time; nothing aggregates option cost across real traffic.

### B. Safety

#### 3.2 Site Guard
Free already has snapshots, History and snapshot compare. PRO sells **watching, alerting and reacting on its own**:
- **Watch critical options** (`siteurl`, `home`, `admin_email`, `users_can_register`, `default_role`, `active_plugins`, `template`/`stylesheet`) plus a user watch list.
- **Instant alerts** (email, Slack, Discord, webhook): who changed what, old → new, AI-triaged ("routine save" vs "looks like a compromise").
- **One-click rollback** from the alert (signed link → confirm). **Lock mode** blocks changes outside an allowlisted context.
- **Update watch:** a lightweight *change point* before every plugin/theme/core update; "this update changed 14 options"; roll back that one update's settings; AI explains the change.
- **Emergency recovery:** a secret-key URL that rolls back the last change point when wp-admin is unreachable.

*Market:* Simple History and WP Activity Log **log** free, but **alerts are premium-only** in both. Auto-snapshot before updates and emergency recovery are **PRO** in WP Reset and **Premium** in UpdraftPlus. Nobody offers rollback from an alert, lock mode, or per-update settings rollback.

**Storage:** change points store **only rows that differ** from the previous point (typically a few KB), compressed, transients excluded, and **skipped when nothing changed**. Built on the free snapshot storage fix (PRD §4 item 2).

#### 3.3 Option Sandbox
Answers the question users ask most: *"Is it safe to delete / disable this?"*
- **Session-only trial:** delete, change or un-autoload any set of options **for your browser session only** (cookie-scoped `pre_option_*` overrides). Visitors keep seeing the real site.
- Browse your site as normal; an admin-bar badge shows the sandbox is active and lists what's overridden. Built-in check of key URLs (HTTP status + PHP errors) with and without the overrides.
- **Apply for everyone** (behind an auto-snapshot) or **discard**.

*Market:* Nothing found. Staging plugins (WP Staging, InstaWP) clone the **whole site**. The Customizer previews theme settings only. No plugin lets you trial arbitrary option changes live and session-scoped.

### AI inside PRO features
- **Site Guard:** alert triage; "explain this update's changes / why did my site break?". The "allow redacted value excerpts" privacy toggle lives in Site Guard → Settings.

### AI delivery (both free and PRO)
- **Engine: WordPress core AI Client** (`wp_ai_client_prompt()`, WP 7.0+). The user connects a provider once in **Settings → Connectors** (Anthropic/OpenAI/Google provider plugins). No SDK in our zip (PRD §1.1), no API keys stored by us, no AI server for us to run.
- **Graceful fallback:** below WP 7.0, or with no connector set up, AI buttons show a single "Connect an AI provider" hint, and everything else works.
- **Privacy default:** metadata only. Option values are sent only per-action with explicit consent, with secrets (keys, tokens, passwords, emails) redacted first.
- PRO sells the **features**, not tokens: the user pays their provider directly.

## 4. Moved out of PRO

Moved to free per §0 (tracked in [PRD.md](./PRD.md)): **Ownership Detection** (Autoload Cleaner & Analyser, free), **Database Growth Analytics** (Meow Database Cleaner, free), **cron execution monitoring/logs** (CronVitals, Cron Pulse, Harvify: free), **AI agent read/write of options over MCP** (AI Engine free MCP includes option updates), **serialized data repair** (FG Fix Serialized Strings, free), **Action Scheduler cleanup** (several free plugins), scheduled cleanup, general DB cleanup, multisite, regex/all-table S&R, off-site backups, unlimited snapshots, snapshot compare/download, cron manager, plugin/theme activation log, weekly email health digest.

## 4a. Parked (cut from PRO 1.0 on 2026-09-28)

Code is kept, not deleted, at `~/Sites/nhrrob-dev/_parked/otm-pro-2026-09-28/` (same relative paths; the PRO repo was uncommitted). Bring one back only if paying users ask for it, and re-run the §0 market check first.

| Feature | Why parked |
|---|---|
| AI Database Audit | Its plan is what Autopilot + the free Orphan Scanner already produce; needs WP 7.0 + the user's own AI provider; overlaps the planned free AI layer. |
| Plugin Settings Vault | Niche; overlaps free Orphan Scanner, Backups and Export; ownership from a static code scan is fragile for deciding which tables to drop. |
| Fleet Dashboard | Agencies already use MainWP/ManageWP/WP Umbrella; a second hub for options alone won't be adopted. |
| Settings Sync & Blueprints | Depends on Fleet's site-to-site channel (a signed admin-level remote API — large attack surface). Free selective export/import covers the manual case. |
| Agency Reports & White-label | Only valuable with Fleet. |
| Team Controls & Approvals | Can't be a real security boundary (admins can use any other wp-admin screen); four-eyes review for `wp_options` has no demonstrated demand. |

Tables `nhrotmp_vault`, `nhrotmp_sites`, `nhrotmp_blueprints`, `nhrotmp_approvals` are no longer created; an install from the 10-feature build keeps them until PRO is uninstalled (its `uninstall.php` drops them).

## 5. Pricing (hybrid)

- Annual: **$39/yr** (1 site) · **$79/yr** (5) · **$149/yr** (unlimited).
- Lifetime: ≈ 3× annual per tier.
- Confirmed 2026-09-27 for the 10-feature launch. **Open (2026-09-28):** re-confirm for the 3-feature PRO; the unlimited-sites tier made most sense with Fleet.
- Sits at ADC Premium's entry point and below WP-Optimize Premium ($49–199) and Better Search Replace Pro ($59–99).

## 6. Roadmap (PRO)

**Decided 2026-09-28: PRO 1.0 = Autopilot (+ Profiler as its proof), Site Guard, Option Sandbox.** All three are built and verified.

1. **Free 2.1 first** (PRO needs its add-on API): History `record_type`, snapshot storage, Classic retirement with full parity, Activity restore. Done 2026-09-28, unreleased.
2. **Launch trigger (decided 2026-09-28):** after the free 2.1.0 database-cleaner release, once the free plugin reaches **~1,000 active installs** (it had 100 on 2026-09-28; at typical 1–2% conversion, fewer installs can't cover Freemius, support and upkeep). The database-cleaner positioning (PRD §2) is what grows that base.
3. **Before launch:** Freemius SDK + product keys, pricing page, re-confirm pricing (§5), and re-run the §0 market check (a competitor may have made one of the three features free).
4. **After launch:** only bring a parked feature back (§4a) on real customer demand.

## 7. Open questions

- Lifetime-tier multiplier (3× vs 4× annual) and refund window.
- Freemius trial: length, and with or without a card.
- ~~Site Guard channels for 1.0~~ **Decided:** email + Slack + Discord + generic JSON webhook all ship in 1.0 (Slack/Discord are just webhook payload shapes).
- ~~Fleet/Sync transport~~ (parked with Fleet, §4a) **Decided:** a per-connection key pair. The managed site issues a connection key (REST root + key id + 256-bit secret, secret encrypted at rest with the site's auth salt); the hub HMAC-signs every call (key, timestamp ±5 min, single-use nonce, method, route, body hash) and the call runs as the admin who issued the key. No application passwords.
- ~~AI prompt budget per audit~~ (parked with AI Audit, §4a).

### Implementation notes / deviations (2026-09-27)

- **No database tables (decided 2026-10-04).** PRO creates none. Read stats, per-URL cost, transient churn, the Autopilot log, Site Guard alerts and update change points are kept in capped, non-autoloaded options through `Core\Store` (caps and shapes: PRO `CLAUDE.md` → Storage). Consequences accepted with that decision: the figures are sampled estimates and a concurrent request can lose one increment; past days keep only the 15 busiest URLs and 25 busiest transients; 200 alerts and 20 change points are kept (was unlimited within 180 days, and 50); an alert whose value exceeds 256 KB cannot be rolled back; free snapshots include this data. Development installs keep their old `nhrotmp_*` tables until PRO is uninstalled.

- **Freemius not wired yet.** `Core\License::is_active()` is the single gate; it honours `nhrotm_fs()` once the Freemius SDK + product keys exist, and returns true in development builds until then. Blocking for launch.
- **Option Sandbox cannot trial protected core options** (they load before any plugin can filter them) or autoload flags (no behavioural effect). Deletes and value changes only.
- (Parked with Vault, §4a) **Vault ownership** comes from a static scan of the plugin's own PHP plus its own prefixes; names found in its code that carry another plugin's prefix are ignored, so removing an add-on never touches its parent plugin's data. Truncated captures (tables > 50k rows, meta > 20k rows) never drop tables/meta on cleanup.
- **Prerequisites not done in free:** the free AI layer is still a free-roadmap item (the snapshot storage fix landed in free 2.1). PRO does not depend on them (Site Guard stores its own deltas; PRO calls the core AI Client directly). Ownership Detection is covered by the free `ScannerManager::guess_owner()` for labels plus PRO's own Footprint scanner for the Vault.
- Free labels the add-on's own `nhrotmp_` options as "Database Cleaner Add-on" (ScannerManager; the word "Pro" does not appear in the free code), so the orphan scanner and audits never propose deleting them.

## 8. Sources

- AAA Option Optimizer: https://wordpress.org/plugins/aaa-option-optimizer/
- Autoload Optimizer: https://wordpress.org/plugins/autoload-optimizer/
- Autoload Cleaner & Analyser (free owner signatures): https://wordpress.org/plugins/siby-autoload-cleaner-analyser/
- Meow Database Cleaner (free size monitoring; AI Engine MCP): https://meowapps.com/database-cleaner/
- WordPress 7.0 AI Client: https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/ , Connectors API: https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/
- Advanced Database Cleaner (free vs premium): https://wordpress.org/plugins/advanced-database-cleaner/
- Simple History: https://wordpress.org/plugins/simple-history/
- WP Activity Log: https://wordpress.org/plugins/wp-security-audit-log/
- WP Reset free vs PRO: https://wpreset.com/free-vs-pro-features-comparison/ , https://docs.wpreset.com/article/124-automatic-snapshots
- UpdraftPlus auto-backup before updates (premium): https://teamupdraft.com/updraftplus/features/wordpress-automatic-backup-before-updates/
- WP Crontrol: https://wordpress.org/plugins/wp-crontrol/
- Advanced Cron Manager (logs are PRO): https://wordpress.org/plugins/advanced-cron-manager/ ; free cron monitors: https://wordpress.org/plugins/cronvitals/ , https://wordpress.org/plugins/cronpulse/
- AI Engine (free MCP incl. option updates; SQL/DB tools Pro): https://wordpress.org/plugins/ai-engine/
- FG Fix Serialized Strings (free): https://wordpress.org/plugins/fg-fix-serialized-strings/
- Action Scheduler cleaners (free): https://wordpress.org/plugins/ws-action-scheduler-cleaner/
- MainWP Maintenance (paid, Pro bundle): https://mainwp.com/add-on/maintenance/
- White-label reports (paid): https://wpmudev.com/reports/ , https://mantlewp.com/
- InstaWP staging sync: https://instawp.com/wordpress-staging-to-production/
- Competitor feature matrix (external sheet): https://docs.google.com/spreadsheets/d/1fv2LJJrSu4rA2Z7-wLd5sGecW4OxoKfMtVsTgQmZXoU/
- Freemius: https://freemius.com/
