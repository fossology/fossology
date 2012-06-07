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

#define TEST_ERROR(error, ...)                                     \
  if(error)                                                        \
  {                                                                \
    lprintf("ERROR %s.%d: %s\n",                                   \
      __FILE__, __LINE__, error->message);                         \
    lprintf("ERROR %s.%d: ", __FILE__, __LINE__);                  \
    lprintf(__VA_ARGS__);                                          \
    lprintf("\n");                                                 \
    g_clear_error(&error);                                         \
    continue;                                                      \
  }

/* global flags */
int verbose = 0;
int closing = 0;
int startup = 0;
int pause_f = 1;
int s_pid;
int s_daemon;
int s_port;
char* sysconfigdir = NULL;
fo_conf* sysconfig = NULL;
char* logdir;
char* process_name;

#define SELECT_DECLS(type, name, l_op, w_op, val) type CONF_##name = val;
CONF_VARIABLES_TYPES(SELECT_DECLS)
#undef SELECT_DECLS

/* ************************************************************************** */
/* **** signals and events ************************************************** */
/* ************************************************************************** */

#define MASK_SIGCHLD (1 << 0)
#define MASK_SIGALRM (1 << 1)
#define MASK_SIGTERM (1 << 2)
#define MASK_SIGQUIT (1 << 3)
#define MASK_SIGHUP  (1 << 4)

int sigmask = 0;

/**
 * Handles any signals sent to the scheduler that are not SIGCHLD.
 *
 * Currently Handles:
 *   SIGCHLD: scheduler will handle to death of the child process or agent
 *   SIGALRM: scheduler will run agent updates and database updates
 *   SIGTERM: scheduler will gracefully shut down
 *   SIGQUIT: scheduler will forcefully shut down
 *   SIGHIP:  scheduler will reload configuration data
 *
 * @param signo  the number of the signal that was sent
 */
void sig_handle(int signo)
{
  /* Anywhere you see a "#if __GNUC__" the code is checking if gcc is the
   * compiler. This is because the __sync... set of functions are the gcc
   * version of atomics.
   *
   * This means that if you aren't compiling with gcc, you can have a race
   * condition that results in a signal being lost during the
   * signal_scheduler() function.
   *
   * What could happen:
   *   1. signal_scheduler() reads value of sigmask
   *   2. scheduler receives a SIG**** and sets the correct bit in sigmask
   *   3. signal_scheduler() clears sigmask by setting it to 0
   *
   * In this set of events, a signal has been lost. If this is a sigchld this
   * could be very bad as a job could never get marked as finsihed.
   */
  switch(signo)
  {
#if __GNUC__
    case SIGCHLD: __sync_fetch_and_or(&sigmask, MASK_SIGCHLD); break;
    case SIGALRM: __sync_fetch_and_or(&sigmask, MASK_SIGALRM); break;
    case SIGTERM: __sync_fetch_and_or(&sigmask, MASK_SIGTERM); break;
    case SIGQUIT: __sync_fetch_and_or(&sigmask, MASK_SIGQUIT); break;
    case SIGHUP:  __sync_fetch_and_or(&sigmask, MASK_SIGHUP);  break;
#else
    case SIGCHLD: sigmask |= MASK_SIGCHLD; break;
    case SIGALRM: sigmask |= MASK_SIGALRM; break;
    case SIGTERM: sigmask |= MASK_SIGTERM; break;
    case SIGQUIT: sigmask |= MASK_SIGQUIT; break;
    case SIGHUP:  sigmask |= MASK_SIGHUP ; break;
#endif
  }
}

/**
 * @brief function that handles certain signals being delivered to the scheduler
 *
 * This function is called every time the event loop attempts to take something
 * from the event queue. It will also get called once a second regardless of if
 * a new event has been queued.
 *
 * This function checks the sigmask variable to check what signals have been
 * received since the last time it was called. The sigmask variable should
 * always be accessed atomically since it is accessed by the event loop thread
 * as well as the signal handlers.
 */
