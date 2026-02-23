/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file
 * \brief Contains agent related information
 * \todo Change the "<date> <time> scheduler ::" to "<date> <time> agent ::" for
 * some log messages
 */

/* local includes */
#include <agent.h>
#include <database.h>
#include <event.h>
#include <host.h>
#include <job.h>
#include <logging.h>
#include <scheduler.h>

/* library includes */
#include <limits.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

/* unix library includes */
#include <fcntl.h>
#include <limits.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <unistd.h>

/* other library includes */
#include <glib.h>

/**
 * \brief Test if paramater is NULL
 *
 * If parameter is NULL, set the errno, write the error and return void.
 * \param a Object to check
 */
#define TEST_NULV(a) if(!a) { \
  errno = EINVAL; ERROR("agent passed is NULL, cannot proceed"); return; }
/**
 * \brief Test if paramater is NULL
 *
 * Like TEST_NULV(), returns ret instead of void.
 * \param a Object to check
 * \param ret Value to return if a is NULL
 * \sa TEST_NULV()
 */
#define TEST_NULL(a, ret) if(!a) { \
  errno = EINVAL; ERROR("agent passed is NULL, cannot proceed"); return ret; }

/** Prints the credential of the agent (null-safe) */
#define AGENT_CREDENTIAL do {                               \
  if(agent && agent->owner)                                 \
    log_printf("JOB[%d].%s[%d.%s]: ", agent->owner->id,     \
        agent->type ? agent->type->name : "?",              \
        agent->pid, agent->host ? agent->host->name : "?"); \
  else                                                      \
    log_printf("AGENT[%s][pid=%d][host=%s]: ",              \
        agent && agent->type ? agent->type->name : "?",     \
        agent ? agent->pid : -1,                            \
        agent && agent->host ? agent->host->name : "?");    \
} while(0)

/** Prints the credential to the agent log (null-safe) */
#define AGENT_LOG_CREDENTIAL do {                                \
  if(agent && agent->owner)                                      \
    con_printf(job_log(agent->owner), "JOB[%d].%s[%d.%s]: ",     \
        agent->owner->id, agent->type ? agent->type->name : "?", \
        agent->pid, agent->host ? agent->host->name : "?");      \
} while(0)

/** ERROR macro specifically for agents */
#define AGENT_ERROR(...) do {                       \
  log_printf("ERROR: %s.%d: ", __FILE__, __LINE__); \
  AGENT_CREDENTIAL;                                 \
  log_printf(__VA_ARGS__);                          \
  log_printf("\n"); } while(0)

/** NOTIFY macro specifically for agents */
#define AGENT_NOTIFY(...) if(TEST_NOTIFY) do { \
  log_printf("NOTE: ");                           \
  AGENT_CREDENTIAL;                            \
  log_printf(__VA_ARGS__);                        \
  log_printf("\n"); } while(0)

/** WARNING macro specifically for agents */
#define AGENT_WARNING(...) if(TEST_WARNING) do {      \
  log_printf("WARNING %s.%d: ", __FILE__, __LINE__);  \
  AGENT_CREDENTIAL;                                   \
  log_printf(__VA_ARGS__);                            \
  log_printf("\n"); } while(0)

/** STANDARD verbose macro changed for agents */
#define AGENT_SEQUENTIAL_PRINT(...) if(TVERB_AGENT) do { \
  AGENT_CREDENTIAL;                          \
  log_printf(__VA_ARGS__); } while(0)

/** Send logging specifically to the agent log file */
#define AGENT_CONCURRENT_PRINT(...) do {                             \
  AGENT_LOG_CREDENTIAL;                                 \
  con_printf(job_log(agent->owner), __VA_ARGS__); } while(0)

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * Array of C-Strings used to pretty-print the agent status in the log file.
 * Uses the X-Macro defined in @link agent.h
 */
#define SELECT_STRING(passed) MK_STRING_LIT(AGENT_##passed),
const char* agent_status_strings[] =
{ AGENT_STATUS_TYPES(SELECT_STRING) };
#undef SELECT_STRING

/* ************************************************************************** */
/* **** Local Functions ***************************************************** */
/* ************************************************************************** */

/**
 * @brief This will close all of the agent's pipes
 *
 * This function will be called by g_tree_foreach() which is the reason for its
 * formatting.
 *
 * @param pid_ptr   the key that was used to store this agent
 * @param agent     the agent that is being closed
 * @param excepted  this is an agent we don't want to close, this is it
 * @return always returns 0 to indicate that the traversal should continue
 */
static int agent_close_fd(int* pid_ptr, agent_t* agent, agent_t* excepted)
{
  TEST_NULL(agent, 0);
  if (agent != excepted)
  {
    close(agent->from_child);
    close(agent->to_child);
    fclose(agent->read);
    fclose(agent->write);
  }
  return 0;
}

/**
 * Check the status and check in time of an agent.
 *   - If we haven't gotten a recent communication, close it
 *   - If it hasn't been performing tasks, close it
 *
 * @param pid_ptr  pointer to key in g_tree, is not used in this function
 * @param agent    the agent that needs to be updated
 * @param unused   data that is also not used in this function
 * @return always returns 0 to indicate that the traversal should continue
 */
