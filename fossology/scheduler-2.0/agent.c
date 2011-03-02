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
#include <database.h>
#include <event.h>
#include <host.h>
#include <job.h>
#include <logging.h>
#include <scheduler.h>

/* library includes */
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

/* unix library includes */
#include <fcntl.h>
#include <limits.h>
#include <sys/wait.h>
#include <unistd.h>

/* other library includes */
#include <glib.h>

/* agent defines */
#define MAX_ARGS 32       ///< the maximum number arguments passed to children  (arbitrary)
#define TILL_DEATH 180    ///< how long to wait before agent is dead            (3 minutes)
#define NUM_UPDATES 5     ///< the number of updates before agent is dead       (arbitrary)
#define MAX_GENERATION 5  ///< the most agents that a piece of data can survive (arbitrary)

#ifndef AGENT_DIR
#define AGENT_DIR ""      ///< the location of the agent executables for localhost
#endif

#define TEST_NULV(j) if(!j) { errno = EINVAL; ERROR("agent passed is NULL, cannot proceed"); return; }
#define TEST_NULL(j, ret) if(!j) { errno = EINVAL; ERROR("agent passed is NULL, cannot proceed"); return ret; }

GTree* meta_agents = NULL;   ///< The master list of all meta agents
GTree* agents      = NULL;   ///< The master list of all of the agents

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * Internal declaration of private members for the meta_agent type. Meta agents
 * are used to store the information necessary to create a new agent of the same
 * type as the meta_agent.
 */
struct meta_agent_internal
{
    char name[256];             ///< the name associated with this agent i.e. nomos, copyright...
    char raw_cmd[MAX_CMD + 1];  ///< the raw command that will start the agent, used for ssh
    char* parsed_cmd[MAX_ARGS]; ///< the parsed set of commands used to run the agent on localhost
    int max_run;                ///< the maximum number that can run at once -1 if no limit
    int special;                ///< any special condition associated with the agent
};

/**
 * Internal declaration of private members for the agent type. The agent type is
 * used to communicate with other the associated agent process. Holds host,
 * threading, status, pipes and data information relevant to what the process is
 * doing.
 */
struct agent_internal
{
    /* we need all the information on creating the agent */
    meta_agent meta_data; ///< the type of agent this is i.e. bucket, copyright...
    host host_machine;    ///< the host that this agent will start on
    /* thread management */
    agent_status status;  ///< the state of execution the agent is currently in
    GThread* thread;      ///< the thread that communicates with this agent
    time_t check_in;      ///< the time that the agent last generated anything
    int n_updates;        ///< keeps track of the number of times the agent has updated
    pid_t pid;            ///< the pid of the process this agent is running in
    /* pipes connecting to the child */
    int from_parent;      ///< file identifier to read from the parent (child stdin)
    int to_child;         ///< file identifier to print to the child
    int from_child;       ///< file identifier to read from child
    int to_parent;        ///< file identifier to print to the parent  (child stdout)
    FILE* read;           ///< FILE* that abstracts the use of the from_child socket
    FILE* write;          ///< FILE* that abstracts the use of the to_child socket
    /* data management */
    job owner;            ///< the job that this agent is assigned to
    char* data;           ///< the data that has been sent to the agent for analysis
    int generation;       ///< the generation of the data (i.e. how many agents has it survived)
    int updated;          ///< boolean flag to indicate if the scheduler has updated the data
    int check_analyzed;   ///< the number that were analyzed between last heartbeats
    int total_analyzed;   ///< the total number that this agent has analyzed
};

/**
 * TODO
 */
const char* status_strings[] = {
    "AG_FAILED",
    "AG_CREATED",
    "AG_SPAWNED",
    "AG_RUNNING",
    "AG_PAUSED",
    "AG_CLOSING",
    "AG_CLOSED"};

/* ************************************************************************** */
/* **** Local Functions ***************************************************** */
/* ************************************************************************** */

