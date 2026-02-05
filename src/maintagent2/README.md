# Maintagent2 — Quick Usage & When To Use

## Purpose

- `maintagent2` is the FOSSology maintenance agent. It runs database and
  repository housekeeping tasks (vacuum/analyze, reindex, remove orphaned
  files/rows, clean logs, remove expired tokens, etc.).

## When to use `maintagent2` vs `maintagent`

- Both agents provide almost the same maintenance actions and command-line
  options. Use either for standard cleanup tasks.
- Use `maintagent2` when you specifically need:
  More structured START/END timing notices and duration logs. `maintagent2`
  provides `log_action_start()` / `log_action_end()` and monotonic timers
  which emit extra detail at higher verbosity levels (see `-v`).

### Temp-tables and multi-step operations (important behavioral differences)

- `maintagent2` uses session-local temporary tables and a multi-step approach
  for several operations (notably `removeUploads()`).
  This differs from the older `maintagent` which often executed single-statement
  DELETEs with embedded sub-selects.

- Example (what `maintagent2` does in `removeUploads()`):
  1. Create a session temp table `tmp_ids_<pid>(id bigint)`.
  2. Insert candidate IDs into that temp table via `INSERT ... SELECT ...`.
  3. Execute a `DELETE FROM upload USING tmp_ids_<pid> WHERE upload.upload_pk = tmp_ids_<pid>.id;` (join-delete).
  4. Drop the temp table.

- Why this matters:
  - Avoids large client-side `IN (...)` lists and repeated subqueries by
    moving the candidate set into a server-side temp table and using a
    join-based delete; this can improve performance and reduce client/DB
    parsing overhead for large ID sets.
  - Session-local temp tables reduce risk of cross-session collisions
    because the name embeds the process id and is dropped when done.
  - The multi-step approach may change locking/transaction characteristics
    compared with a single large SQL statement — evaluate in a staging
    environment if you have heavy concurrency or strict locking needs.

## Build

From repository root (CMake build):

```sh
cmake -S . -B build -G Ninja
cmake --build build --parallel
sudo cmake --install build
```

## Example invocations

- Routine non-slow maintenance:

```sh
./maintagent2 -a
```

- Run all maintenance (including slow operations):

```sh
./maintagent2 -A
```

- Vacuum and analyze the database:

```sh
./maintagent2 -D
```

- Reindex all tables (run in maintenance window):

```sh
./maintagent2 -I
```

- Remove uploads with no pfiles:

```sh
./maintagent2 -R
```

## Verbosity and timing

- `-v` increases log verbosity (repeatable). Many log/debug prints are
  guarded by `agent_verbose`.
- At `agent_verbose >= 3` `maintagent2` emits extra timing details via
  `log_action_start()`/`log_action_end()` (monotonic durations plus timestamps).
- Verbose mode only affects logging and detail level; it does not change SQL
  queries or add extra DB joins.

## Safety notes

- Many operations can delete rows/files. Test on a staging instance or
  run against a recent backup before using destructive options (e.g. `-Z`,
  `-R`, `-E`, `-g`, `-o`).
- Run long operations (reindex, vacuum, delete-old-gold) during off-peak
  windows to minimize impact.

## Config & logs

- The agent relies on `fo_scheduler_connect()` for DB/scheduler config. If
  you run standalone, ensure DB connection info (Db.conf / environment
  variables) is present.
- Check `src/maintagent2/maintagent.conf` for module configuration defaults.
- Inspect scheduler and container logs when running under Docker; agents
  print START/END notices and progress to stdout which appear in the
  scheduler/service logs.

## Source locations

- maintagent2 sources: `src/maintagent2/agent/` (main, process, utils, usage)
- original maintagent sources: `src/maintagent/agent/`
