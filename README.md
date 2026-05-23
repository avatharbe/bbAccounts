bbAccounts for phpBB 3.3
========================

[![Tests](https://github.com/avatharbe/bbAccounts/actions/workflows/tests.yml/badge.svg)](https://github.com/avatharbe/bbAccounts/actions/workflows/tests.yml)

Double-entry accounting for phpBB. Provides a ledger service plus an admin UI; designed as a point-storage foundation that other phpBB extensions can post journal entries against.

#### Requirements
- phpBB 3.3.0 or higher
- PHP 8.1 or higher
- PHP `bcmath` extension

#### Core features
- Double-entry ledger with immutable journal entries (INSERT-only; corrections are reversal entries)
- Multi-pool isolation via per-account `currency_code`; currencies are first-class managed entities (no cross-pool transfers)
- Subledgers per phpBB user (`'customer'`/`'supplier'` types) or per opaque external entity (`'character'` type — e.g. bbGuild character IDs); no separate master-data tables
- Source-agnostic: any extension can post journal entries through the ledger service
- Derived balances (no denormalised totals stored — `SUM()` over journal lines)
- bcmath-precision arithmetic; balances stored as `DECIMAL(20,2)`

#### ACP Modes
- **Currencies**: managed list of pools (code, name, precision, is_active); add / edit / disable. Code is locked once any account references it.
- **Chart of Accounts**: list, add, edit, disable accounts (5 standard types plus customer / supplier / character subledgers); per-row balance column with abnormal-direction highlighting; currency picker sourced from the active currencies list.
- **Journal**: list entries with reversal badges and a per-entry subledger-user(s) column (coloured profile links); create new entries (multi-line, server-side balance validation); two-step reverse-entry confirm; **bulk import via CSV**.
- **Reports**: 5 sub-modes — *trial balance* (grouped by currency pool, BALANCED / UNBALANCED indicator), *account ledger* (paginated drill-down per account), *subledger statement* (paginated per-user transaction list), *single-account balance lookup*, *user-balance lookup* (resolves username via `user_loader`).

#### User-facing surfaces (Phase 3)
- **UCP "My Wallet"** — own per-pool balance summary as a top-level UCP tab.
- **UCP "My Statement"** — paginated own-subledger transaction list with optional date range filter, per-account opening/closing summary, per-page choices 10/25/50/100. Flatpickr-themed date inputs (light + dark, Wowhead-aesthetic overlay on pbwow3).
- **Post-profile balance badge** — small per-pool summary injected into the user-statistics block on profile pages. Own profile visible to any logged-in user; other profiles gated by `u_accounts_view_users`. Anonymous viewers never see the badge. Cached 60 s per user.
- **bbGuild portal widget** — tagged service `bbguild.portal.module` registers an own-balance widget that drops into any of the bbGuild portal columns; reuses the same per-user cache as the profile badge.
- **Front-end Reports page** — read-only mirror of the ACP Reports module at `/app.php/bbaccounts/reports/{report}`; same five sub-modes; access split between two perms — `u_accounts_view_aggregates` (trial balance, balance lookup) and `u_accounts_view_users` (account ledger, user statement, user balance lookup). Navbar quick-link is shown when the viewer has either perm; each report tab hides individually when its perm is missing, and direct-URL access to a gated report returns 403. Both perms default-grant to `ROLE_MOD_FULL`.

#### Bulk import via CSV
The Journal mode includes an "Import CSV" upload that parses the file, runs every existing service-layer validation, shows a per-entry preview, and on confirmation commits the whole file in one DB transaction (any single-entry failure rolls the whole import back). See [`contrib/sample-import.csv`](contrib/sample-import.csv) for a working file that imports cleanly against the default seed.

The format is one row per journal **line**, grouped into entries by `entry_ref`. Required columns: `entry_ref`, `entry_date`, `description`, `account_code`, `debit`, `credit`. Optional: `subledger_user_id`, `subledger_player_id`, `memo`, `reference_type`, `reference_source`, `reference_id`. UTF-8, RFC 4180 quoting. First row of each `entry_ref` group sets the entry-level fields (`entry_date`, `description`, `reference_*`); each subsequent row contributes a line.

Entries with different `entry_ref` may use different currency pools — pool consistency is enforced **per entry**, not per file. Example multi-currency snippet (requires the `EUR` pool and a matching cash account to be created first):

```csv
entry_ref,entry_date,description,account_code,debit,credit
EUR-OP-1,2026-05-04,EUR opening balance,EUR-1010,500.00,0
EUR-OP-1,2026-05-04,EUR opening balance,EUR-3010,0,500.00
PTS-OP-1,2026-05-04,POINTS opening balance,1010,1000.00,0
PTS-OP-1,2026-05-04,POINTS opening balance,3010,0,1000.00
```

#### For external source extensions
Any phpBB extension can post entries through the ledger service `avathar.bbaccounts.service.ledger`. Each entry is namespaced via `reference_source` (format `<vendor>.<entity>`) so cross-extension reverse-lookups stay unambiguous. bbAccounts itself ships no source-specific code or accounts. Use the nullable DI form (`@?avathar.bbaccounts.service.ledger`) so your extension degrades gracefully when bbAccounts is absent. See [`contrib/events.md`](contrib/events.md) for the full integration contract (method signatures, `reference_source` convention, working example).

#### Languages
English (en, en_us), Dutch (nl), German (de), French (fr), Spanish (es). Translations cover ACP / UCP / permissions / portal / common strings.

#### Installation
1. [Download the latest release](https://github.com/avatharbe/bbAccounts/releases) and unzip it.
2. Copy the contents to `/ext/avathar/bbaccounts/` (so that `ext.php` is at `/ext/avathar/bbaccounts/ext.php`).
3. Navigate in the ACP to `Customise → Manage extensions`.
4. Find `bbAccounts` under "Disabled Extensions" and click `Enable`.

#### Uninstallation
1. Navigate in the ACP to `Customise → Manage extensions`.
2. Click the `Disable` link for `bbAccounts`.
3. To permanently uninstall, click `Delete Data`, then delete the `bbaccounts` folder from `/ext/avathar/`.

#### Documentation
- [Getting Started tutorial](contrib/tutorials/getting-started.md) — for phpBB admins without an accounting background. Covers the seeded chart of accounts, day-to-day journal entries, reversal of mistakes, and copy-paste recipes for common scenarios.
- [Architecture overview](contrib/ARCHITECTURE.md) — design rationale (double-entry theory, account types, subledger semantics, immutability invariants).
- [Events & integration points](contrib/events.md) — public API contract for extensions integrating with bbAccounts. Documents the ledger service surface, `reference_source` namespacing convention, routes, permissions, and the two non-obvious phpBB core hooks (permission MASK registration, user-delete subledger remap).
- [Changelog](CHANGELOG.md) — per-release notes following Keep a Changelog.

#### Support
- [Issue tracker](https://github.com/avatharbe/bbAccounts/issues)

#### License
[![License](https://img.shields.io/github/license/avatharbe/bbAccounts)](https://github.com/avatharbe/bbAccounts/blob/main/license.txt)
[GNU General Public License v2](http://opensource.org/licenses/GPL-2.0)

Maintained by Andy Vandenberghe (Sajaki).
