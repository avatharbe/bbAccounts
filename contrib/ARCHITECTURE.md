# bbAccounts — Architecture

## Overview

bbAccounts is a **mini double-entry accounting system** for phpBB. It provides a general ledger with chart of accounts, immutable journal entries, subledgers, and financial reports — all integrated into the phpBB framework.

The forum/guild itself is the entity keeping books. User point balances are liabilities — what the forum owes its members. Every point grant is an expense, every fee is revenue, and the books always balance. phpBB user IDs serve as subledger entities, so no separate customer/supplier master data is needed.

The ledger is **source-agnostic**: it ships no gamification, no forum-activity hooks, and no integrations. Other extensions post entries through the ledger service.

## Motivation

The motivation comes from [avatharbe/bbDKP#1](https://github.com/avatharbe/bbDKP/issues/1), which identified the fundamental flaw in point systems that store running totals in fixed columns: no audit trail, painful rollbacks, and rigid schemas.

Existing phpBB point systems like [dmzx/ultimatepoints](https://github.com/dmzx/Ultimate-Points-Extension) treat each user as their own entity with personal balances updated in-place (`UPDATE user_points = user_points + X`). There is no organisational entity, no balancing books, and no real audit trail.

bbAccounts fixes this by implementing proper double-entry bookkeeping where the forum is the entity and the books always balance.

## Goals

- General-purpose double-entry ledger as a phpBB extension
- Chart of accounts with the 5 standard types (asset, liability, equity, revenue, expense)
- Immutable journal entries with balanced debit/credit lines
- Subledgers where phpBB users are customers/suppliers — no separate entity table
- Financial reports: trial balance, account ledger, subledger statements
- Source-agnostic: manual entries, automated hooks, and external phpBB extensions all use the same service
- Serve as the point-storage foundation for other phpBB extensions, without naming or knowing them

## Non-Goals

- Gamification mechanics — handled by other extensions if installed
- Forum-activity point earning — handled by other extensions if installed
- Cross-pool transfers / FX rates — single-pool-per-entry rule; cross-pool conversion deferred (see Phase 1 spec)
- Tax calculations or compliance features

---

## Accounting Model

### The Entity

The **forum/guild** is the accounting entity. From its perspective:

| Concept | Account Type | Normal Balance | Examples |
|---|---|---|---|
| Forum's own funds | Asset | Debit | Cash on Hand, Bank Account |
| What the forum owes users | Liability | Credit | User Wallets |
| Founding capital / opening balances | Equity | Credit | Opening Balances, Retained Earnings |
| Fees, ticket sales, etc. | Revenue | Credit | Transfer Fee Revenue, Service Income |
| Points granted, interest paid, etc. | Expense | Debit | Points Granted, Interest Expense |

The fundamental equation holds: **Assets + Expenses = Liabilities + Equity + Revenue**

### Double-Entry Mechanics

Every event is recorded as a journal entry with two or more lines. Each line is either a debit or a credit. The entry must balance: `SUM(debit) = SUM(credit)`. The ledger service rejects unbalanced entries.

Journal entries are **immutable**. Corrections are recorded as **reversing entries** — a new entry that mirrors the original with debits and credits swapped, linked back via `reversal_of`. There are no edits or deletes to existing entries.

### Subledgers

Accounts with a `subledger_type` are **control accounts**. Journal lines on these accounts carry a `subledger_user_id` linking to a phpBB user. This provides per-user detail within GL accounts without a separate entity table — phpBB's user system *is* the customer/supplier master data.

- `subledger_type = 'customer'`: tracks what the forum owes users (wallets) or what users owe the forum (receivables)
- `subledger_type = 'supplier'`: tracks what the forum owes external suppliers

A user's balance on a control account is `SUM(credit) − SUM(debit)` for liability accounts (credit-normal), or `SUM(debit) − SUM(credit)` for asset accounts (debit-normal).

---

## Integration Architecture

```
┌──────────────────────────────────────────────────────────────┐
│              External Source Extensions                       │
│   Source A  │  Source B  │  Source C  │  Webhooks / Imports  │
└─────┬───────┴─────┬──────┴─────┬──────┴──────────┬───────────┘
      │             │            │                  │
      │  ledger->create_entry()  │                  │
      │             │            │                  │
┌─────▼─────────────▼────────────▼──────────────────▼──────────┐
│                avathar.be/forum (phpBB)                       │
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │              avathar/bbaccounts Extension                 │ │
│  │                                                           │ │
│  │  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐  │ │
│  │  │ ACP         │  │ Ledger       │  │ Event          │  │ │
│  │  │ Controller  │  │ Service      │  │ Listener       │  │ │
│  │  │             │  │              │  │                │  │ │
│  │  │ - Accounts  │  │ - create()   │  │ - load lang    │  │ │
│  │  │ - Journal   │  │ - reverse()  │  │ - display bal  │  │ │
│  │  │ - Reports   │  │ - balance()  │  │ - permissions  │  │ │
│  │  │ - Admin     │  │ - query()    │  │                │  │ │
│  │  │   grants    │  │ - validate() │  │                │  │ │
│  │  └──────┬──────┘  └──────┬───────┘  └────────────────┘  │ │
│  │         │                │                                │ │
│  │         └────────────────┘                                │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                                │
│  Extension Tables                  phpBB Core Tables           │
│  ├── bbaccounts_accounts           ├── phpbb_users             │
│  ├── bbaccounts_journal            └── phpbb_config            │
│  └── bbaccounts_journal_lines                                  │
└────────────────────────────────────────────────────────────────┘
```

External source extensions never write to bbAccounts tables directly — they call `ledger->create_entry()`. Each entry's origin is namespaced via `reference_source` (format `'<vendor>.<entity>'`) with `reference_id` pointing back to the originating record. bbAccounts itself ships no source-specific code, no source-specific accounts, and no integration adapters.

---

## Data Model

### Chart of Accounts (`bbaccounts_accounts`)

```sql
CREATE TABLE phpbb_bbaccounts_accounts (
    account_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    account_code    VARCHAR(20)     NOT NULL DEFAULT '',
    account_name    VARCHAR(100)    NOT NULL DEFAULT '',
    account_type    VARCHAR(16)     NOT NULL DEFAULT '',
                                    -- 'asset', 'liability', 'equity', 'revenue', 'expense'
    parent_id       INT UNSIGNED    NOT NULL DEFAULT 0,
                                    -- 0 = top-level; otherwise FK to account_id
    subledger_type  VARCHAR(16)     NOT NULL DEFAULT '',
                                    -- '' = GL only, 'customer', 'supplier'
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (account_id),
    UNIQUE KEY (account_code)
);
```

### Journal Entries (`bbaccounts_journal`)

```sql
CREATE TABLE phpbb_bbaccounts_journal (
    journal_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    entry_date      INT UNSIGNED    NOT NULL DEFAULT 0,   -- unix timestamp
    description     VARCHAR(255)    NOT NULL DEFAULT '',
    reference_type  VARCHAR(32)     NOT NULL DEFAULT 'manual',
                                    -- 'manual', 'auto', 'import'
    reference_id    INT UNSIGNED    NOT NULL DEFAULT 0,
    created_by      INT UNSIGNED    NOT NULL DEFAULT 0,   -- phpBB user_id
    created_at      INT UNSIGNED    NOT NULL DEFAULT 0,   -- unix timestamp
    reversal_of     INT UNSIGNED    NOT NULL DEFAULT 0,
                                    -- journal_id this reverses (0 if original)
    PRIMARY KEY (journal_id)
);
```

Reversal status is expressed by `reversal_of != 0`; there is no separate `reference_type='reversal'` value and no `is_reversed` column.

### Journal Lines (`bbaccounts_journal_lines`)

```sql
CREATE TABLE phpbb_bbaccounts_journal_lines (
    line_id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    journal_id          INT UNSIGNED    NOT NULL DEFAULT 0,
    account_id          INT UNSIGNED    NOT NULL DEFAULT 0,
    debit               DECIMAL(20,2)   NOT NULL DEFAULT 0.00,
    credit              DECIMAL(20,2)   NOT NULL DEFAULT 0.00,
    subledger_user_id   INT UNSIGNED    NOT NULL DEFAULT 0,
                                        -- phpBB user_id (0 if GL-only)
    memo                VARCHAR(255)    NOT NULL DEFAULT '',
    PRIMARY KEY (line_id),
    KEY journal_id (journal_id),
    KEY account_id (account_id),
    KEY subledger_user_id (subledger_user_id)
);
```

> **Note:** phpBB DBAL uses UINT for timestamps and TINYINT for booleans. DECIMAL columns require the `vchar` workaround in migration schemas — see migration file for exact DBAL type mappings.

### Configuration (`phpbb_config`)

| Key | Purpose |
|---|---|
| `bbaccounts_enable` | 0/1, system on/off |
| `bbaccounts_currency_default` | Default currency code seeded into new accounts (e.g., `POINTS`, `GOLD`) |
| `bbaccounts_per_page` | Items per page in report views |

### Default Chart of Accounts (Seed)

Installed by the initial migration. Minimal and source-agnostic — admins and external source extensions add the accounts they need:

```
1000  Assets
  1010  Cash on Hand                          (asset)

2000  Liabilities
  2100  User Wallets                          (liability, subledger: customer)

3000  Equity
  3010  Opening Balances                      (equity)

4000  Revenue

5000  Expenses
```

All seven seeded rows use `currency_code = 'POINTS'` (the bbAccounts default; admins can change the default and create accounts in additional pools). Source-specific accounts — e.g. revenue categories or expense categories tied to a particular source extension — are added by that extension's migration, not here.

---

## Journal Entry Examples

The examples below illustrate double-entry mechanics. Account codes beyond the minimal seed (e.g. an admin-grant expense account, a transfer-fee revenue account, a savings wallet) are presumed to have been created by an admin or by another extension's migration before posting these entries.

**Admin grants 500 points to user #7**
```
Journal: "Admin grant to user #7"  reference_type=manual

  DR  5050 Points Granted — Admin     500.00
  CR  2100 User Wallets               500.00  (subledger_user_id = 7)
```

**User #42 transfers 100 to user #99 (5% fee)**
```
Journal: "Transfer user #42 → #99"  reference_type=auto

  DR  2100 User Wallets               100.00  (subledger_user_id = 42)
  CR  2100 User Wallets                95.00  (subledger_user_id = 99)
  CR  4010 Transfer Fee Revenue          5.00
```

**User #42 deposits 30 from cash to a savings wallet**
```
  DR  2100 User Wallets — Cash         30.00  (subledger_user_id = 42)
  CR  2200 User Wallets — Savings      30.00  (subledger_user_id = 42)
```

**Bank interest of 5 paid to user #42**
```
  DR  5040 Interest Expense             5.00
  CR  2200 User Wallets — Savings       5.00  (subledger_user_id = 42)
```

**Reversing an incorrect entry (journal #123)**
```
Journal: "Reverse journal #123"  reference_type=auto  reversal_of=123

  (Exact mirror of #123 with debits and credits swapped.)
```

---

## Ledger Service API

Central service: `avathar.bbaccounts.service.ledger`

```php
// Create a balanced journal entry
// Throws if SUM(debits) != SUM(credits)
create_entry(int $entry_date, string $description, array $lines,
             string $ref_type = 'manual', int $ref_id = 0): int

// Reverse an existing entry (creates a new mirrored entry)
reverse_entry(int $journal_id, string $description = ''): int

// Account balance (respects normal balance direction)
get_account_balance(int $account_id, int $as_of = 0): string

// Subledger balance for a specific phpBB user on a control account
get_subledger_balance(int $account_id, int $user_id, int $as_of = 0): string

// Trial balance: all accounts with debit/credit totals
get_trial_balance(int $as_of = 0): array

// Journal lines for an account (paginated)
get_account_ledger(int $account_id, int $from = 0, int $to = 0,
                   int $page = 1, int $per_page = 25): array

// All subledger postings for a phpBB user (paginated)
get_subledger_statement(int $user_id, int $from = 0, int $to = 0,
                        int $page = 1, int $per_page = 25): array
```

Each `$line` in `create_entry()` is an associative array:

```php
[
    'account_id'        => int,
    'debit'             => string,  // '0.00' if credit line
    'credit'            => string,  // '0.00' if debit line
    'subledger_user_id' => int,     // 0 if GL-only
    'memo'              => string,  // optional
]
```

### Reports

| Report | Description |
|---|---|
| Trial Balance | All GL accounts with debit/credit totals; must net to zero |
| Account Ledger | All journal lines for a single account, chronological |
| Subledger Statement | All postings for a specific phpBB user across control accounts |
| Balance Sheet | Assets = Liabilities + Equity (snapshot) — *future* |
| Income Statement | Revenue − Expenses for a period — *future* |

---

## Permissions

| Permission | Type | Description |
|---|---|---|
| `a_accounts` | Admin | Full access: manage accounts, create entries, view all reports |
| `m_accounts_view` | Moderator | View-only: trial balance, account ledger, subledger statements |

---

## Extension File Structure

```
ext/avathar/bbaccounts/
│
├── composer.json                        # Extension metadata
├── ext.php                              # Extension base class
│                                        # is_enableable() checks phpBB 3.3+ and PHP 8.1+
│
├── config/
│   ├── parameters.yml                   # Table name parameters
│   ├── routing.yml                      # Routes (if any front-end pages in future)
│   └── services.yml                     # All DI service definitions
│
├── service/
│   └── ledger.php                       # Core ledger service
│                                        # create_entry(), reverse_entry()
│                                        # get_account_balance(), get_subledger_balance()
│                                        # get_trial_balance()
│                                        # get_account_ledger(), get_subledger_statement()
│
├── controller/
│   └── acp_controller.php              # ACP page logic
│                                        # manage accounts, create journal entries
│                                        # view reports (trial balance, ledger, statements)
│
├── event/
│   └── listener.php                     # phpBB event hooks
│                                        # core.user_setup → load language
│                                        # core.permissions → register permissions
│
├── migrations/
│   └── v1_0_0_initial.php              # Creates all tables
│                                        # Seeds default chart of accounts
│                                        # Adds config keys
│                                        # Registers ACP module and permissions
│
├── acp/
│   ├── main_info.php                    # ACP module metadata
│   │                                    # modes: accounts, journal, reports
│   └── main_module.php                  # ACP module class → delegates to acp_controller
│
├── adm/style/
│   ├── acp_bbaccounts_accounts.html     # Chart of accounts management
│   ├── acp_bbaccounts_journal.html      # Journal entry creation and list
│   └── acp_bbaccounts_reports.html      # Trial balance, account ledger, statements
│
├── language/en/
│   ├── common.php                       # Shared strings
│   ├── info_acp_bbaccounts.php          # ACP module labels
│   └── permissions_bbaccounts.php       # Permission labels
│
├── contrib/
│   └── ARCHITECTURE.md                  # This document
│
└── license.txt                          # GPL-2.0
```

---

## Design Principles

1. **The forum is the entity** — user balances are liabilities, point grants are expenses, fees are revenue.
2. **Double-entry integrity** — every journal entry must balance. The service rejects unbalanced entries.
3. **Immutable journal** — entries are INSERT-only. Corrections via reversing entries, never UPDATEs/DELETEs.
4. **Subledgers via phpBB users** — no separate entity table. phpBB's user system *is* the customer/supplier master data.
5. **Source-agnostic** — the ledger doesn't know or care where entries come from.
6. **Derived balances** — account balances are `SUM()` queries on journal lines. No denormalised balance columns.
7. **Auditability** — every point ever granted or taken can be traced to a specific journal entry.

---

## Constraints & Gotchas

| Constraint | Detail |
|---|---|
| phpBB DBAL types | No native DECIMAL in migration schema — use `VCHAR:20` with application-level decimal handling, or use `DECIMAL` via raw SQL in migration |
| Timestamps | phpBB convention is `UINT:11` (unix timestamp), not DATETIME/DATE |
| No foreign keys | phpBB DBAL does not support FK constraints — enforce referential integrity in the service layer |
| Balance direction | Asset/Expense accounts have debit-normal balances; Liability/Equity/Revenue have credit-normal. The service must respect this when reporting. |
| Subledger validation | Lines on a subledger control account MUST have a non-zero `subledger_user_id`. The service enforces this. |

---

## Phase 1 Scope

- [ ] Database schema: accounts, journal, journal_lines tables (migration)
- [ ] Seed chart of accounts with the minimal source-agnostic defaults (migration)
- [ ] Core ledger service: create entry (with balance validation), reverse entry, balance queries
- [ ] ACP: Chart of accounts management (list, add, edit, deactivate)
- [ ] ACP: Journal entry creation (manual postings)
- [ ] ACP: Trial balance report
- [ ] ACP: Account ledger view
- [ ] ACP: Subledger statement per phpBB user
- [ ] Permission set (`a_accounts`, `m_accounts_view`)
- [ ] Event listener (load language)

## Future Modules

- **Forum Points** — auto-entries on post/topic (expense ↔ user wallet liability)
- **Transfer** — user-to-user with fee (reclassification + revenue)
- **Bank/Savings** — deposit/withdraw (reclassification between wallet control accounts) + interest cron
- **Transaction Logs UI** — user-facing view of their own subledger statement
- **Income Statement / Balance Sheet** — period-based financial reports
- **Per-forum cost settings** — pay to post/create topics
- **External integrations** — Patreon, DKP, webhooks

---

## Technical Notes

- phpBB extension: `avathar/bbaccounts`
- Namespace: `avathar\bbaccounts`
- Follows avathar extension conventions (see bbpatreon for reference)
- PHP 8.1+, phpBB 3.3+
- GPL-2.0-only
- Architectural inspiration: [avatharbe/bbDKP#1](https://github.com/avatharbe/bbDKP/issues/1)
