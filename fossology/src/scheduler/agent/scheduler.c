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
#include <libfossrepo.h>
#include <agent.h>
#include <database.h>
#include <event.h>
#include <host.h>
#include <interface.h>
#include <logging.h>
#include <scheduler.h>

#include <fossconfig.h>

/* std library includes */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* unix system includes */
#include <dirent.h>
#include <fcntl.h>
#include <signal.h>
#include <sys/mman.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <unistd.h>
#include <pwd.h>
#include <grp.h>

/* glib includes */
#include <glib.h>
#include <gio/gio.h>

#define AGENT_CONF "mods-enabled"
#ifndef PROCESS_NAME
#define PROCESS_NAME "fo_scheduler"
#endif

#define TEST_ERROR(...)                                            \
  if(error)                                                        \
  {                                                                \
    lprintf("ERROR %s.%d: %s\n",                                   \
      __FILE__, __LINE__, error->message);                         \
    lprintf("ERROR %s.%d: ", __FILE__, __LINE__);                  \
    lprintf(__VA_ARGS__);                                          \
    lprintf("\n");                                                 \
    error = NULL;                                                  \
    continue;                                                      \
  }                                                                \

/* global flags */
int verbose = 0;
int closing = 0;
int startup = 0;
int pause_f = 1;
int s_pid;
int s_daemon;
int s_port;
char* sysconfigdir;
fo_conf* sysconfig;
char* logdir;

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
  pid_t n;          // the next pid that has died
  pid_t* pass;
  int status;       // status returned by waitpit()

  /* get all of the dead children's pids */
  while((n = waitpid(-1, &status, WNOHANG)) > 0)
  {
    if(TVERB_SCHED)
      clprintf("SIGNALS: received sigchld for pid %d\n", n);
    pass = g_new0(pid_t, 2);
    pass[0] = n;
    pass[1] = status;
    event_signal(agent_death_event, pass);
  }
}

/**
 * Handles any signals sent to the scheduler that are not SIGCHLD.
 *
 * Currently Handles:
 *   SIGALRM: scheduler will run agent updates and database updates
 *   SIGTERM: scheduler will gracefully shut down
 *   SIGQUIT: scheduler will gracefully shut down
 *   SIGINT:  scheduler will gracefully shut down
 *   SIGHIP:  scheduler will reload configuration data
 *
 * @param signo the number of the signal that was sent
 */
void prnt_sig(int signo)
{
  switch(signo)
  {
    case SIGALRM:
      V_SCHED("SIGNALS: Scheduler received alarm signal, checking job states\n");
      event_signal(agent_update_event, NULL);
      event_signal(database_update_event, NULL);
      alarm(CHECK_TIME);
      break;
    case SIGTERM:
      V_SCHED("SIGNALS: Scheduler received terminate signal, shutting down scheduler\n");
      event_signal(scheduler_close_event, NULL);
      break;
    case SIGQUIT:
      V_SCHED("SIGNALS: Scheduler received quit signal, shutting down scheduler\n");
      event_signal(scheduler_close_event, NULL);
      break;
    case SIGINT:
      V_SCHED("SIGNALS: Scheduler received interrupt signal, shutting down scheduler\n");
      event_signal(scheduler_close_event, NULL);
      break;
    case SIGHUP:
      V_SCHED("SIGNALS: Scheduler received SGIHUP, reloading configuration data\n");
      load_config(NULL);
      break;
  }
}

/* ************************************************************************** */
/* **** The actual scheduler ************************************************ */
/* ************************************************************************** */

/**
 * The heart of the scheduler, the actual scheduling algorithm. This will be
 * passed to the event loop as a call back and will be called every time an event
 * is executed. Therefore the code should be light weight since it will be run
 * very frequently.
 *
 * TODO:
 *   currently this will only grab a job and create a single agent to execute
 *   the job.
 *
 *   TODO: allow for runonpfile jobs to have multiple agents based on size
 *   TODO: allow for job preemption. The scheduler can pause jobs, allow it
 *   TODO: allow for specific hosts to be chossen.
 */
void update_scheduler()
{
  /* queue used to hold jobs if an exclusive job enters the system */
  static job j = NULL;
  static int lockout = 0;

  /* locals */
  host machine = NULL;
  int n_agents = num_agents();
  int n_jobs   = active_jobs();

  if(startup && n_agents == 0)
  {
    event_signal(database_update_event, NULL);
    startup = 0;
  }

  if(closing && n_agents == 0 && n_jobs == 0)
  {
    event_loop_terminate();
    return;
  }

  if(lockout && n_agents == 0 && n_jobs == 0)
    lockout = 0;

  if(j == NULL && !lockout)
  {
    while(peek_job() != NULL)
    {
      if((machine = get_host(1)) == NULL)
        break;

      j = next_job();
      if(is_special(j->agent_type, SAG_EXCLUSIVE))
        break;

      V_SCHED("Starting JOB[%d].%s\n", j->id, j->agent_type);
      agent_init(machine, j);
      j = NULL;
    }
  }

  if(j != NULL && n_agents == 0 && n_jobs == 0)
  {
    agent_init(get_host(1), j);
    lockout = 1;
    j = NULL;
  }

  if(pause_f)
  {
    startup = 1;
    pause_f = 0;
  }
}

