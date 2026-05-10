# Getting Started with bbAccounts

A tutorial for phpBB admins who want to track user point balances but don't have an accounting background.

## What this is

bbAccounts keeps a permanent, tamper-evident record of every point change on your forum. When you give a user 100 points, when they spend 10 on a feature, when you fix a typo with a reversal — every one of those events is recorded.

Two ideas are worth understanding before anything else:

1. **The forum owes its users their points.** A user's balance isn't "money the forum has" — it's "money the forum owes." That's why user wallets are recorded as a *liability* in the books.
2. **Every change has two sides.** When 100 points appear in forum user `alice`'s wallet, they have to come from somewhere — they don't materialise. So the system records both the destination (alice's wallet goes up by 100) *and* the source (e.g. the forum's "Expenses" pool goes up by 100, representing the cost of awarding them).

That's the whole conceptual base. The rest is mechanics.

## The 3 concepts you actually need

### 1. Liability vs expense

- **Liability** = the forum owes someone something. User wallet balances live here. Account `2100 User Wallets`.
- **Expense** = the forum spent something. When you award points to a user, the forum's expense goes up. Account `5000 Expenses`.

### 2. Debit and Credit

Two words that confuse non-accountants. Don't try to memorise "debit means up, credit means down" — whether each one means up or down depends on the account type. For day-to-day bbAccounts use, two facts cover almost every case:

- **To put points INTO a user's wallet:** *credit* `2100 User Wallets` for the amount, with the user picked as the subledger.
- **To pull points OUT of a user's wallet:** *debit* `2100 User Wallets` for the amount, with the user as the subledger.

The opposite side (debit when you put in, credit when you take out) is what balances it. Every recipe in this document follows that pattern.

### 3. Every entry must balance

Total debits across an entry's lines must equal total credits. The journal form will refuse to save an unbalanced entry. If you've put 100 on one side, you must put 100 on the other.

### Worked example: granting 100 points to forum user `alice`

Two lines:

| Account | Debit | Credit | Subledger |
|---|---|---|---|
| 5000 Expenses | 100.00 | | |
| 2100 User Wallets | | 100.00 | `alice` |

Total debits: 100. Total credits: 100. Balanced. `alice`'s wallet now reads +100.

That's the entire mental model. Everything that follows is variations on this pattern.

## Pools (currencies)

Every account belongs to exactly one **pool** (also called a currency). A pool is an isolated namespace: entries cannot mix pools, and balances are reported separately per pool. The extension ships with one pool, `POINTS`, which is what the seeded chart uses.

You can manage pools under **ACP → bbAccounts → Pools / Currencies**: add a new one (e.g. `GOLD` for a separate gold-economy ledger), rename, or disable. Disabled pools are hidden from the Chart of Accounts currency dropdown so no new accounts can be created under them; existing accounts and reversal of historical entries keep working.

## Tour of the seeded chart

The extension installs 5 accounts — one per accounting category. Here is what each is for and how often you'll touch it:

| Code | Name | Type | What it's for | Used often? |
|---|---|---|---|---|
| 1010 | Cash on Hand | Asset | If you ever model the forum holding "real" tokens (e.g. a treasury). Optional. | Rarely |
| **2100** | **User Wallets** | Liability | **Every user's balance lives here, tagged with their phpBB user_id as the subledger.** | **Every entry** |
| 3010 | Opening Balances | Equity | Used once, when migrating in existing balances from another system. | Once |
| 4000 | Revenue | Revenue | Used when the forum *receives* points (e.g. fees, penalties paid by users). | Sometimes |
| **5000** | **Expenses** | Expense | **Used when the forum *gives away* points (grants, rewards, prizes).** | **Most entries** |

The two **bolded** accounts (`2100` and `5000`) are the ones you'll use for the typical "admin awards points" pattern. The other three cover less common scenarios.

> **Tip:** if you want hierarchical reports later (e.g. an "Assets" rollup grouping `1010` plus future asset accounts), you can add your own parent accounts via ACP → bbAccounts → Chart of Accounts. The seed deliberately ships only postable accounts so there's nothing to accidentally post to.

