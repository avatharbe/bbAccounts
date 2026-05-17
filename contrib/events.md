# bbAccounts Extension — Events & Integration Points

## 1. Own Events & API (emitted by this extension)

This section is the public API contract. These are the events and services that bbAccounts deliberately exposes so that *other* extensions can integrate with it. If you are building an extension that awards or spends points (e.g. a DKP system, a Patreon bridge, a gamification layer), this is where to look. Changing anything listed here is a breaking change and requires a major version bump.

### 1.1 PHP Events

bbAccounts does not currently dispatch any custom PHP events. All integration happens through the public service API below — consuming extensions call into the ledger directly rather than reacting to ledger activity.

If a future use case justifies it, candidate hook points would be `avathar.bbaccounts.before_create_entry` (mutate a journal entry before persistence) and `avathar.bbaccounts.after_create_entry` (react to a successful post). Neither exists today.

### 1.2 Public Service API

#### `avathar.bbaccounts.service.ledger`

The source-agnostic entry point for all point mutations. External extensions post journal entries through this service rather than writing to bbAccounts tables directly. This is the **only** supported integration surface — the schema and table names are not part of the public contract.

- **Class:** `avathar\bbaccounts\service\ledger`
- **DI reference:** `@?avathar.bbaccounts.service.ledger` (use the nullable `?` form so your extension degrades gracefully when bbAccounts is absent)
- **Integration contract:** every external entry **must** namespace itself via `reference_source` using the `<vendor>.<entity>` convention (e.g. `'avathar.bbdkp'`, `'avathar.bbpatreon'`). bbAccounts ships zero source-specific code; the `reference_source` string is the *only* thing that lets the reports distinguish where an entry came from.

| Method | Purpose |
|---|---|
| `create_account(string $account_code, string $account_name, string $account_type, string $currency_code = 'POINTS', int $parent_id = 0, string $subledger_type = '', bool $is_active = true): int` | Programmatic chart-of-accounts seed for consumer extensions. Call from your install migration to register the accounts your extension posts against — never INSERT into `bbaccounts_accounts` directly (the table layout is not part of the public contract). Validates `account_type` ∈ `VALID_ACCOUNT_TYPES`, `subledger_type` ∈ `VALID_SUBLEDGER_TYPES`, currency exists and is active, code is unique, parent (if non-zero) exists. Returns the new `account_id`. Throws `\InvalidArgumentException` on any failure. |
| `list_accounts(string $account_type = '', ?string $subledger_type = null): array` | Read-side enumeration for consumer extensions populating UI pickers (e.g. an "account mapping" ACP page). Returns ordered-by-`account_code` rows with all account columns, **including inactive accounts**. Filters: `account_type = ''` means no filter (validates against `VALID_ACCOUNT_TYPES` if non-empty); `subledger_type = null` means no filter, `''` matches accounts with no subledger, `'customer'`/`'supplier'` match those subledgers. Throws `\InvalidArgumentException` on invalid filter values. |
| `create_entry(int $entry_date, string $description, array $lines, string $reference_type = 'manual', int $reference_id = 0, string $reference_source = '', int $created_by = 0): int` | Post a balanced journal entry. Validates `reference_type` against the allowed enum and `lines` against the double-entry rule (sum of debits = sum of credits, single currency per entry). Returns the new `journal_id`. Throws `\InvalidArgumentException` / `\LogicException` on validation failure. |
| `reverse_entry(int $journal_id, string $description = '', int $created_by = 0): int` | Post a mirror of an existing entry. Blocks reversal-of-reversal. Returns the new (reversing) `journal_id`. |
| `get_account_balance(int $account_id, int $as_of = 0): string` | Single account balance, optionally as-of a unix timestamp. Returns a bcmath-precision decimal string. |
| `get_subledger_balance(int $account_id, int $user_id, int $as_of = 0): string` | Single (account, user) balance. Same return semantics. |
| `get_subledger_account_balances(int $user_id, int $from = 0, int $to = 0): array` | Per-account closing balances for one user, grouped by currency. |
| `get_trial_balance(int $as_of = 0, string $currency_code = ''): array` | All accounts with running balances; sanity check that debits = credits across the books. |
| `get_account_ledger(int $account_id, int $from = 0, int $to = 0, int $page = 1, int $per_page = 25): array` | Paginated detail of all journal lines hitting an account. |
| `get_subledger_statement(int $user_id, int $from = 0, int $to = 0, int $page = 1, int $per_page = 25): array` | Paginated detail of all journal lines hitting a user across every subledger account. |
| `get_journal_list(int $offset = 0, int $limit = 25): array` | Paginated journal headers with `is_reversed` flag derived in SQL. |

