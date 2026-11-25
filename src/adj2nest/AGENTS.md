<!-- SPDX-FileCopyrightText: © Fossology contributors
     SPDX-FileCopyrightText: © Kaushlendra Pratap Singh <kaushlendra-pratap.singh@siemens.com>

     SPDX-License-Identifier: GPL-2.0-only
-->
# Adj2nest Agent Guide

## Purpose

Adj2nest converts adjacency-list upload tree data into nested-set form and
updates user permissions for upload records. It exists as a small but critical
database-oriented utility in the FOSSology workflow.

## Module Layout

- `agent/` contains the C implementation.
- `ui/` contains the PHP integration.
- `adj2nest.conf` stores the installed module configuration.
- `agent/adj2nest.c` is the main executable entry point.
- There is no module-local `README.md` yet, so the CMake file and source code
are the primary sources of truth for this module.

## Build And Packaging Notes

- The module is wired from [src/adj2nest/CMakeLists.txt](CMakeLists.txt).
- The build produces a single `adj2nest` executable and installs it into the
  agent directory for the module.
- The target links against the FOSSology shared libraries and uses `-fPIC` and
  C99-oriented compile settings.

## Key Technical Details

- Language: C.
- The main source file is `agent/adj2nest.c`.
- The algorithm builds a tree in memory, walks it depth-first, and writes left
  and right nested-set values back to the database.
- The module is intentionally database-heavy and relies on consistent tree
  ordering and update sequencing.

## Expectations For Changes

- Keep changes focused on the tree-building, traversal, or update path that is
  actually being modified.
- Do not mix SQL shape changes, traversal logic changes, and UI changes unless
  the task explicitly requires all of them.
- Validate any tree-order or parent/child assumption before changing it.
- If you add tests, target the database behavior or traversal edge case you are
  changing.

## Common Gotchas

- The module assumes nested-set numbering starts at 1 and uses strict ordering.
- Parent discovery and sibling chaining are sensitive to update order.
- A small traversal bug can produce incorrect permission or upload tree state,
  so changes should be reviewed conservatively.
