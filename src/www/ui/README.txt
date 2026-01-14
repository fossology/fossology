SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

SPDX-License-Identifier: GPL-2.0-only

UI directory structure (current source tree)

The legacy documentation may refer to paths like ui/common, ui/plugins, ui/template.
In the current Fossology source tree, the classic web UI lives under:

  src/www/ui/

High-level overview of commonly present UI subdirectories:
  src/www/ui/              - UI plugins (PHP)
  src/www/ui/template/     - Twig templates
  src/www/ui/images/       - UI images/assets
  src/www/ui/css/          - UI stylesheets
  src/www/ui/scripts/      - UI JavaScript

This list provides a high-level orientation of the UI layout.
Other subdirectories may exist and evolve over time as the UI implementation
changes. The src/www/ui/ directory itself is the authoritative reference.

Plugins have prefixes for maintenance convenience.
  "core-"  :: These are core plugins that are used by other plugins.
              Core plugins may not generate any UI output.
  "ui-"    :: These are dependent on each other and define the user interface.
  "user-"  :: These manage user accounts.
  "agent-" :: These are not dependent on the UI and control agents.
  "jobs-"  :: These are not dependent on the UI and manage jobs.
  "admin-" :: These are not dependent on the UI and manage the system/db.

NOTE: This is just a naming convention.  The name does not influence the
functionality or implementation.

All plugins must end with ".php" in order to be loaded.
The load order:
  - Initialize(): called in indeterminate order.  This function must NOT
    depend on other plugins.  (In most cases, stick with the template so
    it won't do anything.)
  - Then plugins are sorted:
    - If they have the same name, then the highest version comes first.
      The one that comes first will be used.
    - Sort by dependencies.  Cyclical dependencies WILL ALWAYS fail.
      Things with no dependencies come first.  Then comes the things
      that depend on them.
  - After sorting, PostInitialize() is called. This can use any dependent
    modules.