static int update(int* pid_ptr, agent_t* agent, gpointer unused)
{
  TEST_NULL(agent, 0);
  if (agent->owner == NULL)
  {
    log_printf("ERROR %s.%d: Agent pid %d has no owner; killing to prevent NULL deref\n", __FILE__, __LINE__, agent->pid);
    agent_kill(agent);
    return 0;
  }
  int nokill = is_agent_special(agent, SAG_NOKILL) || is_meta_special(agent->type, SAG_NOKILL);

  if (agent->status == AG_SPAWNED || agent->status == AG_RUNNING || agent->status == AG_PAUSED)
  {
    /* check last checkin time */
    if (time(NULL) - agent->check_in > CONF_agent_death_timer && !(agent->owner->status == JB_PAUSED) && !nokill)
    {
      AGENT_CONCURRENT_PRINT("no heartbeat for %d seconds\n", (time(NULL) - agent->check_in));
      agent_kill(agent);
      return 0;
    }

    /* check items processed */
    if (agent->status != AG_PAUSED && !agent->alive)
    {
      agent->n_updates++;
    }
    else
    {
      agent->n_updates = 0;
    }
    if (agent->n_updates > CONF_agent_update_number && !nokill)
    {
      AGENT_CONCURRENT_PRINT("agent has not set the alive flag in at least 10 minutes, killing\n");
      agent_kill(agent);
      return 0;
    }

    AGENT_SEQUENTIAL_PRINT("agent updated correctly, processed %d items: %d\n", agent->total_analyzed,
        agent->n_updates);
    agent->alive = 0;
  }

  return 0;
}

/**
 * @brief GTraversalFunction that kills all of the agents.
 *
 * This is used for an unclean death since all of the child processes will
 * be sent a kill signal instead of existing cleanly.
 *
 * @param pid the process id associated with the agent
 * @param agent a pointer to the information associated with an agent
 * @param unused
 * @return always returns 0 to indicate that the traversal should continue
 */
static int agent_kill_traverse(int* pid, agent_t* agent, gpointer unused)
{
  agent_kill(agent);
  return FALSE;
}

/**
 * @brief GTraverseFunction that will print the name of every agent in
 * alphabetical order separated by spaces.
 *
 * @param name the name of the agent
 * @param ma the meta_agents structure associated with the specific name
 * @param ostr the output stream to write the data to, socket in this case
 * @return always returns 0 to indicate that the traversal should continue
 */
static int agent_list(char* name, meta_agent_t* ma, GOutputStream* ostr)
{
  if (ma->valid)
  {
    g_output_stream_write(ostr, name, strlen(name), NULL, NULL);
    g_output_stream_write(ostr, " ", 1, NULL, NULL);
  }
  return FALSE;
}

/**
 * @brief GTraversalFunction that tests the current agent on every host
 *
 * This will traverse the list of hosts and start an agent that is of the type
 * of the current meta agent on every host.
 *
 * @param name       The name of the meta agent
 * @param ma         The meta_agent structure needed for agent creation
 * @param scheduler  The scheduler object to test the agents on
 * @return           Always return 0
 */
static int agent_test(const gchar* name, meta_agent_t* ma, scheduler_t* scheduler)
{
  static int32_t id_gen = -1;

  GList* iter;
  host_t* host;
  char *jq_cmd_args = 0;

  for (iter = scheduler->host_queue; iter != NULL; iter = iter->next)
  {
    host = (host_t*) iter->data;
    V_AGENT("META_AGENT[%s] testing on HOST[%s]\n", ma->name, host->name);
    job_t* job = job_init(scheduler->job_list, scheduler->job_queue, ma->name, host->name, id_gen--, 0, 0, 0, 0, jq_cmd_args);
    agent_init(scheduler, host, job);
  }

  return 0;
}

/**
 * Main function used for agent communication. This is where the communication
 * thread will spend the majority of its time.
 *
 * @param scheduler Pointer to scheduler interface
 * @param agent a the agent that will be listened on
 */
