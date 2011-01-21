/* **************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
#include <event.h>
#include <interface.h>
#include <logging.h>
#include <scheduler.h>

/* std library includes */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* unix system includes */
#include <dirent.h>
#include <pthread.h>
#include <signal.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <unistd.h>
#include <pwd.h>
#include <grp.h>

/* glib includes */
#include <glib.h>
#include <gio/gio.h>

char* process_name = "fossology-scheduler";

#ifndef PROJECT_USER
#define PROJECT_USER "fossology"
#endif
#ifndef PROJECT_GROUP
#define PROJECT_GROUP "fossyology"
#endif

int verbose = 0;
int closing = 0;

/* ************************************************************************** */
/* **** signals and events ************************************************** */
/* ************************************************************************** */

/**
 * handle signals from the child process. This will only be called on a SIGCHLD
 * and will handle the effects of the death of the child process.
 *
 * @param signo
 * @param INFO
 * @param context
 */
void chld_sig(int signo)
{
  int idx;          // index of the next empty location in the pid list
  pid_t* pid_list;  // list of dead agent pid's
  pid_t n;          // the next pid that has died
  int status;       // status returned by waitpit()

  /* initialize memory */
  pid_list = (pid_t*)calloc(num_agents() + 1, sizeof(pid_t));
  idx = 0;

  /* get all of the dead children's pids */
  while((n = waitpid(-1, &status, WNOHANG)) > 0)
  {
    if(verbose > 1)
      lprintf("SIGNALS: received sigchld for pid %d\n", n);
    pid_list[idx++] = n;
  }

  event_signal(agent_death_event, pid_list);
}

/**
 * TODO
 *
 * @param signo
 * @param INFO
 * @param context
 */
void prnt_sig(int signo)
{
  switch(signo)
  {
    case SIGINT:
      lprintf("SIGNALS: Scheduler killed by interrupt signal, unclean death\n");
      kill_agents();
      event_loop_terminate();
      break;
    case SIGALRM:
      event_signal(agent_update_event, NULL);
      alarm(CHECK_TIME);
      break;
  }
}

/* ************************************************************************** */
/* **** main utility functions ********************************************** */
/* ************************************************************************** */

/**
 * Correctly set the project user and group. The fossology scheduler must run as
 * the user specified by PROJECT_USER and PROJECT_GROUP since the agents must be
 * able to connect to the database. This ensures that that happens correctly.
 */
void set_usr_grp()
{
  struct group*  grp;
  struct passwd* pwd;

  /* make sure group exists */
  grp = getgrnam(PROJECT_GROUP);
  if(!grp)
  {
    // TODO error message
  }

  /* set the project group */
  setgroups(1, &(grp->gr_gid));
  if((setgid(grp->gr_gid) != 0) || (setegid(grp->gr_gid) != 0))
  {
    fprintf(stderr, "FATAL %s.%d: %s must be run as root or %s\n", __FILE__, __LINE__, process_name, PROJECT_USER);
    fprintf(stderr, "FATAL Set group '%s' aborting due to error: %s\n", PROJECT_GROUP, strerror(errno));
    exit(-1);
  }

  /* run as project user */
  pwd = getpwnam(PROJECT_USER);
  if(!pwd)
  {
    fprintf(stderr, "FATAL %s.%d: user '%s' not found\n", __FILE__, __LINE__, PROJECT_USER);
    exit(-1);
  }

  /* run as correct user, not as root or any other user */
  if((setuid(pwd->pw_uid) != 0) || (seteuid(pwd->pw_uid) != 0))
  {
    fprintf(stderr, "FATAL %s.%d: %s must run this as %s\n", __FILE__, __LINE__, process_name, PROJECT_USER);
    fprintf(stderr, "FATAL SETUID aborting due to error: %s\n", strerror(errno));
    exit(-1);
  }
}

/**
 * TODO
 */
void load_config()
{
  DIR* dp;                  // directory pointer used to load meta agents;
  struct dirent* ep;        // information about directory
  FILE* istr;               // file pointer to agent.conf
  char* tmp;                // pointer into a string
  char buffer[2048];        // TODO
  char name[MAX_NAME + 1];  // TODO
  char cmd [MAX_CMD  + 1];  // TODO
  int max = -1;             // TODO
  int special = 0;          // TODO

  // TODO set this to location of agent.conf files
  if((dp = opendir("./agents/")) == NULL)
  {
    FATAL("Could not opend agent.conf directory");
  }

  while((ep = readdir(dp)) != NULL)
  {
    sprintf(buffer, "agents/%s", ep->d_name);
    if(ep->d_name[0] != '.' && (istr = fopen(buffer, "rb")) != NULL)
    {
      if(verbose > 1)
        lprintf("CONFIG: loading config file %s\n", buffer);

      /* initialize data */
      memset(buffer, '\0', sizeof(buffer));
      memset(name,   '\0', sizeof(name));
      memset(cmd,    '\0', sizeof(cmd));

      /* this is not actually a loop this is used */
      /* so that all error cases still cause the  */
      /* file to close                            */
      while(fgets(buffer, sizeof(buffer) - 1, istr) != NULL)
      {
        if(buffer[0] == '#');
        else if(strncmp(buffer, "name=", 5) == 0)
          strncpy(name, buffer + 5, strlen(buffer + 5) - 1);
        else if(strncmp(buffer, "command=", 8) == 0)
          strncpy(cmd, buffer + 8, strlen(buffer + 8) - 1);
        else if(strncmp(buffer, "max=", 4) == 0)
          max = atoi(buffer + 4);
        else if(strncmp(buffer, "special=", 6) == 0)
        {
          tmp = strtok(buffer, "=|") + 1;
          special = 0;

          while((tmp = strtok(NULL, "=|")) != NULL)
          {
            if(strncmp(tmp, "EXCLUSIVE", 9) == 0)
              special |= SAG_EXCLUSIVE;
            else if(strncmp(tmp, "NONE", 4) == 0)
            {
              special = SAG_NONE;
              break;
            }
          }
        }
        else
        {
          // TODO handle error case
        }
      }

      if(!add_meta_agent(name, cmd, max, special) && verbose > 1)
        lprintf("CONFIG: could not create meta agent using %s\n", ep->d_name);

      fclose(istr);
    }
  }

  closedir(dp);
}