/**
 * This function will be called by g_tree_foreach() which is the reason for its
 * formatting. This will close all of the agent's pipes
 *
 * @param pid_ptr   the key that was used to store this agent (not needed by this function)
 * @param a         the agent that is being closed
 * @param excepted  there is one agent that we don't want to close the pipes on, this is it
 * @return always returns 0, TODO
 */
int agent_close_fd(int* pid_ptr, agent a, agent excepted)
{
  TEST_NULL(a, 0);
  if(a != excepted)
  {
    close(a->from_child);
    close(a->to_child);
    fclose(a->read);
    fclose(a->write);
  }
  return 0;
}

/**
 * Check the status and check in time of an agent.
 *   - if we haven't gotten a recent communication, close it
 *   - if it hasn't been performing tasks, close it
 *
 * @param pid_ptr pointer to key in g_tree, is not used in this function
 * @param a the agent that needs to be updated
 * @param unused data that is also not used in this function
 * @return always returns 0, TODO
 */
int update(int* pid_ptr, agent a, gpointer unused)
{
  TEST_NULL(a, 0);
  if(a->status == AG_SPAWNED || a->status == AG_RUNNING || a->status == AG_PAUSED)
  {
    /* check last checkin time */
    if(time(NULL) - a->check_in > TILL_DEATH)
    {
      ERROR("JOB[%d].%s[%d] no heartbeat for %d seconds",
          job_id(a->owner), job_type(a->owner), a->pid, time(NULL) - a->check_in);
      agent_fail(a);
      return 0;
    }

    /* check items processed */
    if(a->status != AG_PAUSED && a->check_analyzed == a->total_analyzed)
      a->n_updates++;
    else
      a->n_updates = 0;
    if(a->n_updates > NUM_UPDATES)
    {
      agent_fail(a);
      return 0;
    }

    a->check_analyzed = a->total_analyzed;

    VERBOSE3("JOB[%d].%s[%d]: agent updated correctly, processed %d items\n",
          job_id(a->owner), a->meta_data->name, a->pid, a->check_analyzed);
  }

  return 0;
}

/**
 * TODO
 *
 * @param name
 * @param a
 * @param unused
 * @return
 */
int agent_kill(char* name, agent a, gpointer unused)
{
  kill(a->pid, SIGKILL);
  return 0;
}

/**
 * TODO
 *
 * @param name
 * @param ma
 * @param unused
 * @return
 */
int agent_test(char* name, meta_agent ma)
{
  job j = job_init(ma->name, -1);
  agent_init(name_host("localhost"), j);
  return 0;
}

/**
 * Listens for information from the agent. Starts by waiting for the agent to
 * send SPAWNED, then it will wait for any other information from the agent.
 * Information that it can receive includes:
 *
 *  1:      This should be sent only once when the agent is ready for data
 *  2:      The agent has finished execution and is closing
 *  3:      The agent has been killed by the scheduler, usually for lack of heart beat
 *          If an agent has actually died, the heart beat should take care of this
 *  4::%d   registers the heart beat for the agent, the number provided should be increasing
 * <other>: Will be written to the agents log as debugging information
 *
 * @param a the agent that will be listened on
 */