void signal_scheduler()
{
  guint mask;

  /* this will get sigmask and set it to 0 */
#if __GNUC__
  mask = __sync_fetch_and_and(&sigmask, 0);
#else
  mask = sigmask;
  sigmask = 0;
#endif

  /* signal: SIGCHLD
   *
   * A SIGCHLD has been received since the last time signal_scheduler() was
   * called. Get all agents that have finished since last this happened and
   * create an event for each.
   */
  if(mask & MASK_SIGCHLD)
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

  /* signal: SIGALRM
   *
   * A SIGALRM has been received since the last time signal_scheduler() was
   * called. Queue an agent_update_event and database_update_event. Set the
   * alarm to be called again.
   */
  if(mask & MASK_SIGALRM)
  {
    V_SPECIAL("SIGNALS: Scheduler received alarm signal, checking job states\n");
    event_signal(agent_update_event, NULL);
    event_signal(database_update_event, NULL);
    alarm(CONF_agent_update_interval);
  }

  /* signal: SIGTERM
   *
   * A SIGTERM has been received. simply set the closing flag to 1 so that the
   * scheduler will gracefully shutdown as all the agents finish running.
   */
  if(mask & MASK_SIGTERM)
  {
    V_SCHED("SIGNALS: Scheduler received terminate signal, shutting down gracefully\n");
    event_signal(scheduler_close_event, (void*)0);
  }

  /* signal: SIGQUIT
   *
   * A SIGQUIT has been received. Queue a scheduler_close_event so that the
   * scheduler will imediately stop running. This will cause all the agents to
   * be forcefully killed.
   */
  if(mask & MASK_SIGQUIT)
  {
    V_SCHED("SIGNALS: Scheduler received quit signal, shutting down scheduler\n");
    event_signal(scheduler_close_event, (void*)1);
  }

  /* signal: SIGHUP
   *
   * A SIGHUP has been received. reload the configuration files for the
   * scheduler. This will run here instead of being queued as an event.
   */
  if(mask & MASK_SIGHUP)
  {
    V_SCHED("SIGNALS: Scheduler received SGIHUP, reloading configuration data\n");
    load_config(NULL);
  }
}

/* ************************************************************************** */
/* **** The actual scheduler ************************************************ */
/* ************************************************************************** */

/**
 * @brief Update function called after every event
 *
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

  /* check to see if we are in and can exit the startup state */
  if(startup && n_agents == 0)
  {
    event_signal(database_update_event, NULL);
    startup = 0;
  }

  /* check if we are able to close the scheduler */
  if(closing && n_agents == 0 && n_jobs == 0)
  {
    event_loop_terminate();
    return;
  }

  if(lockout && n_agents == 0 && n_jobs == 0)
    lockout = 0;

  if(j == NULL && !lockout)
  {
    while((j = peek_job()) != NULL)
    {
      if(is_meta_special(j->agent_type, SAG_LOCAL))
      {
        machine = name_host(LOCAL_HOST);
        if(!(machine->running < machine->max))
          break;
      }
      else if((machine = get_host(1)) == NULL)
      {
        V_SCHED("JOB_INIT: could not find host\n");
        break;
      }

      next_job();
      if(is_meta_special(j->agent_type, SAG_EXCLUSIVE))
      {
        V_SCHED("JOB_INIT: exclusive, postponing initialization\n");
        break;
      }

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
    fprintf(stderr, "FATAL %s.%d: could not find group \"%s\"\n",
        __FILE__, __LINE__, group);
    fprintf(stderr, "FATAL set_usr_grp() aborting due to error: %s\n",
        strerror(errno));
    exit(-1);
  }

  /* set the project group */
  setgroups(1, &(grp->gr_gid));
  if((setgid(grp->gr_gid) != 0) || (setegid(grp->gr_gid) != 0))
  {
    fprintf(stderr, "FATAL %s.%d: %s must be run as root or %s\n",
        __FILE__, __LINE__, process_name, user);
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
        __FILE__, __LINE__, process_name, user);
    fprintf(stderr, "FATAL SETUID aborting due to error: %s\n",
        strerror(errno));
    exit(-1);
  }
}