/* ************************************************************************** */
/* **** main utility functions ********************************************** */
/* ************************************************************************** */

#define GU_HEADER "DIRECTORIES"
#define GU_GROUP  "PROJECTGROUP"
#define GU_USER   "PROJECTUSER"

/**
 * Correctly set the project user and group. The fossology scheduler must run as
 * the user specified by PROJECT_USER and PROJECT_GROUP since the agents must be
 * able to connect to the database. This ensures that that happens correctly.
 */
void set_usr_grp()
{
  /* locals */
  struct group*  grp;
  struct passwd* pwd;

  char* group =
      fo_config_has_key(sysconfig, GU_HEADER, GU_GROUP) ?
      fo_config_get    (sysconfig, GU_HEADER, GU_GROUP, NULL) : PROJECT_GROUP;
  char* user  =
      fo_config_has_key(sysconfig, GU_HEADER, GU_USER)  ?
      fo_config_get    (sysconfig, GU_HEADER, GU_USER, NULL)  : PROJECT_USER;

  /* make sure group exists */
  grp = getgrnam(group);
  if(!grp)
  {
    // TODO error message
  }

  /* set the project group */
  setgroups(1, &(grp->gr_gid));
  if((setgid(grp->gr_gid) != 0) || (setegid(grp->gr_gid) != 0))
  {
    fprintf(stderr, "FATAL %s.%d: %s must be run as root or %s\n",
        __FILE__, __LINE__, PROCESS_NAME, user);
    fprintf(stderr, "FATAL Set group '%s' aborting due to error: %s\n",
        group, strerror(errno));
    exit(-1);
  }

  /* run as project user */
  pwd = getpwnam(user);
  if(!pwd)
  {
    fprintf(stderr, "FATAL %s.%d: user '%s' not found\n",
        __FILE__, __LINE__, user);
    exit(-1);
  }

  /* run as correct user, not as root or any other user */
  if((setuid(pwd->pw_uid) != 0) || (seteuid(pwd->pw_uid) != 0))
  {
    fprintf(stderr, "FATAL %s.%d: %s must run this as %s\n",
        __FILE__, __LINE__, PROCESS_NAME, user);
    fprintf(stderr, "FATAL SETUID aborting due to error: %s\n",
        strerror(errno));
    exit(-1);
  }
}

/**
 * @brief Kills all other running scheduler
 * @return 0 for success (i.e. a scheduler was killed), -1 for failure.
 *
 * This uses the /proc file system to find all processes that have fo_scheduler
 * in the name and sends a kill signal to them.
 */
int kill_scheduler()
{
  char f_name[FILENAME_MAX];
  struct dirent* ep;
  DIR* dp;
  FILE* file;
  int num_killed = 0;

  if((dp = opendir("/proc/")) == NULL)
  {
    fprintf(stderr, "ERROR %s.%d: Could not open /proc/ file system\n",
        __FILE__, __LINE__);
    exit(-1);
  }

  while((ep = readdir(dp)) != NULL)
  {
    if(string_is_num(ep->d_name))
    {
      snprintf(f_name, sizeof(f_name), "/proc/%s/cmdline", ep->d_name);
      if((file = fopen(f_name, "rt")))
      {
        if(fgets(f_name, sizeof(f_name), file) != NULL &&
            strstr(f_name, "fo_scheduler") && s_pid != atoi(ep->d_name))
        {
          lprintf("KILL: sending kill signal to pid %s\n", ep->d_name);
          kill(atoi(ep->d_name), SIGQUIT);
          num_killed++;
        }
      }
    }
  }

  if(num_killed == 0)
    return -1;
  return 0;
}

/**
 * TODO
 */
