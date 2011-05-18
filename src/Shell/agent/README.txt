Mon Aug 28 13:37:04 MDT 2006

Normally, an engine is supposed to run once and not take a heavy cost
from spawning processes.  But, sometimes this is impractical.
Examples:
  Ununpack spawns processes for every unpacking stage.
  Unzip, bzcat, tar, etc. are all spawned to create the output.
  Rewriting all of these so they read from stdin and never spawn is not
  realistic.

  For uploading data into the database...
  Every file first calls ununpack (spawning processes like mad).
  Then it calls the dbloader -- ok, that does not need to spawn.
  Then it calls repimport to populate the repository.
  These steps always happen together.  Due to the cost from ununpack's
  spawning, there is no reason not to spawn 3 more processes.

Thus, the "Engine-Shell" is created.
It is an always running elements (API) that spawns a command-line.
Any command-line will work, even a shell script.

This is useful for any engine that will not be called millions of times,
or requires spawning.

For the uploading part, the command can actually be
  ./Engine-Shell unpack 'ununpack -RC -d temp.%U -L log-%U.xml %1 \
	    && dbload log-%U.xml && repimport file XML < log-%U.xml'

The percent signs are replaced by the spawner.
  %{%}  = percent sign
  %{P}  = PID of the spawner!
  %{PP} = PPID of the spawner!
  %{U}  = Unique string assigned by the spawner!
  %{A}  = Agent name assigned to the spawner (e.g., "license" "pkgmetagetta")
  %{1}  = the first arg from scheduler  (there is no %{0})
  %{2}  = 2nd arg from scheduler
  %{1000} = 1000th arg from the scheduler (no real limit)
  %{*}  = all args from the scheduler