void agent_listen(agent a)
{
  /* locals */
  char buffer[1024];          // buffer to store c strings read from agent, size is arbitraryssed
  int i;                      // simple indexing variable

  TEST_NULV(a);
  while(a->status == AG_CREATED || a->status == AG_SPAWNED || a->status == AG_RUNNING)
  {
    /* get message from agent */
    if(fgets(buffer, sizeof(buffer), a->read) == NULL)
    {
      clprintf("T_FATAL %s.%d: JOB[%d].%s[%d] pipe from child closed\nT_FATAL errno is: %s\n"
          __FILE__, __LINE__, job_id(a->owner), a->meta_data->name, a->pid, strerror(errno));
    }

    if(TVERBOSE3)
    {
      buffer[strlen(buffer) - 1] = '\0';
      clprintf("JOB[%d].%s[%d]: received: \"%s\"\n",
          job_id(a->owner), a->meta_data->name, a->pid, buffer);
    }

    /* check for messages from scheduler or clean agent death */
    if( strncmp(buffer, "BYE", 3) == 0 || strncmp(buffer, "@@@1", 4) == 0)
      break;
    if(strncmp(buffer, "@@@0", 4) == 0 && a->updated)
    {
      aprintf(a, "%s\n", job_next(a->owner));
      aprintf(a, "END\n");
      fflush(a->write);
      a->updated = 0;
      continue;
    }

    /* if we get here it is an actual message from the agent */
    a->check_in = time(NULL);

    /* the agent has indicated that it is ready for data */
    if(strncmp(buffer, "OK", 2) == 0)
    {
      if(!job_is_paused(a->owner))
        event_signal(agent_ready_event, a);
    }
    /* heart beat received from agent */
    else if(strncmp(buffer, "HEART", 5) == 0)
    {
      a->n_updates++;
      i = atoi(&buffer[7]);
      a->check_analyzed = i - a->total_analyzed;
      a->total_analyzed = i;
    }
    /* agent is failing, log why */
    else if(strncmp(buffer, "FATAL", 5) == 0)
    {
      clprintf("FATAL:JOB[%d].%s[%d]: \"%s\"\n",
          job_id(a->owner), a->meta_data->name, a->pid, &buffer[5]);
      break;
    }
    /* the agent has recieved an error, log what */
    else if(strncmp(buffer, "ERROR", 5) == 0)
    {
      clprintf("ERROR:JOB[%d].%s[%d]: \"%s\"\n",
          job_id(a->owner), a->meta_data->name, a->pid, &buffer[5]);
      break;
    }
    /* we aren't quite sure what the agent sent, log it */
    else
    {
      clprintf("JOB[%d].%s[%d]: message \"%s\"\n",
          job_id(a->owner), a->meta_data->name, a->pid, buffer);
    }
  }

  if(TVERBOSE3)
    clprintf("JOB[%d].%s[%d]: communication thread closing\n",
        job_id(a->owner), a->meta_data->name, a->pid);
}

/**
 * Spawns a new agent using the command passed in using the meta agent. This
 * function will call the fork and exec necessary to create a new agent. As a
 * result what this function does will change depending on if it is running
 * in the child or the parent.
 *
 * child:
 *   will duplicate the stdin, stdout, and stderr pipes for printing to the
 *   scheduler, parse the command line options for the agent and start the
 *   agent. It will then call exec to start the new agent process
 *
 * parent:
 *   this will enter the listen function, and wait for information from the
 *   child, either as a failure or as an update for the information being
 *   analyzed
 *
 * @param passed a pointer to the agent that is being spawned
 */
