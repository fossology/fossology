<!-- SPDX-FileCopyrightText: © Fossology contributors
     SPDX-FileCopyrightText: © Kaushlendra Pratap Singh <kaushlendra-pratap.singh@siemens.com>

     SPDX-License-Identifier: GPL-2.0-only
-->
# Ununpack Agent Guide

## Purpose

Ununpack is the universal unpacker. It extracts archives and package formats,
traverses nested content, and feeds unpacked artifacts back into FOSSology.
The module also includes a standalone path for limited execution scenarios.

## Module Layout

- `agent/` contains the C implementation and unpack helpers.
- `agent/README` documents the supported archive and filesystem handling.
- `agent_tests/Unit/` contains unit tests for helpers and traversal logic.
- `agent_tests/Functional/` contains functional tests for CLI and agent flows.
- `ui/` contains the PHP integration.
- `ununpack.conf` and `mod_deps` define the installed module configuration and
  dependencies.

## Build And Packaging Notes

- The module is wired from [src/ununpack/CMakeLists.txt](CMakeLists.txt).
- The build creates `ununpack`, `departition`, coverage variants, and
  standalone targets.
- The build links against `gcrypt`, `magic`, and the FOSSology shared libraries
  depending on the target.

## Key Technical Details

- Language: C.
- Main source files: `agent/ununpack.c`, `agent/traverse.c`,
  `agent/departition.c`, `agent/utils.c`, and the format-specific helpers.
- The module is traversal-heavy and stateful, with recursion and repository
  upload handling intertwined with archive detection.
- Several formats are handled by dedicated helpers, so changes should stay
  focused on one unpack path at a time.

## Expectations For Changes

- Keep archive-handling changes tightly scoped to the target format or flow.
- Do not combine repository handling, recursion policy, and format support
  changes unless the task requires all of them.
- Update unit or functional tests when the unpack or traversal behavior
  changes.
- If adding format support or altering detection, verify the helper selection
  logic and any install-time assumptions.

## Common Gotchas

- This agent has both scheduler-backed and standalone execution paths.
- Recursion, pruning, and repository writing affect extraction safety and can
  change disk usage significantly.
- The supported format matrix is wide, so regressions can hide in format-specific
  helpers rather than the top-level CLI.
