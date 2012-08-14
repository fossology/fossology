/* **************************************************************
Copyright (C) 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.

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
    log_printf("ERROR %s.%d: %s\n",                                \
      __FILE__, __LINE__, error->message);                         \
    log_printf("ERROR %s.%d: ", __FILE__, __LINE__);               \
    log_printf(__VA_ARGS__);                                       \
    log_printf("\n");                                              \
    g_clear_error(&error);                                         \
    continue;                                                      \
  }

/* global flags */
int verbose = 0;
int closing = 0;

/* pointer to the main thread */
GThread* main_thread;

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
void scheduler_sig_handle(int signo)
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
void scheduler_signal(scheduler_t* scheduler)
{
  // the last time an update was run
  static time_t last_update = 0;

  // copy of the mask
  guint mask;

  /* this will get sigmask and set it to 0 */
#if __GNUC__
  mask = __sync_fetch_and_and(&sigmask, 0);
#else
  mask = sigmask;
  sigmask = 0;
#endif

  /* initialize last_update */
  if(last_update == 0)
    last_update = time(NULL);

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
      V_SCHED("SIGNALS: received sigchld for pid %d\n", n);
      pass = g_new0(pid_t, 2);
      pass[0] = n;
      pass[1] = status;
      event_signal(agent_death_event, pass);
    }
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
    scheduler_config_event(scheduler, NULL);
  }

  /* Finish by checking if an agent update needs to be performed.
   *
   * Every CONF_agent_update_interval, the agents and database should be
   * updated. The agents need to be updated to check for dead and unresponsive
   * agents. The database is updated to make sure that a new job hasn't been
   * scheduled without the scheduler being informed.
   */
  if((time(NULL) - last_update) > CONF_agent_update_interval )
  {
    V_SPECIAL("SIGNALS: Performing agent and database update\n");
    event_signal(agent_update_event, NULL);
    event_signal(database_update_event, NULL);
    last_update = time(NULL);
  }
}

/* ************************************************************************** */
/* **** The actual scheduler ************************************************ */
/* ************************************************************************** */

/**
 * @brief Create a new scheduler object.
 *
 * This will initalize everything to a point where it can be used. All regular
 * expressions, GTree's and the job_queue will be correctly created.
 *
 * @return a new scheduler_t* that can be further populated
 */
scheduler_t* scheduler_init(gchar* sysconfigdir, log_t* log)
{
  scheduler_t* ret = g_new0(scheduler_t, 1);

  ret->process_name  = NULL;
  ret->s_pid         = getpid();
  ret->s_daemon      = FALSE;
  ret->s_startup     = FALSE;
  ret->s_pause       = TRUE;

  ret->sysconfig     = NULL;
  ret->sysconfigdir  = g_strdup(sysconfigdir);
  ret->logdir        = LOG_DIR;
  ret->logcmdline    = FALSE;
  ret->main_log      = log;
  ret->host_queue    = NULL;

  ret->i_created     = FALSE;
  ret->i_terminate   = FALSE;
  ret->i_port        = 0;
  ret->server        = NULL;
  ret->workers       = NULL;
  ret->cancel        = NULL;

  ret->job_queue     = g_sequence_new(NULL);

  ret->db_conn       = NULL;
  ret->host_url      = NULL;
  ret->email_subject = NULL;
  ret->email_header  = NULL;
  ret->email_footer  = NULL;
  ret->email_command = NULL;

  /* This regex should find:
   *   1. One or more capital letters followed by a ':' followed by white space,
   *      followed by a number
   *   2. One or more capital letters followed by a ':' followed by white space,
   *      followed by a number, followed by white space, followed by a number
   *
   * Examples:
   *   HEART: 1 2   -> matches
   *   HEART: 1     -> matches
   *   HEART:       -> does not match
   *
   */
  ret->parse_agent_msg = g_regex_new(
      "([A-Z]+):([ \t]*)(\\d+)(([ \t]*)(\\d))?",
      0, 0, NULL);

  /* This regex should find:
   *   1. A '$' followed by any combination of capital letters or underscore
   *   2. A '$' followed by any combination of capital letters or underscore,
   *      followed by a '.' followed by alphabetic characters or underscore,
   *      followed by a '.' followed by alphabetic characters or underscore
   *
   * Examples:
   *   $HELLO             -> matches
   *   $SIMPLE_NAME       -> matches
   *   $DB.table.column   -> matches
   *   $bad               -> does not match
   *   $DB.table          -> does not match
   */
  ret->parse_db_email      = g_regex_new(
      "\\$([A-Z_]*)(\\.([a-zA-Z_]*)\\.([a-zA-Z_]*))?",
      0, 0, NULL);

  /* This regex should match:
   *   1. a set of alphabetical characters
   *   2. a set of alphabetical characters, followed by white space, followed by
   *      a number
   *   3. a set of alphabetical characters, followed by white space, followed by
   *      a number, followed by white space, followed by a string in quotes.
   *
   *
   * Examples:
   *   close                   -> matches
   *   stop                    -> matches
   *   pause 10                -> matches
   *   kill 10 "hello world"   -> matches
   *   pause 10 10             -> does not match
   *   kill "hello world" 10   -> does not match
   *
   *
   */
  ret->parse_interface_cmd = g_regex_new(
      "(\\w+)(\\s+(-?\\d+))?(\\s+((-?\\d+)|(\"(.*)\")))?",
      0, G_REGEX_MATCH_NEWLINE_LF, NULL);

  ret->meta_agents = g_tree_new_full(string_compare, NULL, NULL,
      (GDestroyNotify)meta_agent_destroy);
  ret->agents      = g_tree_new_full(int_compare,    NULL, NULL,
      (GDestroyNotify)agent_destroy);
  ret->host_list = g_tree_new_full(string_compare, NULL, NULL,
      (GDestroyNotify)host_destroy);
  ret->job_list     = g_tree_new_full(int_compare, NULL, NULL,
      (GDestroyNotify)job_destroy);

  main_log = log;

  return ret;
}

