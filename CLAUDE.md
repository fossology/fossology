# Claude Code Instructions — FOSSology

## GSoC 2026 Context (read at session start)

This repo is being worked on as part of a GSoC 2026 application to FOSSology. At the start of every session, read `GSOC_2026_STRATEGY.md` to get the current strategy, open action items, competition landscape, and mentor contacts before doing anything else.

Key facts (quick reference):
- **Primary target:** Idea #2 — Report Aggregation (SPDX + CycloneDX + ReadMeOSS + CLIXML)
- **Open PR:** #3500 — fix irrelevant-file exclusion from SPDX/ReadmeOSS reports
- **Staked claim:** Comment posted on Discussion #3267
- **Key mentor:** @shaheemazmalmmd (OrgAdmin, already knows us from PR)
- **Acceptance criteria:** 1 significant merged PR + draft reviewed by org member before submission

---

## Pre-Push Checklist (MANDATORY — do this before every `git push`)

We got burned by CI failures on PR #3500 because we didn't run tests locally first. Never push without doing this:

### 1. Run unit tests for affected area

```bash
cd /Users/pranavkrishnau/Desktop/Foscology/src

# Run tests for the specific directory you changed
php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_STRICT" \
  vendor/bin/phpunit --no-coverage lib/php/Report/

# Or run all of lib/php (223 DB-related failures are expected locally — that's fine)
php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_STRICT" \
  vendor/bin/phpunit --no-coverage lib/php/
```

**Expected output:** `OK (N tests, M assertions)` — if you see FAIL or ERROR in non-DB tests, fix before pushing.

DB tests (need live Postgres) will error locally — that's normal. CI handles those via Docker.

### 2. Check mock completeness when writing tests

When mocking a class that extends `ClearedGetterCommon` (or any class using a DI container), **mock every service the constructor fetches**, not just the ones your test directly uses. Check the parent `__construct` for `$container->get(...)` calls.

Known required mocks for `ClearedGetterCommon` subclasses:
- `dao.clearing`
- `dao.license`
- `dao.agent`
- `dao.tree` ← **this one bit us — easy to miss**
- `dao.upload`
- `db.manager`

### 3. Check Signed-off-by email

The commit author email must match the `Signed-off-by` email exactly. Use:

```bash
git config user.email   # must return: pranavkrishnau@users.noreply.github.com
```

When committing, always use `-s` flag or add manually:
```
Signed-off-by: pranavsnotebook-a11y <pranavkrishnau@users.noreply.github.com>
```

If you ever need to fix it after the fact: `git commit --amend -s` then `git push --force-with-lease`.

### 4. Commit message format (conventional commits)

FOSSology uses commitlint. Format must be:
```
type(scope): short description

Body (optional).

Signed-off-by: pranavsnotebook-a11y <pranavkrishnau@users.noreply.github.com>
```
Valid types: `fix`, `feat`, `docs`, `refactor`, `test`, `chore`. Scope = module name (e.g. `report`, `spdx`, `copyright`).

---
