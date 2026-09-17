<!-- SPDX-FileCopyrightText: © Fossology contributors
     SPDX-FileCopyrightText: © Kaushlendra Pratap Singh <kaushlendra-pratap.singh@siemens.com>

     SPDX-License-Identifier: GPL-2.0-only
-->
# Maintagent Guide

## Purpose

Maintagent performs database and repository maintenance tasks such as vacuum,
analyze, reindex, removing orphaned records, cleaning logs, and deleting
expired tokens.

## Module Layout

- `agent/` contains the executable source.
- `ui/` contains the PHP integration.
- `maintagent.conf` stores module configuration defaults.
- The local README captures the operational behavior and safety notes.

## Build And Packaging Notes

- The module is wired from [src/maintagent/CMakeLists.txt](CMakeLists.txt).
- The only installed executable is the `maintagent` target from `agent/`.
- The build links against the FOSSology shared libraries and PostgreSQL client
  headers/libraries.

## Key Technical Details

- Language: C.
- Main source files: `maintagent.c`, `process.c`, `utils.c`, and `usage.c`.
- The implementation uses database-heavy workflows and can perform destructive
  operations on repository data.
- Some operations use temporary tables and multi-step SQL flows instead of a
  single query.

## Expectations For Changes

- Treat this module as high risk. Even a small logic change can delete or
  modify data.
- Keep changes surgical and validate them against the intended SQL path.
- Do not mix maintenance behavior changes with unrelated refactors.
- Add or update tests only when they materially improve coverage for the
  change being made.

## Safety Notes

- Review every delete or update path carefully before changing it.
- Be especially cautious with orphan removal, cleanup jobs, reindexing, and
  any flow that touches uploads, logs, or tokens.
- If a task affects performance or locking, call that out explicitly because
  maintenance workloads often run on live data.