/**
 * @brief Free any memory associated with a scheduler_t.
 *
 * This will stop the interface if it is currently running, and free all the
 * memory associated with the different regular expression and similar
 * structures.
 *
 * @param scheduler
 */
void scheduler_destroy(scheduler_t* scheduler)
{
  // TODO interface close
  // TODO repo close

  event_loop_destroy();

  if(scheduler->main_log)
  {
    log_destroy(scheduler->main_log);
    main_log = NULL;
  }

  if(scheduler->process_name) g_free(scheduler->process_name);
  if(scheduler->sysconfig)    fo_config_free(scheduler->sysconfig);
  if(scheduler->sysconfigdir) g_free(scheduler->sysconfigdir);
  if(scheduler->host_queue)   g_list_free(scheduler->host_queue);
  if(scheduler->workers)      g_thread_pool_free(scheduler->workers, FALSE, TRUE);

  if(scheduler->email_subject) g_free(scheduler->email_subject);
  if(scheduler->email_command) g_free(scheduler->email_command);

  g_sequence_free(scheduler->job_queue);

  g_regex_unref(scheduler->parse_agent_msg);
  g_regex_unref(scheduler->parse_db_email);
  g_regex_unref(scheduler->parse_interface_cmd);

  g_tree_unref(scheduler->meta_agents);
  g_tree_unref(scheduler->agents);
  g_tree_unref(scheduler->host_list);
  g_tree_unref(scheduler->job_list);

  g_free(scheduler);
}

/**
 * @brief Update function called after every event
 *
 * The heart of the scheduler, the actual scheduling algorithm. This will be
 * passed to the event loop as a call back and will be called every time an event
 * is executed. Therefore the code should be light weight since it will be run
 * very frequently.
 *
 * @TODO:
 *   currently this will only grab a job and create a single agent to execute
 *   the job.
 *
 *   @TODO: allow for runonpfile jobs to have multiple agents based on size
 *   @TODO: allow for job preemption. The scheduler can pause jobs, allow it
 *   @TODO: allow for specific hosts to be chossen.
 */