void load_agent_config()
{
  DIR* dp;                  // directory pointer used to load meta agents;
  struct dirent* ep;        // information about directory
  char addbuf[512];         // standard string buffer
  int max = -1;             // the number of agents to a host or number of one type running
  int special = 0;          // anything that is special about the agent (EXCLUSIVE)
  int i;
  char* name;
  char* cmd;
  char* tmp;
  GError* error = NULL;
  fo_conf* config;

  /* clear previous configurations */
  agent_list_clean();

  snprintf(addbuf, sizeof(addbuf), "%s/%s/", sysconfigdir, AGENT_CONF);
  if((dp = opendir(addbuf)) == NULL)
  {
    FATAL("Could not open agent config directory: %s", addbuf);
    return;
  }

  /* load the configuration for the agents */
  while((ep = readdir(dp)) != NULL)
  {
    if(ep->d_name[0] != '.')
    {
      snprintf(addbuf, sizeof(addbuf), "%s/%s/%s/%s.conf",
          sysconfigdir, AGENT_CONF, ep->d_name, ep->d_name);

      config = fo_config_load(addbuf, &error);
      if(error && error->code == fo_missing_file)
      {
        V_SCHED("CONFIG: Could not find %s\n", addbuf);
        g_error_free(error);
        error = NULL;
        continue;
      }
      TEST_ERROR("no additional info");
      V_SCHED("CONFIG: loading config file %s\n", addbuf);

      if(!fo_config_has_group(config, "default"))
      {
        lprintf("ERROR: %s must have a \"default\" group\n", addbuf);
        lprintf("ERROR: cause by %s.%d\n", __FILE__, __LINE__);
        error = NULL;
        continue;
      }

      special = 0;
      name = ep->d_name;
      max = fo_config_list_length(config, "default", "special", &error);
      TEST_ERROR("the special key should be of type list");
      for(i = 0; i < max; i++)
      {
        cmd = fo_config_get_list(config, "default", "special", i, &error);
        TEST_ERROR("failed to load element %d of special list", i)
        if(strncmp(cmd, "EXCLUSIVE", 9) == 0)
          special |= SAG_EXCLUSIVE;
        else if(strncmp(cmd, "NOEMAIL", 7) == 0)
          special |= SAG_NOEMAIL;
        else if(strlen(cmd) != 0)
          WARNING("Invalid special type for agent %s: %s", name, cmd);
      }

      cmd  = fo_config_get(config, "default", "command", &error);
      TEST_ERROR("the default group must have a command key");
      tmp  = fo_config_get(config, "default", "max", &error);
      TEST_ERROR("the default group must have a max key");

      if(!add_meta_agent(name, cmd, atoi(tmp), special))
      {
        V_SCHED("CONFIG: could not create meta agent using %s\n", ep->d_name);
      }
      else if(TVERB_SCHED)
      {
        lprintf("CONFIG: added new agent\n");
        lprintf("    name = %s\n", name);
        lprintf(" command = %s\n", cmd);
        lprintf("     max = %d\n", max);
        lprintf(" special = %d\n", special);
        lprintf("\"\n");
      }

      fo_config_free(config);
    }
  }
  closedir(dp);
  for_each_host(test_agents);
}

/**
 * TODO
 */
void load_foss_config()
{
  char* tmp;                // pointer into a string
  char** keys;
  int max = -1;             // the number of agents to a host or number of one type running
  int special = 0;          // anything that is special about the agent (EXCLUSIVE)
  char addbuf[512];         // standard string buffer
  char dirbuf[512];
  GError* error = NULL;
  int i;

  /* clear all previous configurations */
  host_list_clean();

  /* parse the config file */
  snprintf(addbuf, sizeof(addbuf), "%s/fossology.conf", sysconfigdir);
  sysconfig = fo_config_load(addbuf, &error);
  if(error)
    FATAL("%s", error->message);

  /* load the port setting */
  if(s_port < 0)
    s_port = atoi(fo_config_get(sysconfig, "FOSSOLOGY", "port", &error));
  set_port(s_port);

  /* load the host settings */
  keys = fo_config_key_set(sysconfig, "HOSTS", &special);
  for(i = 0; i < special; i++)
  {
    tmp = fo_config_get(sysconfig, "HOSTS", keys[i], &error);
    if(error)
    {
      lprintf(error->message);
      error = NULL;
      continue;
    }

    sscanf(tmp, "%s %s %d", addbuf, dirbuf, &max);
    host_init(keys[i], addbuf, dirbuf, max);
    if(TVERB_SCHED)
    {
      lprintf("CONFIG: added new host\n");
      lprintf("      name = %s\n", keys[i]);
      lprintf("   address = %s\n", addbuf);
      lprintf(" directory = %s\n", dirbuf);
      lprintf("       max = %d\n", max);
    }
  }
}

/**
 * TODO
 *
 * @param unused
 */
void load_config(void* unused)
{
  load_foss_config();
  load_agent_config();
}

/**
 * TODO
 *
 * @param unused
 */
void scheduler_close_event(void* unused)
{
  closing = 1;
  kill_agents();
}

/**
 * TODO
 *
 * @return
 */
int close_scheduler()
{
  job_list_clean();
  host_list_clean();
  agent_list_clean();
  interface_destroy();
  database_destroy();
  event_loop_destroy();
  fo_RepClose();
  return 0;
}

/**
 * Checks if a string is entirely composed of numeric characters
 *
 * @param str the string to test
 * @return TRUE if the string is entirely numeric, FALSE otherwise
 */
gint string_is_num(gchar* str)
{
  int len = strlen(str);
  int i;

  for(i = 0; i < len; i++)
    if(!isdigit(str[i]))
      return FALSE;
  return TRUE;
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
