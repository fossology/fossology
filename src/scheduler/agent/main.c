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
  gboolean ki_sched = FALSE;  // flag that indicates that the scheduler will be killed after start
  gboolean db_init  = FALSE;  // flag indicating a database test
  gboolean test_die = FALSE;  // flag to run the tests then die
  char* log = NULL;           // used when a different log from the default is used
  GOptionContext* options;    // option context used for command line parsing
  GError* error = NULL;       // error object used during parsing

  sysconfigdir = DEFAULT_SETUP;
  logdir = LOG_DIR;

  /* the options for the command line parser */
  GOptionEntry entries[] =
  {
      { "daemon",   'd', 0, G_OPTION_ARG_NONE,   &s_daemon,     "Run scheduler as daemon"                     },
      { "database", 'i', 0, G_OPTION_ARG_NONE,   &db_init,      "Initialize database connection and exit"     },
      { "kill",     'k', 0, G_OPTION_ARG_NONE,   &ki_sched,     "Kills all running schedulers and exit"       },
      { "log",      'L', 0, G_OPTION_ARG_STRING, &log,          "Prints log here instead of default log file" },
      { "port",     'p', 0, G_OPTION_ARG_INT,    &s_port,       "Set the port the interface listens on"       },
      { "reset",    'R', 0, G_OPTION_ARG_NONE,   &db_reset,     "Reset the job queue upon startup"            },
      { "test",     't', 0, G_OPTION_ARG_NONE,   &test_die,     "Close the scheduler after running tests"     },
      { "verbose",  'v', 0, G_OPTION_ARG_INT,    &verbose,      "Set the scheduler verbose level"             },
      { "config",   'c', 0, G_OPTION_ARG_STRING, &sysconfigdir, "Set the system configuration directory"      },
      {NULL}
  };

  s_pid = getpid();
  s_daemon = FALSE;
  s_port = -1;

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

  /* perform pre-initialization checks */
  if(s_daemon && daemon(0, 0) == -1) { return -1; }
  if(db_init)     { database_init();  return 0; }
  if(ki_sched)    { return kill_scheduler(); }
  if(log != NULL) { set_log(log); }

  /* the proces's pid could have change */
  s_pid = getpid();

  NOTIFY("*****************************************************************");
  NOTIFY("***                FOSSology scheduler started                ***");
  NOTIFY("***        pid:     %-34d        ***", s_pid);
  NOTIFY("***        verbose: %-34d        ***", verbose);
  NOTIFY("***        config:  %-34s        ***", sysconfigdir);
  NOTIFY("*****************************************************************");

  /* create data structs, load config and set the user groups */
  agent_list_init();
  host_list_init();
  job_list_init();
  load_foss_config();
  set_usr_grp();

  /* ********************************** */
  /* *** do all the initializations *** */
  /* ******* order matters here ******* */
  /* ********************************** */
  g_thread_init(NULL);
  g_type_init();
  interface_init();
  database_init();
  load_agent_config();
  fo_RepOpenFull(sysconfig);

  signal(SIGCHLD, chld_sig);
  signal(SIGALRM, prnt_sig);
  signal(SIGTERM, prnt_sig);
  signal(SIGQUIT, prnt_sig);
  //signal(SIGSEGV, prnt_sig);
  signal(SIGHUP,  prnt_sig);

  /* *********************************** */
  /* *** post initialization checks **** */
  /* *********************************** */
  if(fo_config_has_key(sysconfig, "DIRECTORIES", "LOG_DIR"))
    logdir = fo_config_get(sysconfig, "DIRECTORIES", "LOG_DIR", &error);
  if(db_reset)
    database_reset_queue();
  if(test_die)
    closing = 1;

  /* *************************************** */
  /* *** enter the scheduler event loop **** */
  /* *************************************** */

  alarm(CHECK_TIME);
  event_loop_enter(update_scheduler);

  NOTIFY("*****************************************************************");
  NOTIFY("***                FOSSology scheduler closed                 ***");
  NOTIFY("***        pid:     %-34d        ***", s_pid);
  NOTIFY("*****************************************************************\n");

  return close_scheduler();
}
