/* **************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
************************************************************** */

/* local includes */
#include <agent.h>
#include <database.h>
#include <event.h>
#include <host.h>
#include <interface.h>
#include <logging.h>
#include <scheduler.h>

/* library includes */
#include <libfossrepo.h>

/**
 * main function for FOSSology scheduler, does command line parsing,
 * Initialization and then simply enters the event loop.
 *
 * @param argc the command line argument cound
 * @param argv the command line argument values
 * @return if the program ran correctly
 */
int main(int argc, char** argv)
{
  /* locals */
  gboolean db_reset = FALSE;  // flag to reset the job queue upon database connection
  gboolean ki_kill  = FALSE;  // flag that indicates all schedulers should be forcibly shutdown
  gboolean ki_shut  = FALSE;  // flag that indicates all schedulers should be gracefully shutdown
  gboolean db_init  = FALSE;  // flag indicating a database test
  gboolean test_die = FALSE;  // flag to run the tests then die
  gboolean s_daemon = FALSE;  // falg to run the scheduler as a daemon
  gchar* logdir = NULL;       // used when a different log from the default is used
  GOptionContext* options;    // option context used for command line parsing
  GError* error = NULL;       // error object used during parsing
  uint16_t port = 0;
  gchar* sysconfigdir = DEFAULT_SETUP;

  /* THE SCHEDULER */
  scheduler_t* scheduler;

  if(getenv("FO_SYSCONFDIR") != NULL)
    sysconfigdir = getenv("FO_SYSCONFDIR");

  /* get this done first */
  srand(time(NULL));
#if !(GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32)
  g_thread_init(NULL);
#endif
#if !(GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 36)
  g_type_init();
#endif

  /* the options for the command line parser */
  GOptionEntry entries[] =
  {
      { "daemon",   'd', 0, G_OPTION_ARG_NONE,   &s_daemon,     "      Run scheduler as daemon"                       },
      { "database", 'i', 0, G_OPTION_ARG_NONE,   &db_init,      "      Initialize database connection and exit"       },
      { "kill",     'k', 0, G_OPTION_ARG_NONE,   &ki_kill,      "      Forcibly kills all running schedulers"         },
      { "shutdown", 's', 0, G_OPTION_ARG_NONE,   &ki_shut,      "      Gracefully shutdown of all running schedulers" },
      { "log",      'L', 0, G_OPTION_ARG_STRING, &logdir,       "[str] Specify location of log file"                  },
      { "port",     'p', 0, G_OPTION_ARG_INT,    &port,         "[num] Set the interface port"                        },
      { "reset",    'R', 0, G_OPTION_ARG_NONE,   &db_reset,     "      Reset the job queue upon startup"              },
      { "test",     't', 0, G_OPTION_ARG_NONE,   &test_die,     "      Close the scheduler after running tests"       },
      { "verbose",  'v', 0, G_OPTION_ARG_INT,    &verbose,      "[num] Set the scheduler verbose level"               },
      { "config",   'c', 0, G_OPTION_ARG_STRING, &sysconfigdir, "[str] Specify system configuration directory"        },
      {NULL}
  };

  /* ********************* */
  /* *** parse options *** */
  /* ********************* */
  options = g_option_context_new("- scheduler for FOSSology");
  g_option_context_add_main_entries(options, entries, NULL);
  g_option_context_parse(options, &argc, &argv, &error);

  if(error)
  {
    fprintf(stderr, "ERROR: %s\n", error->message);
    fprintf(stderr, "%s", g_option_context_get_help(options, FALSE, NULL));
    fflush(stderr);
    return -1;
  }

  g_option_context_free(options);

  /* check changes to the process first */
  if(ki_shut) { return kill_scheduler(FALSE); }
  if(ki_kill) { return kill_scheduler(TRUE);  }

  /* initialize the scheduler */
  scheduler = scheduler_init(sysconfigdir,
      log_new("stdout", "initializing", getpid()));

  if(logdir)
  {
    scheduler->logdir     = logdir;
    scheduler->logcmdline = TRUE;
    scheduler->main_log   = log_new(scheduler->logdir, NULL, scheduler->s_pid);

    log_destroy(main_log);
    main_log = scheduler->main_log;
  }

  scheduler->process_name = g_strdup(argv[0]);
  scheduler->s_daemon     = s_daemon;

  scheduler_foss_config(scheduler);
  if(s_daemon && scheduler_daemonize(scheduler) == -1) { return -1; }
  scheduler_agent_config(scheduler);

  database_init(scheduler);
  email_init(scheduler);

  NOTIFY("*****************************************************************");
  NOTIFY("***                FOSSology scheduler started                ***");
  NOTIFY("***        pid:      %-33d        ***", getpid());
  NOTIFY("***        verbose:  %-33d        ***", verbose);
  NOTIFY("***        config:   %-33s        ***", sysconfigdir);
  NOTIFY("*****************************************************************");

  interface_init(scheduler);
  fo_RepOpenFull(scheduler->sysconfig);

  signal(SIGCHLD, scheduler_sig_handle);
  signal(SIGTERM, scheduler_sig_handle);
  signal(SIGQUIT, scheduler_sig_handle);
  signal(SIGHUP,  scheduler_sig_handle);

  /* ***************************************************** */
  /* *** we have finished initialization without error *** */
  /* ***************************************************** */

  if(db_reset)
    database_reset_queue(scheduler);
  if(test_die)
    closing = 1;
  event_loop_enter(scheduler, scheduler_update, scheduler_signal);

  NOTIFY("*****************************************************************");
  NOTIFY("***                FOSSology scheduler closed                 ***");
  NOTIFY("***        pid:     %-34d        ***", scheduler->s_pid);
  NOTIFY("*****************************************************************\n");

  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
  return 0;
}