void* agent_spawn(void* passed)
{
  /* locals */
  agent a = (agent)passed;    // the agent that is being spawned
  char* args[MAX_ARGS + 1];   // the arguments that will be passed to the child
  char buffer[2048];          // character buffer

  TEST_NULL(a, NULL);
  /* we are in the child */
  if((a->pid = fork()) == 0)
  {
    /* set the child's stdin and stdout to use the pipes */
    dup2(a->from_parent, fileno(stdin));
    dup2(a->to_parent, fileno(stdout));
    //dup2(a->to_parent, fileno(stderr));

    /* close all the unnecessary file descriptors */
    g_tree_foreach(agents, (GTraverseFunc)agent_close_fd, a);
    close(a->from_child);
    close(a->to_child);

    /* if host is null, the agent will run locally to */
    /* run the agent localy, use the commands that    */
    /* were parsed when the meta_agent was created    */
    if(strcmp(host_address(a->host_machine), "localhost") == 0)
    {
      sprintf(buffer, "%s/%s", AGENT_DIR, a->meta_data->parsed_cmd[0]);
      memcpy(args, a->meta_data->parsed_cmd, sizeof(a->meta_data->parsed_cmd));
      args[0] = buffer;
      execv(args[0], args);
    }
    /* otherwise the agent will be started using ssh   */
    /* if the agent is started using ssh we don't need */
    /* to fully parse the arguments, just pass the run */
    /* command as the last argument to the ssh command */
    else
    {
      sprintf(buffer, "%s/%s", host_agent_dir(a->host_machine), a->meta_data->raw_cmd);
      args[0] = "/usr/bin/ssh";
      args[1] = host_address(a->host_machine);
      args[2] = buffer;
      args[3] = 0;
      execv(args[0], args);
    }

    /* we should never reach here */
    clprintf("ERROR %s.%d: JOB[%d].AGENT[%d] exec failed\nERROR errno is: %s\n",
        __FILE__, __LINE__, job_id(a->owner), getpid(), strerror(errno));
  }
  /* we are in the parent */
  else if(a->pid > 0)
  {
    event_signal(agent_create_event, a);
    agent_listen(a);
  }
  /* error case */
  else
  {
    clprintf("ERROR %s.%d: JOB[%d].AGENT[%d] for failed\nERROR errno is: %s\n",
        __FILE__, __LINE__, job_id(a->owner), getpid(), strerror(errno));
  }

  return NULL;
}

/**
 * TODO
 *
 * @param a
 * @param new_status
 */
