# Changelog

All notable changes to bbAccounts are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0-alpha] — 2026-05-23

### Added

- **Character subledger support.** New `'character'` value in `VALID_SUBLEDGER_TYPES`,
  backed by a new `subledger_player_id` UINT column + index on
  `bbaccounts_journal_lines`. Journal lines hitting a `'character'`-type account
  populate `subledger_player_id` instead of `subledger_user_id`. The reference is
  opaque to bbAccounts — conventionally `bb_players.player_id` (bbGuild).
- **Strict mutual exclusion** in `ledger::validate_lines()`. Each account's
  `subledger_type` dictates which subledger ID column its lines must populate:
  `''` accepts neither, `'customer'`/`'supplier'` require `subledger_user_id`,
  `'character'` requires `subledger_player_id`. Mixed populations throw at
  posting time with specific error messages.
- **`ledger::anonymize_player_subledger(int $player_id, int $replacement = 0): int`** —
  consumer-driven anonymization. Rewrites all journal lines for a given player_id
  to the replacement (default 0). bbAccounts stays source-agnostic; consumers
  (e.g. bbDKP) call this method from their own listener on the relevant
  player-deleted event.
- **`ledger::get_subledger_balance_by_character(int $account_id, int $player_id, int $as_of = 0): string`** —
  per-character analog of `get_subledger_balance()`.
- **`ledger::get_subledger_account_balances_by_character(int $player_id, int $from = 0, int $to = 0): array`** —
  per-character analog of `get_subledger_account_balances()`, returning the same
  `opening`/`period_debit`/`period_credit`/`closing` shape so consumers can use
  the methods interchangeably depending on subledger source.
- **CSV importer accepts the optional `subledger_player_id` column.** Validation
  is type-dispatched: `'customer'`/`'supplier'` lines still require
  `subledger_user_id` and validate it exists in `phpbb_users`; `'character'`
  lines require `subledger_player_id` and do NOT validate its existence (the
  source-agnostic posture).

### Changed

- `ledger::create_account()` and `ledger::list_accounts()` error messages updated
  to mention all four `subledger_type` values.
- `ledger::sum_lines_for_account()` (protected helper) gained an optional
  `int $player_id = 0` parameter. Existing call sites unchanged; the parameter
  defaults to 0 so user-keyed reads are unaffected.

### Migration

- New migration `v1_1_0_character_subledger` — additive (ALTER TABLE adds column
  and index). Non-destructive. Historical journal lines preserved with
  `subledger_player_id = 0` default.

### Verified

- All existing service-layer tests (ledger_test, csv_importer_test, listener_test)
  still pass without modification.
- New tests: `ledger_character_test` (6 cases), `ledger_anonymize_player_test`
  (5 cases), `ledger_character_balance_test` (5 cases). CSV importer test gained
  4 new cases covering character subledger support and back-compat.

### Notes

- bbPoints v2.0 (existing consumer) is unaffected. Customer-subledger flow is
  unchanged. A manual smoke test confirming bbPoints' posting flow against
  v1.1.0-alpha should be run before tagging the release.
- This release unblocks bbDKP v2.0.0-alpha1, which depends on character
  subledgers to attribute per-character DKP balances.

## [1.0.0-alpha] — 2026-05-14

Initial baseline. Pre-release; never publicly released. See
`contrib/specs/2026-04-26-bbaccounts-phase1-design.md` for Phase 1 design.

[1.1.0-alpha]: https://github.com/avatharbe/bbAccounts/releases/tag/v1.1.0-alpha
[1.0.0-alpha]: https://github.com/avatharbe/bbAccounts/releases/tag/v1.0.0-alpha