## Walkthrough: grant 100 points to a user

Goal: give forum user `alice` 100 points.

1. Go to **ACP → bbAccounts → Journal entries → New entry**.
2. Fill in the header:
   - **Date:** today (default)
   - **Description:** e.g. `Grant for moderating March events`
   - **Reference type:** `manual` (see "About `reference_type`" below)
   - **Reference source / Reference id:** leave blank for an admin-entered grant
3. Fill in the lines:

   | # | Account | Debit | Credit | Subledger user | Memo |
   |---|---|---|---|---|---|
   | 1 | `5000 Expenses (POINTS)` | `100.00` | | — | |
   | 2 | `2100 User Wallets (POINTS)` | | `100.00` | `alice` | `March moderation grant` |

4. Click **Save**.
5. Go to **ACP → bbAccounts → Reports → Run** (trial balance for today).

You should see something like:

```
Pool: POINTS
2100  User Wallets   Liability      0.00     100.00
5000  Expenses       Expense      100.00       0.00
                                  ──────    ──────
                                  100.00     100.00     ✓ Balanced
```

`alice` now has a wallet balance of 100 points. The trial balance proves the books still balance after your entry.

### About `reference_type`

Each entry header carries three reference fields used to track *where the entry came from*. They are free-text but follow conventions:

- **`reference_type`** — one of `manual`, `auto`, or `import`:
  - `manual` — a human entered this in the ACP. Use this for any hand-typed entry.
  - `auto` — the system created this. Reversals (the **Reverse** button) use `auto` automatically; you should not pick it yourself.
  - `import` — created by a bulk import (e.g. CSV migration). Set automatically by the importer.
- **`reference_source`** — when an external extension posts entries through the ledger service it stamps these with its own namespace (e.g. `vendor.entity`). Leave this blank for admin-entered entries.
- **`reference_id`** — the foreign id in the source system (e.g. a raid id, a Patreon webhook id). Also blank for admin-entered entries.

For everything you do by hand from the ACP, **`manual` with the other two blank** is the right answer. Only set `reference_source`/`reference_id` if you're consciously linking the entry back to some external record.

## Walkthrough: reverse a mistake

bbAccounts treats journal entries as **immutable**. You don't edit or delete a saved entry. If you got something wrong, you *reverse* the original — which creates a new mirror-image entry that cancels it out — and then post the correct entry.

Goal: undo the 100-point grant to `alice` (it should have been 50).

1. **ACP → bbAccounts → Journal entries**.
2. Find the original grant entry. Click **Reverse**.
3. Confirm.
4. The journal list now shows the original *and* a reversal entry (`reference_type` = `auto`). `alice`'s wallet is back to 0.
5. Now post a fresh, correct grant of 50 (same procedure as the previous walkthrough, with `50.00` instead of `100.00`).

Why bother with this dance instead of just editing? Because the audit trail is the whole point. A user who saw "+100" yesterday and "+50" today knows exactly what happened. Silently changing the past would destroy that visibility — and would also make any downstream reports lie.

## Recipe book

Drop-in journal patterns for common scenarios. Replace `<user>` with the phpBB user_id (or pick from the autocomplete) and pick your own amount `X`.

### Grant points to a user

Use case: rewarding a user. Forum's expense pool goes up; user's wallet goes up.

| Account | Debit | Credit | Subledger |
|---|---|---|---|
| 5000 Expenses | X | | — |
| 2100 User Wallets | | X | `<user>` |

### Deduct points from a user (charging for a feature)

Use case: user pays the forum points for something (name change, bump, etc.). Forum's revenue goes up; user's wallet goes down.

| Account | Debit | Credit | Subledger |
|---|---|---|---|
| 2100 User Wallets | X | | `<user>` |
| 4000 Revenue | | X | — |

