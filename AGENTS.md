<!-- SPDX-FileCopyrightText: © Fossology contributors
     SPDX-FileCopyrightText: © Kaushlendra Pratap Singh <kaushlendra-pratap.singh@siemens.com>

     SPDX-License-Identifier: GPL-2.0-only
-->
# FOSSology AI Agent Guide

This file is the entry point for AI-assisted work in the FOSSology repository.
It exists to keep changes targeted, safe, and consistent with the existing
project structure.

## Read First

Before changing code, read the project-level documentation that already exists:

- [README.md](README.md) for project scope, installation, and support links.
- [CONTRIBUTING.md](CONTRIBUTING.md) for coding, commit, PR, and DCO rules.
- The module-specific AGENTS.md file in the relevant `src/<module>/` directory.

If the needed context is still missing after those files, inspect the local
module README, CMake files, and the surrounding code before guessing.

## Core Rules For AI Contributors

- Stay inside the requested scope. If the task is about one endpoint, one
  agent, or one UI flow, do not make unrelated edits elsewhere.
- Keep diffs small and logical. One functional change per pull request is the
  default unless the user explicitly asks for a larger refactor.
- Do not mix formatting, cleanup, or dependency changes with behavior changes
  unless they are directly needed for the task.
- Preserve existing behavior unless the task explicitly asks to change it.
- Prefer the simplest implementation that fits the existing codebase.
- If there is a feature implementation, Try and understand the flavour and process followed 
  for other similar functions/features. 
- Update tests and docs when behavior changes. If tests are not practical,
  explain the gap clearly.
- Do not rename files, symbols, or public interfaces unless the task requires
  it.
- Do not move code between modules just to "improve" structure.
- If a change touches generated code, verify whether the generator or source of
  truth should be updated instead of editing generated output directly.
- Understand the core-logic of how a specific feature/function is implemented in the project,
  then only do an actual implementation.
- DO NOT WRITE MADE UP LOGIC, first reason if the 

## Additional Best Practices

- Start with the most specific context available: the module README, the local
  `AGENTS.md`, nearby code, and any existing tests for that module.
- Prefer an existing implementation pattern in the same area instead of
  inventing a new one.
- Keep behavior changes and cleanup separate unless the cleanup is required to
  make the behavioral fix correct.
- When code paths are generated, schema-backed, or install-time sensitive,
  verify the source of truth before editing outputs.
- Treat public interfaces, data formats, and database queries as compatibility
  boundaries unless the task explicitly changes them.
- When in doubt about scope, stop and confirm rather than broadening the patch.
- If behavior changes, update or add tests in the same module whenever that is
  practical.
- Preserve SPDX headers, copyright notices, and other repository-specific
  metadata in files that expect them.
- Keep refactors small and local; do not cascade a feature change into an
  unrelated cleanup across other agents or UI paths.

## Open Source Contribution Expectations

FOSSology follows the contribution rules described in [CONTRIBUTING.md](CONTRIBUTING.md).
AI contributors should respect the same standards:

- Make the smallest reasonable change that solves the problem.
- Keep the change focused. If `/getUser` needs a bug fix, do not add styling
  fixes or unrelated cleanup in another endpoint.
- Use the existing coding style and conventions for the touched area.
- Keep commits, pull requests, and review notes logically grouped.
- Add or preserve SPDX and copyright headers where the project expects them.
- Keep changes compatible with the project license and DCO workflow.
- Prefer evidence from existing docs, tests, and nearby code over assumptions.

## Repository Shape

The main implementation lives under [src/](src). Each agent directory usually
contains some mix of:

- `agent/` for the executable source code.
- `ui/` for PHP or Twig UI integration.
- `agent_tests/` or `ui_tests/` for tests when present.
- `<agent>.conf` for module configuration defaults.
- `CMakeLists.txt` for build and install wiring.

When editing an agent, first read the local module README and local AGENTS file
if one exists.

## Agent Guide Index

- [src/nomos/AGENTS.md](src/nomos/AGENTS.md)
- [src/maintagent/AGENTS.md](src/maintagent/AGENTS.md)
- [src/scanoss/AGENTS.md](src/scanoss/AGENTS.md)
- [src/monk/AGENTS.md](src/monk/AGENTS.md)
- [src/ununpack/AGENTS.md](src/ununpack/AGENTS.md)
- [src/adj2nest/AGENTS.md](src/adj2nest/AGENTS.md)

More module-level guides should be added over time using the same pattern.

## When Context Is Missing

If the docs do not answer a required detail, do this before editing:

1. Inspect the relevant CMake file, module README, and nearby source files.
2. Look for existing tests or examples in the same module.
3. Prefer asking for clarification over guessing when the risk is high.
4. Leave a short note in the PR description or final response if a gap remains.