/**
 * TODO
 *
 * @return
 */
int close_scheduler()
{
  job_list_clean();
  agent_list_clean();
  interface_destroy();
  return 0;
}

/**
 * TODO
 */
void update_scheduler()
{
  if(closing && num_agents() == 0 && num_jobs() == 0)
    event_loop_terminate();
}

/**
 * Utility function that enables the use of the strcmp function with a GTree.
 *
 * @param a The first string
 * @param b The second string
 * @param user_data unused in this function
 * @return integral value idicating the relatioship between the two strings
 */
gint string_compare(gconstpointer a, gconstpointer b, gpointer user_data)
{
  return strcmp((char*)a, (char*)b);
}

/**
 * Utility function that enable the agents to be stored in a GTree using
 * the PID of the associated process.
 *
 * @param a The pid of the first process
 * @param b The pid of the second process
 * @param user_data unused in this function
 * @return integral value idicating the relationship between the two pids
 */
gint int_compare(gconstpointer a, gconstpointer b, gpointer user_data)
{
  return *(int*)a - *(int*)b;
}

/* ************************************************************************** */
/* **** main types ********************************************************** */
/* ************************************************************************** */

/**
 *
 * @param app_name
 */
void usage(char* app_name)
{
  fprintf(stderr, "Usage: %s [options]\n", app_name);
  fprintf(stderr, "  options:\n");
  fprintf(stderr, "    -d :: Run as a daemon, causes init to own the process\n");
  fprintf(stderr, "    -h :: Print the usage for the program and exit\n");
  fprintf(stderr, "    -i :: Initialize the database and exit\n");
  fprintf(stderr, "    -L :: Print to this file instead of default log file\n");
  fprintf(stderr, "    -p :: set the port that the scheduler should listen on\n");
  fprintf(stderr, "    -t :: Test run every type of agent, then quit\n");
  fprintf(stderr, "    -T :: Test run every type of agent, then run normally\n");
  fprintf(stderr, "    -v :: set verbose to true, used for debugging\n");
}

/**
 * @brief start point for the scheduler
 *
 * TODO change this to be more informative
 * Usage: fossology-scheduler [options] < 'typed command
 *   options:\n");
 *     -d :: Run as a daemon, causes init to own the process
 *     -h :: Print the usage for the program and exit
 *     -i :: Initialize the database and exit
 *     -L :: Print to this file instead of default log file
 *     -t :: Test run every type of agent, then quit
 *     -T :: Test run every type of agent, then run normally
 *     -v :: set verbose to true, used for debugging
 *
 * @param argc
 * @param argv
 * @return
 */
int main(int argc, char** argv)
{
  /* locals */
  int c;              // used for parsing the arguments
  int run_daemon = 0; // flag to run the scheduler as a daemon
  int rc;             // destination of return status for system calls
  int test = 0;       // flag to run the tests before starting scheduler
  char buffer[2048];  // character buffer, used multiple times

  /* initialize memory */
  memset(buffer, '\0', sizeof(buffer));
  verbose = 0;

  /* ********************* */
  /* *** parse options *** */
  /* ********************* */
  while((c = getopt(argc, argv, "diL:ptTv:h")) != -1)
  {
    switch(c)
    {
      case 'd':
        run_daemon = 1;
        break;
      case 'i':
        // TODO initialize database and exit
        break;
      case 'L':
        set_log(optarg);
        break;
      case 'p':
        set_port(atoi(optarg));
        break;
      case 't':
        test = 2;
        break;
      case 'T':
        test = 1;
        break;
      case 'v':
        verbose = atoi(optarg);
        break;
      case 'h': default:
        usage(argv[0]);
        return -1;
    }
  }

  if((optind != argc - 1) && (optind != argc))
  {
    usage(argv[0]);
    return -1;
  }

  if(verbose)
  {

  }

  /* ********************** */
  /* *** handle options *** */
  /* ********************** */
  g_thread_init(NULL);
  g_type_init();
  set_usr_grp();
  interface_init();
  load_config();

  if(run_daemon)
  {
    // TODO old scheduler used log file to decide second arg?
    rc = daemon(0, 1);
    fclose(stdin);
  }

  /* ********************** */
  /* *** handle signals *** */
  /* ********************** */
  signal(SIGCHLD, chld_sig);
  signal(SIGALRM, prnt_sig);
  signal(SIGINT,  prnt_sig);

  /* ********************** */
  /* *** run any tests **** */
  /* ********************** */
  if(test > 0)
  {
    add_job(job_init("all", NULL, 0));
    test_agents();
    if(test > 1)
      closing = 1;
  }

  alarm(CHECK_TIME);
  event_loop_enter(update_scheduler);

  return close_scheduler();
}
