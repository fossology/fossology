Dr. Neal's Tutorial on Agent Creation
by Dr. Neal Krawetz
Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
--------------------------------------------------------------


Agents have three components:
  - The agent itself. This performs the analysis and stores results in the
    database.  Since most agents store stuff in the database, you will
    either need to create a DB table, or use an existing one (e.g., the
    attribute table is generic enough for most uses).

  - The jobqueue.  The jq_args parameter defines what information is
    passed to the agent.  These are agent-specific arguments.

  - The Interface.  The user interface (UI) or command-line interface (CLI)
    needs to schedule the jobs in the jobqueue and it needs to display any
    results.

This tutorial creates a basic word-count (wc) agent and stores the
results in the DB.


===========================================================
Part I: The Job Queue

The jobqueue operates in two modes: generic and per-host.
The basic idea is that the file repository may be split across hosts.
Rather than transfering files across the network (e.g., NFS), it may be
faster for agents to run on the same host as the file.

For example, the wget_agent downloads a file from the Internet and stuffs
it into the repository.  Since the repository host is unknown, wget_agent
can really run on any host.  This is an example of a generic agent.

In contrast, the license analysis agents process files in the repository.
Since the hosts are known, it is faster to run these agents on the specific
file.

There is one other distinction: the generic-host entries in the jobqueue
contain one request.  The value of the jobqueue.jq_args is passed as-is to
the agent and the agent is assumed to know how to parse the line.  In
contrast, the host-specific agents have an SQL line in the
jobqueue.jq_args.  The scheduler runs the SQL and sends the results of this
multi-SQL query (MSQ) to the agent.

The difference between generic-host and MSQ is critical: if an agent needs
to perform a task on hundreds of DB items, then it either needs to process
the SQL query itself (using parameters from the jq_args), or it needs to
process one item that the scheduler retrieves using the MSQ.

Since this example wc agent is expected to run on thousands of files in the
repository, it is a good idea to use the host-specific, MSQ option.

With MSQ queries, we need to know the data and the stop condition.  The
stop condition identifies when the file has been processed.  In this
example, there is a custom table, "agent_wc", for storing results.  The SQL
for the jq_args should return every pfile and repository file name
associated with the project and that does not already exist in the agent_wc
table:
  SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile, pfile_fk
  FROM uptreeup
  WHERE upload_fk = 619
  AND pfile_fk NOT IN (SELECT agent_wc.pfile_fk FROM agent_wc)
  LIMIT 5000;

The "LIMIT 5000" ensures that this job does not hog all of the scheduler's
resources.
The "619" is an example -- it should match the upload_fk for the project
and be set by the Interface.
Assuming everything gets processed, this will return no rows when
everything is done processing.  That's how the scheduler will know that
there is no more work to perform.

Since this job should run on host-specific fields, the
jobqueue.jq_runonpfile should be set to "pfile".  This is the name of the
column from the SQL that denotes the host-specific information.


===========================================================
Part II: The Agent

There are two main ways to build the agent.  The main issue is around how
to access the database.

Option #1: Shell script.
The agent can be implemented as a shell script.  This is great for a quick
test agent, but not ideal for long-term or complicated agents.

To make it an agent, use the "engine-shell" agent as a wrapper.  The shell
will handle all of the scheduler communication.  For minimal DB access, you
can use the dbinit program (/usr/local/fossology/test.d/dbinit).  The
"wc_agent.sh" program uses this approach.

Option #2: Standalone program.
This is the more efficient method, and actually uses a programming
language, like "C", to implement the agent and communicate with the
database.  The "wc_agent.c" program uses this approach.


In both of these samples, the "pfile" supplied by the scheduler is
processed and results are stored in the agent_wc database table.


===========================================================
Part III: The Interface

[TBD]

===========================================================
Part IV: Build Process

When you have everything working, add it to the build process.
Edit trunk/Makefile and add your directory to the DIRS list.