static void agent_listen(scheduler_t* scheduler, agent_t* agent)
{
  /* static locals */
#if GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32
  static GMutex version_lock;
#else
  static GStaticMutex version_lock = G_STATIC_MUTEX_INIT;
#endif

  /* locals */
  char buffer[1024]; // buffer to store c strings read from agent
  GMatchInfo* match; // regex match information
  char* arg;         // used during regex retrievals
  int relevant;      // used during special retrievals

  TEST_NULV(agent);

  /**
   * Start by getting the version information from the agent. The agent should
   * send "VERSION: <string>" where the string is the version information. there
   * are five things that can happen here.
   *   -# the agent sends correct version information   => continue
   *   -# this is the first agent to send version info  => save version and continue
   *   -# the agent sends incorrect version information => invalidate the agent
   *   -# the agent doesn't send version information    => invalidate the agent
   *   -# the agent crashed before sending information  => close the thread
   */
  if (fgets(buffer, sizeof(buffer), agent->read) == NULL)
  {
    AGENT_CONCURRENT_PRINT("pipe from child closed: %s\n", strerror(errno));
    g_thread_exit(NULL);
  }

  /* check to make sure "VERSION" was sent */
  buffer[strlen(buffer) - 1] = '\0';
  if (strncmp(buffer, "VERSION: ", 9) != 0)
  {
    if (strncmp(buffer, "@@@1", 4) == 0)
    {
      THREAD_FATAL(job_log(agent->owner), "agent crashed before sending version information");
    }
    else
    {
      agent->type->valid = 0;
      agent_fail_event(scheduler, agent);
      agent_kill(agent);
      con_printf(main_log, "ERROR %s.%d: agent %s.%s has been invalidated, removing from agents\n", __FILE__, __LINE__,
          agent->host->name, agent->type->name);
      AGENT_CONCURRENT_PRINT("agent didn't send version information: \"%s\"\n", buffer);
      return;
    }
  }

  /* check that the VERSION information is correct */
#if GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32
  g_mutex_lock(&version_lock);
#else
  g_static_mutex_lock(&version_lock);
#endif
  strcpy(buffer, &buffer[9]);
  if (agent->type->version == NULL && agent->type->valid)
  {
    agent->type->version_source = agent->host->name;
    agent->type->version = g_strdup(buffer);
    if (TVERB_AGENT)
      con_printf(main_log, "META_AGENT[%s.%s] version is: \"%s\"\n", agent->host->name, agent->type->name,
          agent->type->version);
  }
  else if (strcmp(agent->type->version, buffer) != 0)
  {
    con_printf(job_log(agent->owner), "ERROR %s.%d: META_DATA[%s] invalid agent spawn check\n", __FILE__, __LINE__,
        agent->type->name);
    con_printf(job_log(agent->owner), "ERROR: versions don't match: %s(%s) != received: %s(%s)\n",
        agent->type->version_source, agent->type->version, agent->host->name, buffer);
    agent->type->valid = 0;
    agent_kill(agent);
#if GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32
    g_mutex_unlock(&version_lock);
#else
    g_static_mutex_unlock(&version_lock);
#endif
    return;
  }
#if GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32
  g_mutex_unlock(&version_lock);
#else
  g_static_mutex_unlock(&version_lock);
#endif

  /*!
   * If we reach here the agent has correctly sent VERION information to the
   * scheduler. The agent now enters a listening loop. The communication thread
   * will wait for input from the agent, and act according to the agents current
   * state and what was sent
   *
   * \note any command prepended by "@@@" is a message from the scheduler to the
   *       communication thread, not from the agent.
   */
  while (1)
  {
    /* get message from agent */
    if (fgets(buffer, sizeof(buffer), agent->read) == NULL)
      g_thread_exit(NULL);

    buffer[strlen(buffer) - 1] = '\0';

    if (strlen(buffer) == 0)
      continue;

    if (TVERB_AGENT && (TVERB_SPECIAL || strncmp(buffer, "SPECIAL", 7) != 0))
      AGENT_CONCURRENT_PRINT("received: \"%s\"\n", buffer);

    /*! - \b command: "BYE"
     *
     *    The agent has finished processing all of the data from the relevant job.
     *    This command is follow by a return code. 0 indicates that it completed
     *    correctly, anything else can be used as an error code. Regardless of
     *    whether the agent completed, the communication thread will shutdown.
     */
    if (strncmp(buffer, "BYE", 3) == 0)
    {
      if ((agent->return_code = atoi(&(buffer[4]))) != 0)
      {
        AGENT_CONCURRENT_PRINT("agent failed with error code %d\n", agent->return_code);
        event_signal(agent_fail_event, agent);
      }
      break;
    }

    /*! - \b command "@@@1"
     *
     *    The scheduler needs the communication thread to shutdown. This will
     *    normally only happen if the agent crashes and the scheduler receives a
     *    SIGCHLD for it before it sends "BYE #".
     */
    if (strncmp(buffer, "@@@1", 4) == 0)
      break;

    /*! - \b command "@@@0"
     *
     *    The scheduler has updated the data that the agent should be processing.
     *    This is sent after an agent sends the "OK" command, and the scheduler has
     *    processed the resulting agent_ready_event().
     */
    if (strncmp(buffer, "@@@0", 4) == 0 && agent->updated)
    {
      aprintf(agent, "%s\n", agent->data);
      aprintf(agent, "END\n");
      fflush(agent->write);
      agent->updated = 0;
      continue;
    }

    /* agent just checked in */
    agent->check_in = time(NULL);

    /*! - \b command: "OK"
     *
     *    The agent is ready for data. This is sent it 2 situations:
     *        -# the agent has completed startup and is ready for the first part of
     *           the data that needs to be analyzed for the job
     *        -# the agent has finished the last piece of the job it was working on
     *           and is ready for the next piece or to be shutdown
     */
    if (strncmp(buffer, "OK", 2) == 0)
    {
      if (agent->status != AG_PAUSED)
        event_signal(agent_ready_event, agent);
    }

    /*! - \b command: "HEART"
     *
     *    Given the size of jobs that can be processed by FOSSology, agents can
     *    take an extremely long period of time to finish. To make sure that an
     *    agent is still working it must periodically update the scheduler with
     *    how much of the job it has processed.
     */
    else if (strncmp(buffer, "HEART", 5) == 0)
    {
      g_regex_match(scheduler->parse_agent_msg, buffer, 0, &match);

      arg = g_match_info_fetch(match, 3);
      agent->total_analyzed = atoi(arg);
      g_free(arg);

      arg = g_match_info_fetch(match, 6);
      agent->alive = (arg[0] == '1' || agent->alive);
      g_free(arg);

      g_match_info_free(match);
      match = NULL;

      database_job_processed(agent->owner->id, agent->total_analyzed);
    }

    /*! - \b command: "EMAIL"
     *
     *    Agents have the ability to set the message that will be sent with the
     *    notification email. This grabs the message and sets inside the job that
     *    the agent is running under.
     */
    else if (strncmp(buffer, "EMAIL", 5) == 0)
    {
      agent->owner->message = g_strdup(buffer + 6);
    }

    /*! - \b command: "SPECIAL"
     *
     *    Agents can set special attributes that change how it is treated during
     *    execution. This grabs the command and whether it is being set to true
     *    or false. Agents use this by calling fo_scheduler_set_special() in the
     *    agent api.
     */
    else if (strncmp(buffer, "SPECIAL", 7) == 0)
    {
      relevant = INT_MAX;

      g_regex_match(scheduler->parse_agent_msg, buffer, 0, &match);

      arg = g_match_info_fetch(match, 3);
      relevant &= atoi(arg);
      g_free(arg);

      arg = g_match_info_fetch(match, 6);
      if (atoi(arg))
      {
        if (agent->special & relevant)
          relevant = 0;
      }
      else
      {
        if (!(agent->special & relevant))
          relevant = 0;
      }
      g_free(arg);

      g_match_info_free(match);

      agent->special ^= relevant;
    }

    /*! - \b command: GETSPECIAL
     *
     *    The agent has requested the value of a special attribute. The scheduler
     *    will respond with the value of the special attribute.
     */
    else if (strncmp(buffer, "GETSPECIAL", 10) == 0)
    {
      g_regex_match(scheduler->parse_agent_msg, buffer, 0, &match);

      arg = g_match_info_fetch(match, 3);
      relevant = atoi(arg);
      g_free(arg);

      if (agent->special & relevant)
        aprintf(agent, "VALUE: 1\n");
      else
        aprintf(agent, "VALUE: 0\n");

      g_match_info_free(match);
    }

    /*! - \b command: unknown
     *
     *    The agent didn't use a legal command. This will simply put what the agent
     *    printed into the log and move on.
     */
    else if (!(TVERB_AGENT))
      AGENT_CONCURRENT_PRINT("\"%s\"\n", buffer);
  }

  if (TVERB_AGENT)
    AGENT_CONCURRENT_PRINT("communication thread closing\n");
}