void scheduler_update(scheduler_t* scheduler)
{
  /* queue used to hold jobs if an exclusive job enters the system */
  static job_t*  job  = NULL;
  static host_t* host = NULL;
  static int lockout = 0;

  /* locals */
  int n_agents = g_tree_nnodes(scheduler->agents);
  int n_jobs   = active_jobs(scheduler->job_list);

  /* check to see if we are in and can exit the startup state */
  if(scheduler->s_startup && n_agents == 0)
  {
    event_signal(database_update_event, NULL);
    scheduler->s_startup = 0;
  }

  /* check if we are able to close the scheduler */
  if(closing && n_agents == 0 && n_jobs == 0)
  {
    event_loop_terminate();
    return;
  }

  if(lockout && n_agents == 0 && n_jobs == 0)
    lockout = 0;

  if(job == NULL && !lockout)
  {
    while((job = peek_job(scheduler->job_queue)) != NULL)
    {
      // check if the agent is required to run on local host
      if(is_meta_special(
          g_tree_lookup(scheduler->meta_agents, job->agent_type), SAG_LOCAL))
      {
        host = g_tree_lookup(scheduler->host_list, LOCAL_HOST);
        if(!(host->running < host->max))
        {
          job = NULL;
          break;
        }
      }
      // check if the job is required to run on a specific machine
      else if((job->required_host != NULL))
      {
        host = g_tree_lookup(scheduler->host_list, job->required_host);
        if(!(host->running < host->max))
        {
          job = NULL;
          break;
        }
      }
      // the generic case, this can run anywhere, find a place
      else if((host = get_host(&(scheduler->host_queue), 1)) == NULL)
      {
        job = NULL;
        break;
      }

      next_job(scheduler->job_queue);
      if(is_meta_special(
          g_tree_lookup(scheduler->meta_agents, job->agent_type), SAG_EXCLUSIVE))
      {
        V_SCHED("JOB_INIT: exclusive, postponing initialization\n");
        break;
      }

      V_SCHED("Starting JOB[%d].%s\n", job->id, job->agent_type);
      agent_init(scheduler, host, job);
      job = NULL;
    }
  }

  if(job != NULL && n_agents == 0 && n_jobs == 0)
  {
    agent_init(scheduler, host, job);
    lockout = 1;
    job  = NULL;
    host = NULL;
  }

  if(scheduler->s_pause)
  {
    scheduler->s_startup = 1;
    scheduler->s_pause = 0;
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
void set_usr_grp(gchar* process_name, fo_conf* config)
{
  /* locals */
  struct group*  grp;
  struct passwd* pwd;

  char* group =
      fo_config_has_key(config, GU_HEADER, GU_GROUP) ?
      fo_config_get    (config, GU_HEADER, GU_GROUP, NULL) : PROJECT_GROUP;
  char* user  =
      fo_config_has_key(config, GU_HEADER, GU_USER)  ?
      fo_config_get    (config, GU_HEADER, GU_USER, NULL)  : PROJECT_USER;

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
  gchar f_name[FILENAME_MAX];
  struct dirent* ep;
  DIR* dp;
  FILE* file;
  gint num_killed = 0;
  pid_t s_pid = getpid();

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
 * @brief clears any information that is loaded when loading the configuration
 *
 * @param scheduler  the scheduler to reset the information on
 */
void scheduler_clear_config(scheduler_t* scheduler)
{
  g_tree_clear(scheduler->meta_agents);
  g_tree_clear(scheduler->host_list);

  g_list_free(scheduler->host_queue);
  scheduler->host_queue = NULL;

  g_free(scheduler->host_url);
  g_free(scheduler->email_subject);
  g_free(scheduler->email_command);
  PQfinish(scheduler->db_conn);
  scheduler->db_conn       = NULL;
  scheduler->host_url      = NULL;
  scheduler->email_subject = NULL;
  scheduler->email_command = NULL;

  if(scheduler->default_header)
    munmap(scheduler->email_header, strlen(scheduler->email_header));
  if(scheduler->default_footer)
    munmap(scheduler->email_footer, strlen(scheduler->email_footer));
  scheduler->email_header  = NULL;
  scheduler->email_footer  = NULL;

  fo_config_free(scheduler->sysconfig);
  scheduler->sysconfig = NULL;
}

/**
 * @brief GTraverseFunc used by g_tree_clear to collect all the keys in a tree
 *
 * @param key    the current key
 * @param value  the value mapped to the current key
 * @param data   a GList** that the key will be appended to
 * @return       Always returns 0
 */
static gboolean g_tree_collect(gpointer key, gpointer value, gpointer data)
{
  GList** ret = (GList**)data;

  *ret = g_list_append(*ret, key);

  return 0;
}

/**
 * @brief Clears the contents of a GTree
 *
 * @param tree  the tree to remove all elements from
 */
void g_tree_clear(GTree* tree)
{
  GList* keys = NULL;
  GList* iter = NULL;

  g_tree_foreach(tree, g_tree_collect, &keys);

  for(iter = keys; iter != NULL; iter = iter->next)
    g_tree_remove(tree, iter->data);

  g_list_free(keys);
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
void scheduler_agent_config(scheduler_t* scheduler)
{
  DIR* dp;                  // directory pointer used to load meta agents;
  struct dirent* ep;        // information about directory
  gchar* dirname;           // holds the name of the current configuration file
  uint8_t max = -1;             // the number of agents to a host or number of one type running
  uint32_t special = 0;          // anything that is special about the agent (EXCLUSIVE)
  int32_t i;
  gchar* name;
  gchar* cmd;
  gchar* tmp;
  GError* error = NULL;
  fo_conf* config;

  dirname = g_strdup_printf("%s/%s/", scheduler->sysconfigdir, AGENT_CONF);
  if((dp = opendir(dirname)) == NULL)
  {
    FATAL("Could not open agent config directory: %s", dirname);
    return;
  }
  g_free(dirname);

  /* load the configuration for the agents */
  while((ep = readdir(dp)) != NULL)
  {
    if(ep->d_name[0] != '.')
    {
      dirname = g_strdup_printf("%s/%s/%s/%s.conf",
          scheduler->sysconfigdir, AGENT_CONF, ep->d_name, ep->d_name);

      config = fo_config_load(dirname, &error);
      if(error && error->code == fo_missing_file)
      {
        V_SCHED("CONFIG: Could not find %s\n", dirname);
        g_clear_error(&error);
        continue;
      }
      TEST_ERROR(error, "no additional info");
      V_SCHED("CONFIG: loading config file %s\n", dirname);

      if(!fo_config_has_group(config, "default"))
      {
        log_printf("ERROR: %s must have a \"default\" group\n", dirname);
        log_printf("ERROR: cause by %s.%d\n", __FILE__, __LINE__);
        continue;
      }

      special = 0;
      name = ep->d_name;
      max = fo_config_list_length(config, "default", "special", &error);
      TEST_ERROR(error, "%s: the special key should be of type list", dirname);
      for(i = 0; i < max; i++)
      {
        cmd = fo_config_get_list(config, "default", "special", i, &error);
        TEST_ERROR(error, "%s: failed to load element %d of special list",
            dirname, i)

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
                dirname, name, cmd);
        }
      }

      cmd  = fo_config_get(config, "default", "command", &error);
      TEST_ERROR(error, "%s: the default group must have a command key", dirname);
      tmp  = fo_config_get(config, "default", "max", &error);
      TEST_ERROR(error, "%s: the default group must have a max key", dirname);

      if(!add_meta_agent(scheduler->meta_agents, name, cmd, (max = atoi(tmp)), special))
      {
        V_SCHED("CONFIG: could not create meta agent using %s\n", ep->d_name);
      }
      else if(TVERB_SCHED)
      {
        log_printf("CONFIG: added new agent\n");
        log_printf("    name = %s\n", name);
        log_printf(" command = %s\n", cmd);
        log_printf("     max = %d\n", max);
        log_printf(" special = %d\n", special);
      }

      g_free(dirname);
      fo_config_free(config);
    }
  }

  closedir(dp);
  event_signal(scheduler_test_agents, NULL);
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
void scheduler_foss_config(scheduler_t* scheduler)
{
  gchar*   tmp;                   // pointer into a string
  gchar**  keys;                  // list of host names grabbed from the config file
  int32_t  max = -1;              // the number of agents to a host or number of one type running
  int32_t  special = 0;           // anything that is special about the agent (EXCLUSIVE)
  gchar    addbuf[512];           // standard string buffer
  gchar    dirbuf[FILENAME_MAX];  // standard string buffer
  GError*  error = NULL;          // error return location
  int32_t  i;                     // indexing variable
  host_t*  host;                  // new hosts will be created in the loop
  fo_conf* version;               // information loaded from the version file

  if(scheduler->sysconfig != NULL)
    fo_config_free(scheduler->sysconfig);

  /* parse the config file */
  tmp = g_strdup_printf("%s/fossology.conf", scheduler->sysconfigdir);
  scheduler->sysconfig = fo_config_load(tmp, &error);
  if(error) FATAL("%s", error->message);
  g_free(tmp);

  /* set the user and group before proceeding */
  set_usr_grp(scheduler->process_name, scheduler->sysconfig);

  /* load the port setting */
  if(scheduler->i_port == 0)
    scheduler->i_port = atoi(fo_config_get(scheduler->sysconfig,
        "FOSSOLOGY", "port", &error));

  /* load the log directory */
  if(!scheduler->logcmdline)
  {
    if(fo_config_has_key(scheduler->sysconfig, "DIRECTORIES", "LOGDIR"))
      scheduler->logdir = fo_config_get(scheduler->sysconfig, "DIRECTORIES", "LOGDIR", &error);
    scheduler->main_log = log_new(scheduler->logdir, NULL, scheduler->s_pid);

    log_destroy(main_log);
    main_log = scheduler->main_log;
  }

  /* load the host settings */
  keys = fo_config_key_set(scheduler->sysconfig, "HOSTS", &special);
  for(i = 0; i < special; i++)
  {
    tmp = fo_config_get(scheduler->sysconfig, "HOSTS", keys[i], &error);
    if(error)
    {
      WARNING("%s\n", error->message);
      g_clear_error(&error);
      continue;
    }

    sscanf(tmp, "%s %s %d", addbuf, dirbuf, &max);
    host = host_init(keys[i], addbuf, dirbuf, max);
    host_insert(host, scheduler);
    if(TVERB_SCHED)
    {
      log_printf("CONFIG: added new host\n");
      log_printf("      name = %s\n", keys[i]);
      log_printf("   address = %s\n", addbuf);
      log_printf(" directory = %s\n", dirbuf);
      log_printf("       max = %d\n", max);
    }
  }

  if((tmp = fo_RepValidate(scheduler->sysconfig)) != NULL)
  {
    ERROR("configuration file failed repository validation");
    ERROR("The offending line: \"%s\"", tmp);
    g_free(tmp);
    exit(254);
  }

  /* load the version information */
  tmp = g_strdup_printf("%s/VERSION", scheduler->sysconfigdir);
  version = fo_config_load(tmp, &error);
  if(error) FATAL("%s", error->message);
  g_free(tmp);

  fo_config_join(scheduler->sysconfig, version, NULL);
  fo_config_free(version);

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
#define SELECT_CONF_INIT(type, name, l_op, w_op, val)                                  \
  if(fo_config_has_key(scheduler->sysconfig, "SCHEDULER", #name))                      \
    CONF_##name = l_op(fo_config_get(scheduler->sysconfig, "SCHEDULER", #name, NULL)); \
  V_SPECIAL("CONFIG: %s == " MK_STRING_LIT(w_op) "\n", #name, CONF_##name );
  CONF_VARIABLES_TYPES(SELECT_CONF_INIT)
#undef SELECT_CONF_INIT
}

/**
 * @brief daemonizes the scheduler
 *
 * This will make sure that the pid that is maintained in the scheduler struct
 * is correct during the daemonizing process.
 *
 * @param scheduler  the scheduler_t struct
 * @return  if the daemonizing was successful.
 */
int scheduler_daemonize(scheduler_t* scheduler)
{
	int ret = 0;

	/* daemonize the process */
	if((ret = daemon(0, 0)) != 0)
	  return ret;

	scheduler->s_pid = getpid();
	return ret;
}

/**
 * @brief Load both the fossology configuration and all the agent configurations
 *
 * @param scheduler  the scheduler to load the configuration for
 * @param unused     this can be called as an event
 */
void scheduler_config_event(scheduler_t* scheduler, void* unused)
{
  if(scheduler->sysconfig)
    scheduler_clear_config(scheduler);

  scheduler_foss_config(scheduler);
  scheduler_agent_config(scheduler);

  database_init(scheduler);
  email_init(scheduler);
}

/**
 * @brief Sets the closing flag and possibly kills all currently running agents
 *
 * This function will cause the scheduler to slowly shutdown. If killed is true
 * this is a quick, ungraceful shutdown.
 *
 * @param scheduler  the scheduler
 * @param killed     should the scheduler kill all currently executing agents
 *                   before exiting the event loop, or should it wait for them
 *                   to finished first.
 */
void scheduler_close_event(scheduler_t* scheduler, void* killed)
{
  closing = 1;

  if(killed) {
    kill_agents(scheduler);
  }
}

/**
 * @brief Event used when the scheduler tests the agents
 *
 * @param scheduler  the scheduler struct
 * @param unused
 */
void scheduler_test_agents(scheduler_t* scheduler, void* unused)
{
  scheduler->s_startup = TRUE;
  test_agents(scheduler);
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
