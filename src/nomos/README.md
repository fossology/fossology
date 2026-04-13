<!--
SPDX-FileCopyrightText: © Fossology contributors

SPDX-License-Identifier: GPL-2.0-only
-->

# Nomos — FOSSology license scanner agent

Nomos is a license identification agent that uses short phrases (regular
expressions) and heuristics to detect licenses in source files. It can
recognise exact licenses as well as "style" matches where a text has
similarities with a known license type. The phrase signatures live in
`STRINGS.in`.

## Prerequisites

| Dependency | Reason |
|---|---|
| CMake ≥ 3.13 (≥ 3.19 for packaging) | Build system generator |
| A C compiler (GCC or Clang) | Compiles the agent |
| GLib 2.x development headers | Core data structures |
| PostgreSQL client development headers (`libpq-dev` / `postgresql-devel`) | Required by `libfossology` even for CLI-only use |
| `libfossology` (built as part of FOSSology) | Scheduler communication, DB access |
| A working `fossology.conf` at the configured `SYSCONFDIR` | Runtime configuration (agent won't start without it) |

On Debian / Ubuntu the quickest way to pull in every build dependency is:

```sh
# from the repository root
sudo utils/fo-installdeps
```

Each module may also ship a `mod_deps` script for its own extras
(see `src/scheduler/mod_deps` for an example).

## Building

Nomos **must** be built as part of the full FOSSology tree — For standalone 
nomos please check the release packages.

The nomos binary will appear under `build/src/nomos/agent/`.

## Command-line usage

```
nomos [options] [file …]
```

| Flag | Description |
|---|---|
| `file` | Scan the given file(s) and print detected licenses to stdout |
| *(no file)* | Read work items from the FOSSology scheduler (agent mode) |
| `-c DIR` | System configuration directory (must contain `fossology.conf`) |
| `-i` | Initialise the database, then exit |
| `-l` | Print full file path in output (CLI mode only) |
| `-v` | Verbose (`-vv` for more) |
| `-J` | JSON output |
| `-S` | Print highlight / match-position info to stdout |
| `-d DIR` | Scan an entire directory |
| `-n N` | Spawn N−1 child workers (default 2 when `-d` is used) |
| `-V` | Print version and exit |

### Example

```sh
# scan a single file
./nomos -l /path/to/source/file.c

# scan a directory tree with four workers, JSON output
./nomos -J -d /path/to/project -n 4
```

## Directory layout

```
src/nomos/
├── agent/            # C source for the nomos binary
│   ├── STRINGS.in    # license signature phrases
│   ├── parse.c       # main detection logic
│   ├── nomos.h       # primary header, compile-time defines
│   └── …
├── agent_tests/
│   ├── Unit/         # CUnit-based unit tests
│   └── Functional/   # functional / integration tests
└── ui/               # PHP UI integration (OneShot, etc.)
```

## Adding a new license signature

See the wiki guide:
[How to add a new license signature](https://github.com/fossology/fossology/wiki/Nomos).

The short version:

1. Add the new signature phrase(s) to `STRINGS.in`.
2. Add the corresponding detection logic in `parse.c`.
3. Recompile (`cmake --build build --parallel`).
4. Test on the command line before installing.
5. Add a test case under `agent_tests/`.

> **Important:** Changes to `STRINGS.in` or `parse.c` are *not*
> retroactively applied to files already scanned. Previously scanned
> files keep their old results unless you force a rescan or install a
> new agent version (which increments the agent revision in the DB).

## Debugging

Two compile-time defines are especially useful for diagnosing detection
issues. They can be activated around line 31 of `parse.c`:

- **`PROC_TRACE`** — logs every regex attempt and its result. Grep the
  output for `addRef` to see successful matches.
- **`DOCTOR_DEBUG`** — dumps the buffer before and after the "doctor"
  pre-processing pass. Look for `[Dr-BEFORE:]` and `[Dr-AFTER]` in the
  output.

### Other compile-time defines

| Define | Purpose |
|---|---|
| `STANDALONE` | Build without FOSSology DB support (uses `standalone.h` stubs) |
| `MEMORY_TRACING` | Enable the `DMalloc` debug-malloc wrapper |
| `SHOW_LOCATION` | Include byte-offset location in output (normally always on) |
| `STOPWATCH` / `TIMING` | Performance measurement |

## Running the tests

```sh
# configure with testing enabled
cmake -S . -B build -DTESTING=ON
cmake --build build --parallel

# run nomos tests
cd build
ctest --test-dir src/nomos -V
```