/**
 * @brief Kills all other running scheduler
 * @param force  if the scheduler should shutdown gracefully
 * @return 0 for success (i.e. a scheduler was killed), -1 for failure.
 *
 * This uses the /proc file system to find all processes that have fo_scheduler
 * in the name and sends a kill signal to them.
 */
int kill_scheduler(int force)
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
          NOTIFY("KILL: send signal to process %s\n", ep->d_name);
          if(force)
            kill(atoi(ep->d_name), SIGQUIT);
          else
            kill(atoi(ep->d_name), SIGTERM);
          num_killed++;
        }

        fclose(file);
      }
    }
  }

  closedir(dp);

  if(num_killed == 0)
    return -1;
  return 0;
}

/**
 * @brief Loads a particular agents configuration file
 *
 * This loads and saves the results as a new meta_agent. This assumes that the
 * configuration file for the agent includes the following key/value pairs:
 *   1. command: the command that will be used to start the agent
 *   2. max: the maximum number of this agent that can run at once
 *   3. special: anything that is special about the agent
 */
void load_agent_config()
{
  DIR* dp;                  // directory pointer used to load meta agents;
  struct dirent* ep;        // information about directory
  char namebuf[512];        // holds the name of the current configuration file
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

  snprintf(namebuf, sizeof(namebuf), "%s/%s/", sysconfigdir, AGENT_CONF);
  if((dp = opendir(namebuf)) == NULL)
  {
    FATAL("Could not open agent config directory: %s", namebuf);
    return;
  }

  /* load the configuration for the agents */
  while((ep = readdir(dp)) != NULL)
  {
    if(ep->d_name[0] != '.')
    {
      snprintf(namebuf, sizeof(namebuf), "%s/%s/%s/%s.conf",
          sysconfigdir, AGENT_CONF, ep->d_name, ep->d_name);

      config = fo_config_load(namebuf, &error);
      if(error && error->code == fo_missing_file)
      {
        V_SCHED("CONFIG: Could not find %s\n", namebuf);
        g_clear_error(&error);
        continue;
      }
      TEST_ERROR(error, "no additional info");
      V_SCHED("CONFIG: loading config file %s\n", namebuf);

      if(!fo_config_has_group(config, "default"))
      {
        lprintf("ERROR: %s must have a \"default\" group\n", namebuf);
        lprintf("ERROR: cause by %s.%d\n", __FILE__, __LINE__);
        continue;
      }

      special = 0;
      name = ep->d_name;
      max = fo_config_list_length(config, "default", "special", &error);
      TEST_ERROR(error, "%s: the special key should be of type list", namebuf);
      for(i = 0; i < max; i++)
      {
        cmd = fo_config_get_list(config, "default", "special", i, &error);
        TEST_ERROR(error, "%s: failed to load element %d of special list",
            namebuf, i)

        if(cmd[0] != '\0') {
          if(strncmp(cmd, "EXCLUSIVE", 9) == 0)
            special |= SAG_EXCLUSIVE;
          else if(strncmp(cmd, "NOEMAIL", 7) == 0)
            special |= SAG_NOEMAIL;
          else if(strncmp(cmd, "NOKILL", 6) == 0)
            special |= SAG_NOKILL;
          else if(strncmp(cmd, "LOCAL", 6) == 0)
            special |= SAG_LOCAL;
          else if(strlen(cmd) != 0)
            WARNING("%s: Invalid special type for agent %s: %s",
                namebuf, name, cmd);
        }
      }

      cmd  = fo_config_get(config, "default", "command", &error);
      TEST_ERROR(error, "%s: the default group must have a command key", namebuf);
      tmp  = fo_config_get(config, "default", "max", &error);
      TEST_ERROR(error, "%s: the default group must have a max key", namebuf);

      if(!add_meta_agent(name, cmd, (max = atoi(tmp)), special))
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
      }

      fo_config_free(config);
    }
  }
  closedir(dp);
  for_each_host(test_agents);
}