- **Known consumers:** none yet — Phase 3 (issues [#3](https://github.com/avatharbe/bbAccounts/issues/3) ultimatepoints, [#4](https://github.com/avatharbe/bbAccounts/issues/4) bbDKP) will be the first.

**Example `services.yml`:**
```yaml
my_extension.point_poster:
    class: my_vendor\my_ext\point_poster
    arguments:
        - '@?avathar.bbaccounts.service.ledger'
        - '@user'
```

**Example usage — awarding 10 POINTS to user 42 for posting a new topic:**

The forum is *issuing* points (no value arrives from outside), so the contra-account is `5000 Expenses` : recording the reward as a promotional cost. 
When real value enters the forum (e.g. a Patreon contribution.. ) you would `1010 Cash on Hand`. 

```php
if ($this->ledger !== null)
{
    $this->ledger->create_entry(
        time(),
        'New topic posted',
        [
            ['account_id' => $expense_account_id, 'debit' => '10', 'credit' => '0'],
            ['account_id' => $wallets_account_id, 'debit' => '0',  'credit' => '10', 'subledger_user_id' => 42],
        ],
        'auto',
        $topic_id,
        'avathar.ultimatepoints',
        (int) $this->user->data['user_id']
    );
}
```

#### `avathar.bbaccounts.service.balance_summary`

A cached read-side helper for "show me one row per currency" UI (profile badges, portal widgets). External extensions are welcome to consume it but are not required to — anything balance_summary returns can be derived from the ledger.

- **Class:** `avathar\bbaccounts\service\balance_summary`
- **DI reference:** `@?avathar.bbaccounts.service.balance_summary`
- **Methods:**
  - `get_pool_balances(int $user_id): array` — Returns `[{currency_code, balance, is_abnormal}, ...]` sorted by currency code. 60 s cache.
  - `invalidate(int $user_id): void` — Drop the cache entry. **Call this after any `create_entry()`/`reverse_entry()` that touches the user**, or stale balances will linger for up to 60 s.

### 1.3 Routes

| Route name | Path | Purpose |
|---|---|---|
| `avathar_bbaccounts_reports` | `/bbaccounts/reports/{report}` | Front-end read-only mirror of the ACP Reports module. `{report}` ∈ `trial_balance` (default), `account_ledger`, `subledger`, `balance_lookup`, `user_balance_lookup`. Access is split between two perms — see Permissions below. |

### 1.4 Permissions

| Permission key | Category | Purpose |
|---|---|---|
| `a_accounts` | misc | Admin: full ACP access (currencies, accounts, journal CRUD, reports). Granted to `ROLE_ADMIN_FULL` by default. |
| `u_accounts_view_aggregates` | misc | User: view aggregate Reports — trial balance and account-balance lookup. Gates the navbar "bbAccounts Reports" link (either view perm shows it). Granted to `ROLE_MOD_FULL` by default. |
| `u_accounts_view_users` | misc | User: view per-user Reports — account ledger, user statement (subledger), user-balance lookup — plus the balance badge on **other** users' profile pages. Granted to `ROLE_MOD_FULL` by default. |

The two view perms split along the aggregate-vs-per-user axis so a treasurer / officer role can read financial summaries without also being able to look up individual user activity. A viewer with EITHER perm reaches the front-end Reports page; each report tab is then enforced per-perm individually (direct-URL access to a gated report returns 403). Display labels in the ACP permission MASK UI are prefixed `"bbAccounts: "` for scannability.

Note: own-balance views (UCP "My Wallet", UCP "My Statement", own-profile balance badge) are **not** gated by either view perm. Any logged-in user can see their own balance — the UCP module gate `ext_avathar/bbaccounts` and a self-vs-other check in the event listener handle that.

---

## 2. Events Subscribed from Other Extensions

Extensions can listen to each other's events or consume each other's services. This section documents every place where bbAccounts reaches *out* to another extension — for example, calling a service provided by a neighbouring extension when it is installed. These integrations are always optional (soft-coupled): bbAccounts works normally when the other extension is absent.

None. bbAccounts is deliberately source-agnostic — the integration model is *inverted* compared to most extensions: consumers reach into bbAccounts via the ledger service rather than bbAccounts reaching out to them. bbAccounts ships no code, no accounts, and no assumptions tied to any specific consumer extension.

---

## 3. phpBB Core Hooks with Non-Obvious Behavior

bbAccounts subscribes to a handful of standard phpBB core events from `event/listener.php` (language load, navbar template var, profile badge injection). The routine ones are not documented here — read the listener directly. The two below are flagged because their behavior is **not** what a casual reader would assume.

| phpBB Core Event | Handler | Why it's non-obvious |
|---|---|---|
| `core.permissions` | `on_permissions()` | Without this hook, `a_accounts`, `u_accounts_view_aggregates`, and `u_accounts_view_users` exist in `phpbb_acl_options` (the migration adds them) but the ACP permission MASK shows **no row** to grant them — the perms are silently ungrantable. Hook registers them in the `misc` category so they appear in the role/group/user permission tabs. |
| `core.delete_user_after` | `on_user_delete()` | bbAccounts does **not** delete the user's journal data. It remaps `bbaccounts_journal_lines.subledger_user_id` from the deleted user to `ANONYMOUS_USER_ID` (1). The journal is immutable by design, so historical balances stay intact and reports continue to reconcile — the deleted user's slice just becomes attributable to "anonymous" in subledger statements. |