/**
 * @brief Parses the shell command that is found in the configuration file.
 *
 * @param confdir  The configuration directory for FOSSology
 * @param user_id  The id of the user that created the job
 * @param group_id The id of the group that created the job
 * @param input    The command line that was in the agent configuration file
 * @param jq_cmd_args Extra parameters required by agent (if any)
 * @param jobId    Job id (from db)
 * @param[out] argc     Returns the number of arguments parsed
 * @param[out] argv     The parsed arguments
 */
static void shell_parse(char* confdir, int user_id, int group_id, char* input, char *jq_cmd_args, int jobId, int* argc, char*** argv)
{
  char* begin;
  char* curr;
  int idx = 0;
#define MAX_CMD_ARGS 30

  *argv = g_new0(char*, MAX_CMD_ARGS);
  begin = NULL;

  for (curr = input; *curr; curr++)
  {
    if (*curr == ' ')
    {
      if (begin == NULL)
        continue;

      if (*begin == '"')
        continue;

      *curr = '\0';
      (*argv)[idx++] = g_strdup(begin);
      begin = NULL;
    }
    else if (begin == NULL)
    {
      begin = curr;
    }
    else if (*begin == '"' && *curr == '"')
    {
      *begin = '\0';
      *curr = '\0';

      (*argv)[idx++] = g_strdup(begin + 1);
      begin = NULL;
    }
    if (idx > MAX_CMD_ARGS - 7)
      break;
  }

  (*argv)[idx++] = g_strdup_printf("--jobId=%d", jobId);
  (*argv)[idx++] = g_strdup_printf("--config=%s", confdir);
  (*argv)[idx++] = g_strdup_printf("--userID=%d", user_id);
  (*argv)[idx++] = g_strdup_printf("--groupID=%d", group_id);
  (*argv)[idx++] = "--scheduler_start";
  if (jq_cmd_args)
  {
    const char *start = jq_cmd_args;
    const char *current = jq_cmd_args;
    gboolean in_quotes = FALSE;

    while (*current != '\0')
    {
      if (*current == '\'' || *current == '"')
        in_quotes = !in_quotes;
      else if (*current == ' ' && !in_quotes)
      {
        if (current > start)
        {
          int len = current - start;
          char *arg = g_strndup(start, len);
          (*argv)[idx++] = arg;
        }
        start = current + 1;
      }
      current++;
    }

    if (current > start)
    {
      char *arg = g_strndup(start, current - start);
      (*argv)[idx++] = arg;
    }
  }
  (*argc) = idx;
}

/**
 * For the agent_spawn() function.
 */
typedef struct
{
  scheduler_t* scheduler; ///< Reference to current scheduler state
  agent_t* agent;         ///< Reference to current agent state
} agent_spawn_args;

/**
 * @brief Spawns a new agent using the command passed in using the meta agent.
 *
 * This function will call the fork and exec necessary to create a new agent.
 * As a result what this function does will change depending on if it is running
 * in the child or the parent.
 *
 * @b child:
 *   Will duplicate the stdin, stdout, and stderr pipes for printing to the
 *   scheduler, parse the command line options for the agent and start the
 *   agent. It will then call exec to start the new agent process
 *
 * @b parent:
 *   This will enter the listen function, and wait for information from the
 *   child, either as a failure or as an update for the information being
 *   analyzed
 *
 * @param pass  a pointer to scheduler_t and the new agent_t
 */