/**
 * @brief Loads the configuration data from fossology.conf
 *
 * This assumes that fossology.conf contains the following key/value pairs:
 *   1. port: the port that the scheduler will listen on
 *   2. LOG_DIR: the directory that the log should be in
 *
 * There should be a group named HOSTS with all of the hosts listed as
 * key/value pairs under this category. For each of these hosts, the scheduler
 * will create a new host as an internal representation.
 */
void load_foss_config()
{
  char* tmp;                  // pointer into a string
  char** keys;                // list of host names grabbed from the config file
  int max = -1;               // the number of agents to a host or number of one type running
  int special = 0;            // anything that is special about the agent (EXCLUSIVE)
  char addbuf[512];           // standard string buffer
  char dirbuf[FILENAME_MAX];
  GError* error = NULL;
  int i;

  /* clear all previous configurations */
  host_list_clean();

  if(sysconfig != NULL)
    fo_config_free(sysconfig);

  /* parse the config file */
  snprintf(addbuf, sizeof(addbuf), "%s/fossology.conf", sysconfigdir);
  sysconfig = fo_config_load(addbuf, &error);
  if(error)
    FATAL("%s", error->message);

  /* load the port setting */
  if(s_port < 0)
    s_port = atoi(fo_config_get(sysconfig, "FOSSOLOGY", "port", &error));
  set_port(s_port);

  /* load the log directory */
  if(fo_config_has_key(sysconfig, "DIRECTORIES", "LOG_DIR"))
    logdir = fo_config_get(sysconfig, "DIRECTORIES", "LOG_DIR", &error);

  /* load the host settings */
  keys = fo_config_key_set(sysconfig, "HOSTS", &special);
  for(i = 0; i < special; i++)
  {
    tmp = fo_config_get(sysconfig, "HOSTS", keys[i], &error);
    if(error)
    {
      lprintf(error->message);
      g_clear_error(&error);
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

  /* This will create the load and the print command for the special
   * configuration variables. This uses the l_op operation to load the variable
   * from the file and the w_op variable to write the variable to the log file.
   *
   * example:
   *   if this is in the CONF_VARIABLES_TYPES():
   *
   *     apply(char*, test_variable, NOOP, %s, "hello")
   *
   *   this is generated:
   *
   *     if(fo_config_has_key(sysconfig, "SCHEDULER", "test_variable")
   *       CONF_test_variable = fo_config_get(sysconfig, "SCHEDULER",
   *           "test_variable", NULL);
   *     V_SPECIAL("CONFIG: %s == %s\n", "test_variable", CONF_test_variable);
   *
   */
#define SELECT_CONF_INIT(type, name, l_op, w_op, val)                          \
  if(fo_config_has_key(sysconfig, "SCHEDULER", #name))                         \
    CONF_##name = l_op(fo_config_get(sysconfig, "SCHEDULER", #name, NULL));    \
  V_SPECIAL("CONFIG: %s == " MK_STRING_LIT(w_op) "\n", #name, CONF_##name );
  CONF_VARIABLES_TYPES(SELECT_CONF_INIT)
#undef SELECT_CONF_INIT
}

/**
 * @brief Load both the fossology configuration and all the agent configurations
 *
 * @param unused  this can be called as an event
 */
void load_config(void* unused)
{
  load_foss_config();
  load_agent_config();
}

/**
 * @brief Sets the closing flag and possibly kills all currently running agents
 *
 * This function will cause the scheduler to slowly shutdown. If killed is true
 * this is a quick, ungraceful shutdown.
 *
 * @param killed  should the scheduler kill all currently executing agents
 *                before exiting the event loop, or should it wait for them
 *                to finished first.
 */
void scheduler_close_event(void* killed)
{
  closing = 1;

  if(killed) {
    kill_agents();
  }
}

/**
 * @brief cleanup any memory associated with the scheduler.
 *
 * @return always returns 0
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