If the deduction is *not* income (e.g. you're correcting an over-grant), debit `2100 User Wallets` and credit `5000 Expenses` instead — that simply reverses an earlier outgoing.

### Transfer points between users

Use case: user A sends `50` to user B. The forum's totals don't change, only the per-user split inside `2100 User Wallets`.

| Account | Debit | Credit | Subledger |
|---|---|---|---|
| 2100 User Wallets | 50.00 | | `<sender>` |
| 2100 User Wallets | | 50.00 | `<recipient>` |

Same account on both sides. Allowed. The subledger user IDs are what differ.

### Opening balances (one-off migration)

Use case: you're migrating in balances from a previous points system. Each user already has a balance; you need to seed those into bbAccounts.

For each user with an existing balance N:

| Account | Debit | Credit | Subledger |
|---|---|---|---|
| 3010 Opening Balances | N | | — |
| 2100 User Wallets | | N | `<user>` |

Set **Reference type** to `import` if you're seeding via the (planned) CSV importer, or `manual` if you're entering them by hand. After migration, `3010 Opening Balances` will sit at the total of all migrated balances and shouldn't be touched again.

## Don'ts

- **Don't try to edit a saved entry.** There is no edit. Reverse and re-post. (See the reverse walkthrough above.)
- **Don't delete accounts that have ever been used.** The system locks immutable fields (code, type, currency) once any line references the account. You can disable it instead so it stops appearing in dropdowns.
- **Don't change an account's type after it has activity.** Same reason — historical reports would break.
- **Don't leave the subledger user blank on `2100`.** Lines on a subledger account without a user are technically saved but they make per-user balances meaningless.
- **Don't mix currencies in one entry.** Every line in an entry must share the same currency/pool. The journal form enforces this.

## Permissions — who sees what

bbAccounts ships two custom permissions plus relies on phpBB's "is the extension enabled" gate for user surfaces.

| Permission | Type | Default-granted to | What it unlocks |
|---|---|---|---|
| `a_accounts` | Admin | `ROLE_ADMIN_FULL` | Full ACP access: chart of accounts edit, journal create/reverse, currencies, CSV import, all Reports sub-modes |
| `u_accounts_view` | User | `ROLE_MOD_FULL` (also: any group/user you grant manually) | ACP Reports (read-only) + the front-end Reports page at `/app.php/bbaccounts/reports/...` + the "bbAccounts Reports" link in the sandwich/quick-links menu + the post-profile balance badge on **other** users' profiles |
| *(no permission needed — extension-enabled gate)* | User | every logged-in user | UCP "bbAccounts" tab → My Wallet + My Statement + own-balance badge on **own** profile + bbGuild portal own-balance widget |

**Common surprise:** an admin testing as their own account doesn't automatically have `u_accounts_view`. The migration grants it to `ROLE_MOD_FULL`, so it flows to anyone in a group using that role (typically Global Moderators). Admins themselves usually inherit only `ROLE_ADMIN_FULL` + `ROLE_USER_STANDARD`. To grant access:

- ACP → Permissions → **User permissions** → enter your username → tab **User permissions** → set "Can view bbAccounts reports" to **Yes** → Apply (per-user override), or
- ACP → Permissions → **Group permissions** → pick a group → grant the perm there for everyone in the group.

End users (regular registered) see their own wallet and statement in the UCP without any permission grant — the extension being installed is the gate. They never see other users' data unless they're a mod.

## Where to look next

- **Trial balance** (Reports → Run) — overview of where every account stands as of a chosen date.
- **Account ledger** (Reports → Account ledger) — every line that ever hit a specific account, paginated.
- **User statement** (Reports → User statement) — every line that ever touched a specific user.
- **Single-account balance lookup** (Reports → Account balance) — fast spot-check of one account.
- **User-balance lookup** (Reports → User balance) — fast spot-check of one user's balance against one subledger account.
- **CSV bulk import** (Journal → Import CSV) — upload many entries at once with preview + transactional commit.
- **UCP "My Wallet" / "My Statement"** — what regular users see (their own balances + transaction history).
- [`contrib/specs/2026-04-26-bbaccounts-phase1-design.md`](../specs/2026-04-26-bbaccounts-phase1-design.md) — the design spec, if you want the deep version.

If something here was unclear, that's a documentation bug — please open an [issue](https://github.com/avatharbe/bbAccounts/issues).
