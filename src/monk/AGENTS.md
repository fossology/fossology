<!-- SPDX-FileCopyrightText: © Fossology contributors
     SPDX-FileCopyrightText: © Kaushlendra Pratap Singh <kaushlendra-pratap.singh@siemens.com>

     SPDX-License-Identifier: GPL-2.0-only
-->
# Monk Agent Guide

## Purpose

Monk is the FOSSology license comparison agent. It loads the license database,
builds the matching structures used by the scanner, and supports CLI, scheduler,
and offline knowledgebase flows. The module also ships related executables for
bulk scanning and Kotoba integrations.

## Module Layout

- `agent/` contains the C implementation and shared support code.
- `agent/generator/` contains the inputs used to generate matching helpers.
- `agent_tests/Unit/` contains C unit tests for the agent internals.
- `agent_tests/Functional/` contains CLI and scheduler-style tests.
- `ui/` contains PHP entry points for Monk, Monk bulk, and Kotoba.
- `monk.conf`, `monkbulk.conf`, and `kotoba.conf` define the installed module
  variants.

## Build And Packaging Notes

- The module is wired from [src/monk/CMakeLists.txt](CMakeLists.txt).
- The build generates a helper source file from `agent/generator/` before
  compiling the agent executables.
- The installed binaries are `monk`, `monkbulk`, and `kotoba`, each with its
  own install path and configuration file.

## Key Technical Details

- Language: C for the agent and PHP for the UI integration.
- Main source files: `agent/monk.c`, `agent/monkbulk.c`, `agent/kotoba.c`,
  `agent/scheduler.c`, `agent/database.c`, and related helpers.
- The agent uses a generated visitor and shared matching state, so changes to
  comparison logic should preserve the data flow between CLI, scheduler, and
  offline modes.
- Database access and matching logic are tightly coupled; keep changes focused
  on the specific matching or serialization behavior being changed.

## Expectations For Changes

- Keep changes scoped to the intended comparison rule, scheduler path, or UI
  flow.
- Do not mix generator changes, matching algorithm changes, and UI updates in
  the same patch unless the task explicitly requires all three.
- Update unit tests and functional tests when behavior changes.
- If you touch the generated visitor pipeline, verify whether the generator
  input or generated file is the correct source of truth.

## Common Gotchas

- The module has multiple binaries that share implementation files but differ
  in install targets and runtime behavior.
- Offline knowledgebase mode and scheduler mode have different initialization
  paths.
- The generator output is part of the build sequence, so rebuild after changing
  generator inputs.