void transition(agent a, agent_status new_status)
{
  VERBOSE3("JOB[%d].%s[%d]: agent status changed: %s -> %s\n",
        job_id(a->owner), a->meta_data->name, a->pid,
        status_strings[a->status], status_strings[new_status]);
  a->status = new_status;
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * Creates a new meta agent. This will take and parse the information necessary
 * for the creation of a new agent instance. The name of the agent, the cmd for
 * starting the agent, the number of these agents that can run simutaniously, and
 * any special conditions for this agent. This function is where the cmd will get
 * parsed to be passed as command line args to the new agent.
 *
 * @param name the name of the agent (i.e. nomos, buckets, etc...)
 * @param cmd the command for starting the agent in a shell
 * @param max the number of these that can concurrently, -1 for no limit
 * @param spc any special conditions associated with the agent
 * @return
 */
meta_agent meta_agent_init(char* name, char* cmd, int max, int spc)
{
  /* locals */
  meta_agent ma;
  char cpy[MAX_CMD + 1];
  char* loc_1, * loc_2;
  int i = 0;

  /* test inputs */
  if(!name || !cmd)
  {
    errno = EINVAL;
    ERROR("invalid arguments passed to meta_agent_init()");
    return NULL;
  }

  /* confirm valid inputs */
  if(strlen(name) > MAX_NAME || strlen(cmd) > MAX_CMD)
  {
    lprintf("ERROR failed to load %s meta agent", name);
    return NULL;
  }

  /* inputs are valid, create the meta_agent */
  ma = (meta_agent)calloc(1, sizeof(struct meta_agent_internal));

  strcpy(cpy, cmd);
  strcpy(ma->name, name);
  strcpy(ma->raw_cmd, cmd);
  ma->max_run = max;
  ma->special = spc;
  memset(ma->parsed_cmd, 0, sizeof(ma->parsed_cmd));

  /* parse the command like a normal command line argument */
  loc_1 = cpy;
  while(loc_1)
  {
    if(*loc_1 == '"')
    {
      loc_2 = strchr(loc_1 + 1, '"');
      if(loc_2 != NULL)
        *loc_2 = 0;
      ma->parsed_cmd[i] = (char*)calloc(1, strlen(loc_1 + 1) + 1);
      strcpy(ma->parsed_cmd[i++], loc_1 + 1);
      if(loc_2 == NULL)
        loc_1 = NULL;
      else
        loc_1 = loc_2 + 2;
    }
    else
    {
      loc_2 = strchr(loc_1, ' ');
      if(loc_2 != NULL)
        *loc_2 = 0;
      ma->parsed_cmd[i] = (char*)calloc(1, strlen(loc_1) + 1);
      strcpy(ma->parsed_cmd[i++], loc_1);
      if(loc_2 == NULL)
        loc_1 = NULL;
      else
        loc_1 = loc_2 + 1;
    }
  }

  return ma;
}

/**
 * Free the memory associated with a meta_agent. This is a destructor, and as a
 * result the meta_agent should not be used again after a call to this method
 *
 * @param ma the meta_agent to clear
 */
void meta_agent_destroy(meta_agent ma)
{
  int i;

  TEST_NULV(ma)
  for(i = 0; ma->parsed_cmd[i]; i++)
  {
    free(ma->parsed_cmd[i]);
  }

  free(ma);
}

/**
 * allocate and spawn a new agent. The agent that is spawned will be of the same
 * type as the meta_agent that is passed to this function and the agent will run
 * on the host that is passed.
 *
 * @param meta_data, the
 */
agent agent_init(host host_machine, job j)
{
  /* local variables */
  agent a;
  int child_to_parent[2];
  int parent_to_child[2];

  /* check inputs */
  if(!j || g_tree_lookup(meta_agents, job_type(j)) == NULL)
  {
    errno = EINVAL;
    ERROR("invalid arguments passed to agent_init");
    return NULL;
  }

  /* allocate memory and do trivial assignments */
  a = (agent)calloc(1, sizeof(struct agent_internal));
  a->meta_data = g_tree_lookup(meta_agents, job_type(j));
  a->status = AG_CREATED;

  /* create the pipes between the child and the parent */
  if(pipe(parent_to_child) != 0)
  {
    ERROR("JOB[%d.%s] failed to create parent to child pipe", job_id(j), job_type(j));
    return NULL;
  }
  if(pipe(child_to_parent) != 0)
  {
    ERROR("JOB[%d.%s] failed to create child to parent pipe", job_id(j), job_type(j));
    return NULL;
  }

  /* set file identifiers to correctly talk to children */
  a->from_parent = parent_to_child[0];
  a->to_child = parent_to_child[1];
  a->from_child = child_to_parent[0];
  a->to_parent = child_to_parent[1];

  /* initialize other info */
  a->host_machine = host_machine;
  a->owner = j;
  a->updated = 0;
  a->n_updates = 0;

  /* open the relevant file pointers */
  if((a->read = fdopen(a->from_child, "r")) == NULL)
  {
    ERROR("JOB[%d.%s] failed to initialize read file", job_id(j), job_type(j));
    return NULL;
  }
  if((a->write = fdopen(a->to_child, "w")) == NULL)
  {
    ERROR("JOB[%d.%s] failed to initialize write file", job_id(j), job_type(j));
    return NULL;
  }

  /* spawn the listen thread */
  a->thread = g_thread_create(agent_spawn, a, 1, NULL);
  return a;
}

/**
 * Allocate and spawn a new agent based upon an old agent. This will spawn the new
 * agent
 *
 * @param a
 * @return
 */
agent agent_copy(agent a)
{
  TEST_NULL(a, NULL);
  if(a->generation == MAX_GENERATION)
    return NULL;

  VERBOSE3("JOB[%d].%s[%d]: creating copy of agent\n",
        job_id(a->owner), a->meta_data->name, a->pid);

  agent cpy = agent_init(a->host_machine, a->owner);
  cpy->data = a->data;
  cpy->generation = a->generation + 1;

  return cpy;
}

/**
 * frees the memory associated with an agent.
 *
 * This include:
 *  all of the files that are open in the agent
 *  all of the pipes still open for the agent
 *  inform the os that the process can die using a waitpid()
 *  free the internal data structure of the agent
 *
 * @param a the agent to destroy
 */
void agent_destroy(agent a)
{
  TEST_NULV(a);

  /* close all of the files still open for this agent */
  close(a->from_child);
  close(a->to_child);
  close(a->from_parent);
  close(a->to_parent);
  fclose(a->write);
  fclose(a->read);

  /* release the child process */
  free(a);
}

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

/**
 * TODO
 *
 * @param pids
 */
void agent_death_event(void* pids)
{
  /* locals */
  int* curr;      // the current pid being manipulated
  int  db = 0;    // flag to run a database update
  agent a;        // agent to be accessed

  TEST_NULV(pids);

  /* for each agent, check its status and change accordingly */
  for(curr = (int*)pids; *curr; curr++)
  {
    a = g_tree_lookup(agents, curr);

    if(a->status != AG_CLOSING)
    {
      ERROR("JOB[%d].%s[%d]: agent closed unexpectedly, agent status was %s",
          job_id(a->owner), a->meta_data->name, a->pid, status_strings[a->status]);
      agent_fail(a);
    }
    else
    {
      agent_close(a);
    }

    if(job_id(a->owner) >= 0)
      db = 1;
  }

  if(db) database_update_event(NULL);

  /* clean up the passed params */
  free(pids);
}

/**
 * TODO
 *
 * @param a
 */
void agent_create_event(agent a)
{
  TEST_NULV(a);

  VERBOSE3("JOB[%d].%s[%d]: agent successfully spawned\n",
      job_id(a->owner), a->meta_data->name, a->pid);

  g_tree_insert(agents, &a->pid, a);
  transition(a, AG_SPAWNED);
  job_add_agent(a->owner, a);
}

/**
 * TODO
 *
 * @param a
 */
void agent_ready_event(agent a)
{
  TEST_NULV(a);
  if(a->status == AG_SPAWNED)
  {
    transition(a, AG_RUNNING);
    VERBOSE2("JOB[%d].%s[%d]: agent successfully created\n",
          job_id(a->owner), a->meta_data->name, a->pid);
  }

  if(a->generation == 0)
  {
    if(!job_is_open(a->owner))
    {
      transition(a, AG_PAUSED);
      job_finish_agent(a->owner, a);
      job_update(a->owner);
    }
    else
    {
      a->updated = 1;
      a->generation = 0;
      if(write(a->to_parent, "@@@0\n", 5) != 5)
      {
        ERROR("JOB[%d].%s[%d]: failed sending new data to agent",
            job_id(a->owner), a->meta_data->name, a->pid);
        agent_fail(a);
      }
    }
  }
}

/**
 * TODO
 *
 * @param unused
 */
void agent_update_event(void* unused)
{
  g_tree_foreach(agents, (GTraverseFunc)update, NULL);
}

/**
 * TODO
 *
 * @param a
 * @param ref
 */
void agent_restart(agent a, agent ref)
{
  TEST_NULV(a);
  TEST_NULV(ref);
  VERBOSE3("JOB[%d].%s[%d]: restarting agent to finish data from %s[%d]\n",
        job_id(a->owner), a->meta_data->name, a->pid, ref->meta_data->name, ref->pid);

  a->data = ref->data;
  a->updated = 1;
  a->generation = ref->generation + 1;
  if(write(a->to_parent, "@@@0\n", 5) != 5)
  {
    ERROR("JOB[%d].%s[%d]: failed to restart agent with new data",
        job_id(a->owner), a->meta_data->name, a->pid);
    agent_fail(a);
  }
}

/**
 * TODO
 *
 * @param a
 */
void agent_fail(agent a)
{
  TEST_NULV(a);
  kill(a->pid, SIGKILL);
  transition(a, AG_FAILED);
  job_fail_agent(a->owner, a);
  if(write(a->to_parent, "@@@1\n", 5) != 5)
  {
    ERROR("JOB[%d].%s[%d]: Failed to kill agent thread cleanly",
        job_id(a->owner), a->meta_data->name, a->pid);
  }
  job_update(a->owner);
}

/**
 * TODO
 *
 * @param a
 */
void agent_close(agent a)
{
  lprintf("AGENT: %d.%s\n", a->pid, status_strings[a->status]);
  if(a->status != AG_CLOSING && a->status != AG_FAILED)
  {
    transition(a, AG_CLOSING);
    aprintf(a, "CLOSE\n");
  }
  else
  {
    job_remove_agent(a->owner, a);
    // TODO change to detach when the scheduler is done
    g_thread_join(a->thread);

    VERBOSE2("JOB[%d].%s[%d]: successfully removed from the system\n",
          job_id(a->owner), a->meta_data->name, a->pid);

    g_tree_remove(agents, &a->pid);
  }
}

/**
 * TODO
 *
 * @param a
 * @return
 */
host agent_host(agent a)
{
  TEST_NULL(a, NULL);
  return a->host_machine;
}

/**
 * TODO
 *
 * @param a
 * @param ostr
 */
void agent_print_status(agent a, GOutputStream* ostr)
{



}

/**
 * TODO
 *
 * @param a
 * @param fmt
 * @return
 */
int aprintf(agent a, const char* fmt, ...)
{
  va_list args;
  int rc;
  char* tmp;

  va_start(args, fmt);
  if(TVERBOSE3)
  {
    tmp = g_strdup_vprintf(fmt, args);
    tmp[strlen(tmp) - 1] = '\0';
    clprintf("JOB[%d].%s[%d]: sent to agent \"%s\"\n",
        job_id(a->owner), a->meta_data->name, a->pid, tmp);
    rc = fprintf(a->write, "%s\n", tmp);
    g_free(tmp);
  }
  else
  {
    rc = vfprintf(a->write, fmt, args);
  }
  va_end(args);
  fflush(a->write);

  return rc;
}

/**
 * TODO
 *
 * @param a
 * @param buf
 * @param count
 * @return
 */
ssize_t agent_write(agent a, const void* buf, size_t count)
{
  return write(a->to_parent, buf, count);
}

/* ************************************************************************** */
/* **** static functions and meta agents ************************************ */
/* ************************************************************************** */

/**
 * TODO
 */
void test_agents()
{
  g_tree_foreach(meta_agents, (GTraverseFunc)agent_test, NULL);
}

/**
 * TODO
 */
void kill_agents()
{
  g_tree_foreach(agents, (GTraverseFunc)agent_kill, NULL);
}

/**
 * TODO
 */
void agent_list_clean()
{
  if(meta_agents) g_tree_destroy(meta_agents);
  meta_agents = NULL;
  if(agents) g_tree_destroy(agents);
  agents = NULL;
}

/**
 * TODO
 *
 * @param name
 * @param cmd
 * @param max
 * @param spc
 */
int add_meta_agent(char* name, char* cmd, int max, int spc)
{
  meta_agent ma;

  if(!name || !cmd)
  {
    errno = EINVAL;
    ERROR("could job add new meta agent");
    return 0;
  }

  if(meta_agents == NULL)
  {
    meta_agents = g_tree_new_full(string_compare, NULL, NULL, (GDestroyNotify)meta_agent_destroy);
    agents      = g_tree_new_full(int_compare   , NULL, NULL, (GDestroyNotify)agent_destroy);
  }

  if(g_tree_lookup(meta_agents, name) == NULL)
  {
    ma = meta_agent_init(name, cmd, max, spc);
    g_tree_insert(meta_agents, ma->name, ma);
    return 1;
  }

  return 0;
}

/**
 * Checks to see if a particular agent type is available in the list of
 * meta agents.
 *
 * @param name the type of the agent (it's name)
 * @return if the type exists in the list of meta agents
 */
int is_meta_agent(char* name)
{
  return g_tree_lookup(meta_agents, name) != NULL;
}

/**
 * TODO
 *
 * @return
 */
int num_agents()
{
  return g_tree_nnodes(agents);
}

