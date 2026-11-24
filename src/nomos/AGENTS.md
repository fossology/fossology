<!-- SPDX-FileCopyrightText: © Fossology contributors
     SPDX-FileCopyrightText: © Kaushlendra Pratap Singh <kaushlendra-pratap.singh@siemens.com>

     SPDX-License-Identifier: GPL-2.0-only
-->
# Nomos Agent Guide

## Purpose

Nomos is the FOSSology license scanner agent. It uses regular expressions,
heuristics, and generated signature data to detect licenses in source files.
The upstream module README describes the end-user behavior in more detail.

## Module Layout

This module is organized as follows:

- `agent/` contains the C source for the executable and supporting helpers.
- `agent/encode.c` and `agent/generator/` build generated signature data.
- `agent_tests/` contains unit and functional tests.
- `ui/` contains the PHP integration used by the web UI.
- `nomos.conf` stores the module configuration shipped with the install.

## Build And Packaging Notes

- The module is wired from [src/nomos/CMakeLists.txt](CMakeLists.txt).
- The main installed executable comes from the `nomos_exec` target and is
  installed as `nomos`.
- The build also creates `nomossa` for standalone use and coverage variants
  for testing.
- The build generates `_autodata.c`, `_autodefs.h`, and `_precheck.c` from the
  signature generator pipeline. Prefer updating generator inputs such as
  `agent/generator/STRINGS.in` instead of editing generated output directly.

## Key Technical Details

- Language: C.
- Runtime dependencies: GLib, PostgreSQL client libraries, `json-c`, pthread,
  and the FOSSology shared libraries.
- Core source files live in `agent/nomos.c`, `parse.c`, `process.c`,
  `licenses.c`, `nomos_regex.c`, `nomos_utils.c`, and related helpers.
- The scanning logic is stateful and data-driven, so changes to heuristics or
  generated tables should be kept narrow and tested carefully.

## Expectations For Changes

- Keep detection changes scoped to the intended rule, phrase, or behavior.
- Do not change signature generation, parsing, and output formatting in the
  same change unless the task explicitly needs all of them.
- Add or update tests under `agent_tests/` when changing detection behavior.
- If you touch the generator, re-check whether the produced artifacts are
  meant to be committed or regenerated in the build only.

## Common Gotchas

- Changes to `STRINGS.in` or parser logic affect future scans, not already
  stored results.
- The module has both scheduler-backed and standalone execution paths.
- Generated code depends on the build order, so rebuild after changing inputs.