static void* agent_spawn(agent_spawn_args* pass)
{
  /* locals */
  scheduler_t* scheduler = pass->scheduler;
  agent_t* agent = pass->agent;
  gchar* tmp;                 // pointer to temporary string
  gchar** args;               // the arguments that will be passed to the child
  int argc;                   // the number of arguments parsed
  int len;
  char buffer[2048];          // character buffer

  /* spawn the new process */
  if (agent->owner == NULL)
  {
    log_printf("ERROR %s.%d: Agent spawn requested but agent has no owner; aborting spawn.\n", __FILE__, __LINE__);

    /* Close FILE* streams if they were opened by agent_init(). */
    if (agent->read)  { fclose(agent->read);  agent->read = NULL; agent->from_child = -1; }
    if (agent->write) { fclose(agent->write); agent->write = NULL; agent->to_child   = -1; }

    /* from_child/to_child were already closed via fclose() above. */
    if (agent->from_parent >= 0) { close(agent->from_parent); agent->from_parent = -1; }
    if (agent->to_parent >= 0)   { close(agent->to_parent);   agent->to_parent   = -1; }

    /* Mark failed and free the spawn args to avoid leaking the heap allocation */
    agent->status = AG_FAILED;
    g_free(pass);
    return NULL;
  }

  while ((agent->pid = fork()) < 0)
    sleep(rand() % CONF_fork_backoff_time);

  /* we are in the child */
  if (agent->pid == 0)
  {
    /* set the child's stdin and stdout to use the pipes */
    dup2(agent->from_parent, fileno(stdin));
    dup2(agent->to_parent, fileno(stdout));
    dup2(agent->to_parent, fileno(stderr));

    /* close all the unnecessary file descriptors */
    g_tree_foreach(scheduler->agents, (GTraverseFunc) agent_close_fd, agent);
    close(agent->from_child);
    close(agent->to_child);

    /* set the priority of the process to the job's priority */
    if (nice(agent->owner->priority) == -1)
      ERROR("unable to correctly set priority of agent process %d", agent->pid);

    /* if host is null, the agent will run locally to */
    /* run the agent locally, use the commands that    */
    /* were parsed when the meta_agent was created    */
    if (strcmp(agent->host->address, LOCAL_HOST) == 0)
    {
      shell_parse(scheduler->sysconfigdir, agent->owner->user_id, agent->owner->group_id,
                  agent->type->raw_cmd, agent->owner->jq_cmd_args,
                  agent->owner->parent_id, &argc, &args);

      tmp = args[0];
      args[0] = g_strdup_printf(AGENT_BINARY, scheduler->sysconfigdir,
      AGENT_CONF, agent->type->name, tmp);

      strcpy(buffer, args[0]);
      *strrchr(buffer, '/') = '\0';
      if (chdir(buffer) != 0)
      {
        ERROR("unable to change working directory: %s\n", strerror(errno));
      }

      execv(args[0], args);
    }
    /* otherwise the agent will be started using ssh   */
    /* if the agent is started using ssh we don't need */
    /* to fully parse the arguments, just pass the run */
    /* command as the last argument to the ssh command */
    else
    {
      args = g_new0(char*, 5);
      len = snprintf(buffer, sizeof(buffer), AGENT_BINARY " --userID=%d --groupID=%d --scheduler_start --jobId=%d",
                     agent->host->agent_dir, AGENT_CONF, agent->type->name, agent->type->raw_cmd,
                     agent->owner->user_id, agent->owner->group_id, agent->owner->parent_id);

      if (len>=sizeof(buffer)) {
        *(buffer + sizeof(buffer) - 1) = '\0';
        log_printf("ERROR %s.%d: JOB[%d.%s]: exec failed: truncated buffer: \"%s\"",
            __FILE__, __LINE__, agent->owner->id, agent->owner->agent_type, buffer);

        exit(5);
      }

      args[0] = "/usr/bin/ssh";
      args[1] = agent->host->address;
      args[2] = buffer;
      args[3] = agent->owner->jq_cmd_args;
      args[4] = NULL;
      execv(args[0], args);
    }

    /* If we reach here, the exec call has failed */
    log_printf("ERROR %s.%d: JOB[%d.%s]: exec failed: pid = %d, errno = \"%s\"", __FILE__, __LINE__, agent->owner->id,
        agent->owner->agent_type, getpid(), strerror(errno));
  }
  /* we are in the parent */
  else
  {
    event_signal(agent_create_event, agent);
    agent_listen(scheduler, agent);
  }

  return NULL;
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * @brief Creates a new meta agent.
 *
 * This will take and parse the information necessary
 * for the creation of a new agent instance. The name of the agent, the cmd for
 * starting the agent, the number of these agents that can run simutaniously,
 * and any special conditions for this agent. This function is where the cmd
 * will get parsed to be passed as command line args to the new agent.
 *
 * @param name the name of the agent (i.e. nomos, buckets, etc...)
 * @param cmd the command for starting the agent in a shell
 *
 * @param max the number of these that can concurrently, -1 for no limit
 * @param spc any special conditions associated with the agent
 * @return meta_agent_t Meta agent
 */
meta_agent_t* meta_agent_init(char* name, char* cmd, int max, int spc)
{
  /* locals */
  meta_agent_t* ma;

  /* test inputs */
  if (!name || !cmd)
  {
    ERROR("invalid arguments passed to meta_agent_init()");
    return NULL;
  }

  /* confirm valid inputs */
  if (strlen(name) > MAX_NAME || strlen(cmd) > MAX_CMD)
  {
    log_printf("ERROR failed to load %s meta agent", name);
    return NULL;
  }

  /* inputs are valid, create the meta_agent */
  ma = g_new0(meta_agent_t, 1);

  strcpy(ma->name, name);
  strcpy(ma->raw_cmd, cmd);
  strcat(ma->raw_cmd, " --scheduler_start");
  ma->max_run = max;
  ma->run_count = 0;
  ma->special = spc;
  ma->version = NULL;
  ma->valid = TRUE;

  return ma;
}

/**
 * Free the memory associated with a meta_agent. This is a destructor, and as a
 * result the meta_agent should not be used again after a call to this method
 *
 * @param ma the meta_agent to clear
 */
void meta_agent_destroy(meta_agent_t* ma)
{
  TEST_NULV(ma);
  g_free(ma->version);
  g_free(ma);
}

/**
 * @brief Allocate and spawn a new agent.
 *
 * The agent that is spawned will be of the same
 * type as the meta_agent that is passed to this function and the agent will run
 * on the host that is passed.
 *
 * @param scheduler  the scheduler this agent is being created under
 * @param host       the machine to start the agent on
 * @param job        the job that this agent belongs to
 */
agent_t* agent_init(scheduler_t* scheduler, host_t* host, job_t* job)
{
  /* local variables */
  agent_t* agent;
  int child_to_parent[2];
  int parent_to_child[2];
  agent_spawn_args* pass;

  /* check job input */
  if (!job)
  {
    log_printf("ERROR %s.%d: NULL job passed to agent init\n", __FILE__, __LINE__);
    log_printf("ERROR: no other information available\n");
    return NULL;
  }

  /* check that the agent type exists */
  if (g_tree_lookup(scheduler->meta_agents, job->agent_type) == NULL)
  {
    log_printf("ERROR %s.%d: jq_pk %d jq_type %s does not match any module in mods-enabled\n", __FILE__, __LINE__,
        job->id, job->agent_type);
    job->message = NULL;
    job_fail_event(scheduler, job);
    job_remove_agent(job, scheduler->job_list, NULL);
    return NULL;
  }

  /* allocate memory and do trivial assignments */
  agent = g_new(agent_t, 1);
  agent->type = g_tree_lookup(scheduler->meta_agents, job->agent_type);
  agent->status = AG_CREATED;

  /* make sure that there is a metaagent for the job */
  if (agent->type == NULL)
  {
    ERROR("meta agent %s does not exist", job->agent_type);
    return NULL;
  }

  /* check if the agent is valid */
  if (!agent->type->valid)
  {
    ERROR("agent %s has been invalidated by version information", job->agent_type);
    return NULL;
  }

  /* create the pipes between the child and the parent */
  if (pipe(parent_to_child) != 0)
  {
    ERROR("JOB[%d.%s] failed to create parent to child pipe", job->id, job->agent_type);
    g_free(agent);
    return NULL;
  }
  if (pipe(child_to_parent) != 0)
  {
    ERROR("JOB[%d.%s] failed to create child to parent pipe", job->id, job->agent_type);
    g_free(agent);
    return NULL;
  }

  /* set file identifiers to correctly talk to children */
  agent->from_parent = parent_to_child[0];
  agent->to_child = parent_to_child[1];
  agent->from_child = child_to_parent[0];
  agent->to_parent = child_to_parent[1];

  /* initialize other info */
  agent->host = host;
  agent->owner = job;
  agent->updated = 0;
  agent->n_updates = 0;
  agent->data = NULL;
  agent->return_code = -1;
  agent->total_analyzed = 0;
  agent->special = 0;

  /* open the relevant file pointers */
  if ((agent->read = fdopen(agent->from_child, "r")) == NULL)
  {
    ERROR("JOB[%d.%s] failed to initialize read file", job->id, job->agent_type);
    g_free(agent);
    return NULL;
  }
  if ((agent->write = fdopen(agent->to_child, "w")) == NULL)
  {
    ERROR("JOB[%d.%s] failed to initialize write file", job->id, job->agent_type);
    g_free(agent);
    return NULL;
  }

  /* increase the load on the host and count of running agents */
  if (agent->owner->id > 0)
  {
    host_increase_load(agent->host);
    meta_agent_increase_count(agent->type);
  }

  /* spawn the listen thread */
  pass = g_new0(agent_spawn_args, 1);
  pass->scheduler = scheduler;
  pass->agent = agent;

#if GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32
  agent->thread = g_thread_new(agent->type->name, (GThreadFunc) agent_spawn, pass);
#else
  agent->thread = g_thread_create((GThreadFunc)agent_spawn, pass, 1, NULL);
#endif

  return agent;
}

/**
 * @brief Frees the memory associated with an agent.
 *
 * This include:
 * -# All of the files that are open in the agent
 * -# All of the pipes still open for the agent
 * -# Inform the os that the process can die using a waitpid()
 * -# Free the internal data structure of the agent
 *
 * @param agent the agent to destroy
 */
void agent_destroy(agent_t* agent)
{
  TEST_NULV(agent);

  /* close all of the files still open for this agent */
  close(agent->from_child);
  close(agent->to_child);
  close(agent->from_parent);
  close(agent->to_parent);
  fclose(agent->write);
  fclose(agent->read);

  /* release the child process */
  g_free(agent);
}

/* ************************************************************************** */
/* **** Events ************************************************************** */
/* ************************************************************************** */

/**
 * Event created when a SIGCHLD is received for an agent. If one SIGCHILD is
 * received for several process deaths, there will be seperate events for each
 * pid.
 *
 * @param scheduler the scheduler reference to which process was attached to
 * @param pid the pid of the process that died
 */
void agent_death_event(scheduler_t* scheduler, pid_t* pid)
{
  agent_t* agent;
  int status = pid[1];

  if ((agent = g_tree_lookup(scheduler->agents, &pid[0])) == NULL)
  {
    ERROR("invalid agent death event: pid[%d]", pid[0]);
    return;
  }

  if (agent->owner->id >= 0)
    event_signal(database_update_event, NULL);

  if (write(agent->to_parent, "@@@1\n", 5) != 5)
    AGENT_SEQUENTIAL_PRINT("write to agent unsuccessful: %s\n", strerror(errno));
  g_thread_join(agent->thread);

  if (agent->return_code != 0)
  {
    if (WIFEXITED(status))
    {
      AGENT_CONCURRENT_PRINT("agent failed, code: %d\n", (status >> 8));
    }
    else if (WIFSIGNALED(status))
    {
      AGENT_CONCURRENT_PRINT("agent was killed by signal: %d.%s\n", WTERMSIG(status), strsignal(WTERMSIG(status)));
      if (WCOREDUMP(status))
        AGENT_CONCURRENT_PRINT("agent produced core dump\n");
    }
    else
    {
      AGENT_CONCURRENT_PRINT("agent failed, code: %d\n", agent->return_code);
    }
    AGENT_WARNING("agent closed unexpectedly, agent status was %s", agent_status_strings[agent->status]);
    agent_fail_event(scheduler, agent);
  }

  if (agent->status != AG_PAUSED && agent->status != AG_FAILED)
    agent_transition(agent, AG_PAUSED);

  job_update(scheduler, agent->owner);
  if (agent->status == AG_FAILED && agent->owner->id < 0)
  {
    log_printf("ERROR %s.%d: agent %s.%s has failed scheduler startup test\n", __FILE__, __LINE__, agent->host->name,
        agent->type->name);
    agent->type->valid = 0;
  }

  if (agent->owner->id < 0 && !agent->type->valid)
    AGENT_SEQUENTIAL_PRINT("agent failed startup test, removing from meta agents\n");

  AGENT_SEQUENTIAL_PRINT("successfully remove from the system\n");
  job_remove_agent(agent->owner, scheduler->job_list, agent);
  g_tree_remove(scheduler->agents, &agent->pid);
  g_free(pid);
}

/**
 * @brief Event created when a new agent has been created.
 *
 * This means that the agent
 * has been allocated internally and the fork() call has successfully executed.
 * The agent has not yet communicated with the scheduler when this event is
 * created.
 *
 * @param scheduler the scheduler reference to which agent has to attach
 * @param agent the agent that has been created.
 */
void agent_create_event(scheduler_t* scheduler, agent_t* agent)
{
  TEST_NULV(agent);

  AGENT_SEQUENTIAL_PRINT("agent successfully spawned\n");
  g_tree_insert(scheduler->agents, &agent->pid, agent);
  agent_transition(agent, AG_SPAWNED);
  job_add_agent(agent->owner, agent);
}

/**
 * @brief Event created when an agent is ready for more data.
 *
 * This will event will be
 * created when an agent first communicates with the scheduler, so this will
 * handle changing its status to AG_RUNNING. This will also be created every
 * time an agent finishes a block of data.
 *
 * @param scheduler the scheduler reference to which agent is attached
 * @param agent the agent that is ready
 */
void agent_ready_event(scheduler_t* scheduler, agent_t* agent)
{
  int ret;

  TEST_NULV(agent);
  // If the agent has no job (owner is NULL), it shouldn't be here.
  // This prevents the "job passed is NULL
  if (agent->owner == NULL)
  {
      ERROR("Agent ready event received but agent has no owner. Terminating agent to prevent scheduler crash.");
      agent_kill(agent);
      return;
  }

  if (agent->status == AG_SPAWNED)
  {
    agent_transition(agent, AG_RUNNING);
    AGENT_SEQUENTIAL_PRINT("agent successfully created\n");
  }

  if ((ret = job_is_open(scheduler, agent->owner)) == 0)
  {
    agent_transition(agent, AG_PAUSED);
    job_finish_agent(agent->owner, agent);
    job_update(scheduler, agent->owner);
    return;
  }
  else if (ret < 0)
  {
    agent_transition(agent, AG_FAILED);
    return;
  }
  else
  {
    agent->data = job_next(agent->owner);
    agent->updated = 1;
  }

  if (write(agent->to_parent, "@@@0\n", 5) != 5)
  {
    AGENT_ERROR("failed sending new data to agent");
    agent_kill(agent);
  }
}

/**
 * Event created when the scheduler receives a SIGALRM. This will loop over
 * every agent and call the update function on it. This will kill any agents
 * that are hung without heart beat or any agents that have stopped updating
 * the number of item processed.
 *
 * @param scheduler the scheduler reference to inform
 * @param unused needed since this an event, but should be NULL
 */
void agent_update_event(scheduler_t* scheduler, void* unused)
{
  g_tree_foreach(scheduler->agents, (GTraverseFunc) update, NULL);
}

/**
 * @brief Fails an agent.
 *
 * This will move the agent status to AG_FAILED and send a
 * SIGKILL to the relevant agent. It will also update the agents status within
 * the job that owns it and close the associated communication thread.
 *
 * @param scheduler the scheduler to which agent is attached
 * @param agent  the agent that is failing.
 */
void agent_fail_event(scheduler_t* scheduler, agent_t* agent)
{
  TEST_NULV(agent);
  agent_transition(agent, AG_FAILED);
  job_fail_agent(agent->owner, agent);
  if (write(agent->to_parent, "@@@1\n", 5) != 5)
    AGENT_ERROR("Failed to kill agent thread cleanly");
}

/**
 * @brief Receive agent on interface.
 *
 * Calls agent_list() and print evert agent attached to the scheduler.
 * @param scheduler the scheduler to which agent is attached
 * @param ostr Stream to write info to
 */
void list_agents_event(scheduler_t* scheduler, GOutputStream* ostr)
{
  g_tree_foreach(scheduler->meta_agents, (GTraverseFunc) agent_list, ostr);
  g_output_stream_write(ostr, "\nend\n", 5, NULL, NULL);
}

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

/**
 * Changes the status of the agent internal to the scheduler. This function
 * is used to transition between agent states instead of a raw set of the status
 * so that correct printing of the verbose message is guaranteed
 *
 * @param agent the agent to change the status for
 * @param new_status the new status of the agentchar* sysconfdir = NULL;    // system configuration directory (SYSCONFDIR)
 */
void agent_transition(agent_t* agent, agent_status new_status)
{
  AGENT_SEQUENTIAL_PRINT("agent status change: %s -> %s\n", agent_status_strings[agent->status],
      agent_status_strings[new_status]);

  if (agent->owner->id > 0)
  {
    if (agent->status == AG_PAUSED)
    {
      host_increase_load(agent->host);
      meta_agent_increase_count(agent->type);
    }
    if (new_status == AG_PAUSED)
    {
      host_decrease_load(agent->host);
      meta_agent_decrease_count(agent->type);
    }
  }

  agent->status = new_status;
}

/**
 * Pauses an agent, this will pause the agent by sending a SIGSTOP to the
 * process and then decrease the load on the host machine.
 *
 * @param agent the agent to pause
 */
void agent_pause(agent_t* agent)
{
  kill(agent->pid, SIGSTOP);
  agent_transition(agent, AG_PAUSED);
}

/**
 * Unpause the agent, this will send a SIGCONT to the process regardless of if
 * a SIGCONT was sent. If the process wasn't SIGSTOP'd this will do nothing. Also
 * increases the load on the host.
 *
 * @param agent the agent to unpause
 */
void agent_unpause(agent_t* agent)
{
  kill(agent->pid, SIGCONT);
  agent_transition(agent, AG_RUNNING);
}

/**
 * @brief Prints the status of the agent to the output stream provided.
 *
 * The formating for this is as such:
 *
 *     `agent:<pid> host:<host> type:<type> status:<status> time:<time>`
 *
 * @param agent Agent to get info from
 * @param ostr  Stream to write info to
 */
void agent_print_status(agent_t* agent, GOutputStream* ostr)
{
  gchar* status_str;
  char time_buf[64];
  struct tm* time_info;

  TEST_NULV(agent);
  TEST_NULV(ostr);

  strcpy(time_buf, "(none)");
  time_info = localtime(&agent->check_in);
  if (time_info)
    strftime(time_buf, sizeof(time_buf), "%F %T", localtime(&agent->check_in));
  status_str = g_strdup_printf("agent:%d host:%s type:%s status:%s time:%s\n", agent->pid, agent->host->name,
      agent->type->name, agent_status_strings[agent->status], time_buf);

  AGENT_SEQUENTIAL_PRINT("AGENT_STATUS: %s", status_str);
  g_output_stream_write(ostr, status_str, strlen(status_str), NULL, NULL);
  g_free(status_str);
  return;
}

/**
 * @brief Unclean kill of an agent.
 *
 * This simply sends a SIGKILL to the agent and lets
 * everything else get cleaned up normally.
 *
 * @param agent the agent to kill
 */
void agent_kill(agent_t* agent)
{
  AGENT_SEQUENTIAL_PRINT("KILL: sending SIGKILL to pid %d\n", agent->pid);
  meta_agent_decrease_count(agent->type);
  kill(agent->pid, SIGKILL);
}

/**
 * Acts as a standard printf, but prints the agents instead of stdout. This is
 * the main function used by the scheduler when communicating with the agents.
 *
 * @param agent the agent to send the formated data to
 * @param fmt the formating string for the data
 * @return if the print was successful
 */
int aprintf(agent_t* agent, const char* fmt, ...)
{
  va_list args;
  int rc;
  char* tmp;

  va_start(args, fmt);
  if (TVERB_AGENT)
  {
    tmp = g_strdup_vprintf(fmt, args);
    tmp[strlen(tmp) - 1] = '\0';
    AGENT_CONCURRENT_PRINT("sent to agent \"%s\"\n", tmp);
    rc = fprintf(agent->write, "%s\n", tmp);
    g_free(tmp);
  }
  else
  {
    rc = vfprintf(agent->write, fmt, args);
  }
  va_end(args);
  fflush(agent->write);

  return rc;
}

/**
 * Write information to the communication thread for the agent. This is used
 * when the scheduler needs to wake up or kill the thread used to talk to the
 * agent. When using this function, one should always print "@@@..." where ...
 * is the message that is actually getting sent.
 *
 * @param agent the agent to send the information to
 * @param buf the actual data
 * @param count the number of bytes to write to the agent
 * @return returns if the write was successful
 */
ssize_t agent_write(agent_t* agent, const void* buf, int count)
{
  return write(agent->to_parent, buf, count);
}

/* ************************************************************************** */
/* **** static functions and meta agents ************************************ */
/* ************************************************************************** */

/**
 * @brief Calls the agent test function for every type of agent.
 *
 * This is used when either the -t or -T option are used upon scheduler creation.
 *
 * @param scheduler scheduler reference to test attached agents
 */
void test_agents(scheduler_t* scheduler)
{
  g_tree_foreach(scheduler->meta_agents, (GTraverseFunc) agent_test, scheduler);
}

/**
 * @brief Call the agent_kill function for every agent within the system.
 *
 * This will send a SIGKILL to every child process of the scheduler. Used when
 * shutting down the scheduler.
 */
void kill_agents(scheduler_t* scheduler)
{
  g_tree_foreach(scheduler->agents, (GTraverseFunc) agent_kill_traverse, NULL);
}

/**
 * Creates a new meta agent and adds it to the list of meta agents. This will
 * parse the shell command that will start the agent process.
 *
 * @param meta_agents GTree of meta agents available for the scheduler
 * @param name the name of the meta agent (e.g. "nomos", "copyright", etc...)
 * @param cmd the shell command used to the run the agent
 * @param max the max number of this type of agent that can run concurrently
 * @param spc anything special about the agent type
 */
int add_meta_agent(GTree* meta_agents, char* name, char* cmd, int max, int spc)
{
  meta_agent_t* ma;

  if (name == NULL)
    return 0;

  if (g_tree_lookup(meta_agents, name) == NULL)
  {
    if ((ma = meta_agent_init(name, cmd, max, spc)) == NULL)
      return 0;
    g_tree_insert(meta_agents, ma->name, ma);
    return 1;
  }

  return 0;
}

/**
 * @brief tests if a particular meta agent has a specific special flag set
 *
 * @param ma            the meta agent that should be checked
 * @param special_type  in what way is the agent special
 * @return              true or false
 */
int is_meta_special(meta_agent_t* ma, int special_type)
{
  return (ma != NULL) && ((ma->special & special_type) != 0);
}

/**
 * @brief tests if a particular agent has a specific special flag set
 *
 * @param a             the agent that should be tested
 * @param special_type  in what way is the agent special
 * @return              true or false
 */
int is_agent_special(agent_t* agent, int special_type)
{
  return (agent != NULL) && ((agent->special & special_type) != 0);
}

/**
 * Increase the running agent count.
 * @param ma Agent's meta
 */
void meta_agent_increase_count(meta_agent_t* ma)
{
  ma->run_count++;
  V_AGENT("AGENT[%s] run increased to %d\n", ma->name, ma->run_count);
}

/**
 * Decrease the running agent count.
 * @param ma Agent's meta
 */
void meta_agent_decrease_count(meta_agent_t* ma)
{
  ma->run_count--;
  V_AGENT("AGENT[%s] run decreased to %d\n", ma->name, ma->run_count);
}
