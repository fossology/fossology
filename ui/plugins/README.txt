Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
  - Initialize(): called in indeterminant order.  This function must NOT
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

