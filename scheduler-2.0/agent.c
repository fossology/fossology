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
#include <job.h>
#include <event.h>
#include <scheduler.h>

/* library includes */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

/* unix library includes */
#include <fcntl.h>
#include <limits.h>
#include <pthread.h>
#include <sys/wait.h>
#include <unistd.h>

/* other library includes */
#include <glib.h>

/* agent defines */
#define MAX_ARGS 32       ///< the maximum number arguments passed to children  (arbitrary)
#define TILL_DEATH 180    ///< how long to wait before agent is dead            (3 minutes)
#define MAX_GENERATION 5  ///< the most agents that a piece of data can survive (arbitrary)
#ifndef AGENT_DIR
#define AGENT_DIR ""   ///< the location of the agent executables for localhost
#endif

GTree* meta_agents = NULL;   ///< The master list of all meta agents
GTree* agents      = NULL;   ///< The master list of all of the agents

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
struct meta_agent_internal
{
    /* information relating to creation of agent */
    char name[256];             ///< the name associated with this agent i.e. nomos, copyright...
    char raw_cmd[MAX_CMD + 1];  ///< the raw command that will start the agent, used for ssh
    char* parsed_cmd[MAX_ARGS]; ///< the parsed set of commands used to run the agent on localhost
    int max_run;                ///< the maximum number that can run at once -1 if no limit
    int special;                ///< any special condition associated with the agent
};

/**
 * TODO
 */
struct agent_internal
{
    /* we need all the information on creating the agent */
    meta_agent meta_data; ///< the type of agent this is i.e. bucket, copyright...
    host host_machine;    ///< the host that this agent will start on, TODO change to host object
    /* thread management */
    agent_status status;  ///< the state of execution the agent is currently in
    pthread_t thread;     ///< the thread that communicates with this agent
    time_t check_in;      ///< the time that the agent last generated anything
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
    char** data;          ///< the data that has been sent to the agent for analysis
    int generation;       ///< the generation of the data (i.e. how many agents has it survived)
    int updated;          ///< boolean flag to indicate if the scheduler has updated the data
    int check_analyzed;   ///< the number that were analyzed at last update
    int total_analyzed;   ///< the total number that this agent has analyzed
};

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
 */
int agent_close(int* pid_ptr, agent a, agent excepted)
{
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
 * @return
 */
int update(int* pid_ptr, agent a, gpointer unused)
{
  if(a->status == AG_SPAWNED || a->status == AG_RUNNING || a->status == AG_PAUSED)
  {
    /* check last checkin time */
    if(time(NULL) - a->check_in > TILL_DEATH)
    {
      agent_fail(a);
      return;
    }

    /* check items processed */
    if(a->status != PAUSED && a->check_analyzed == a->total_analyzed)
    {
      agent_fail(a);
      return;
    }

    a->check_analyzed = a->total_analyzed;
  }
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
void listen(agent a)
{
  /* locals */
  char buffer[1024];          // buffer to store c strings read from agent, size is arbitraryssed
  int i;                      // simple indexing variable

  /* send the size of a job to the agent */
  fprintf(a->write, "%s\n", CHECKOUT_SIZE);

  /* initalize memory */
  memset(buffer, '\0', sizeof(buffer));

  while(a->status == AG_CREATED || a->status == AG_SPAWNED || a->status == AG_RUNNING)
  {
    /* get message from agent */
    if(fgets(buffer, sizeof(buffer), a->read) == NULL)
    {
      // TODO error message
      break;
    }

    /* check for a message from scheduler */
    if(strncmp(buffer, "@@@0", 4) == 0 && a->updated)
    {
      for(i = 0; i < CHECKOUT_SIZE && a->data[i]; i++) {
        fprintf(a->write, "%s\n", a->data[i]);
      }
      fflush(a->write);
      a->updated = 0;
    }
    /* the agent has indicated that it is ready for data */
    else if(strncmp(buffer, "OK", 2) == 0)
    {
      a->check_in = time(NULL);
      event_signal(agent_ready_event, a);
    }
    /* heart beat received from agent */
    else if(strncmp(buffer, "HEART", 5) == 0)
    {
      a->check_in = time(NULL);
      // TODO
    }
    /* the agent has finished execution, finish this thread */
    else if( strncmp(buffer, "BYE", 3) == 0 || strncmp(buffer, "@@@1", 4) == 0)
    {
      break;
    }
    else
    {
      // TODO logging
    }
  }
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
void* spawn(void* passed)
{
  /* locals */
  agent a = (agent)passed;    // the agent that is being spawned
  char* args[MAX_ARGS + 1];   // the arguments that will be passed to the child
  char buffer[2048];          // character buffer

  /* we are in the child */
  if((a->pid = fork()) == 0)
  {
    /* set the child's stdin and stdout to use the pipes */
    dup2(a->from_parent, fileno(stdin));
    dup2(a->to_parent, fileno(stdout));
    dup2(a->to_parent, fileno(stderr));

    /* close all the unnecessary file descriptors */
    g_tree_foreach(agents, (GTraverseFunc)agent_close, a);
    close(a->from_child);
    close(a->to_child);

    /* if host is null, the agent will run locally to */
    /* run the agent localy, use the commands that    */
    /* were parsed when the meta_agent was created    */
    if(a->host_machine == NULL)
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
      sprintf(buffer, "%s/%s", a->host_machine->agent_dir, a->meta_data->raw_cmd);
      args[0] = "/usr/bin/ssh";
      args[1] = a->host_machine->address;
      args[2] = buffer;
      args[3] = 0;
      execv(args[0], args);
    }

    /* we should never reach here */
    THREAD_FATAL("exec failed");
  }
  /* we are in the parent */
  else if(a->pid > 0)
  {
    // TODO does this introduce a bug?
    //close(a->to_parent);
    //close(a->from_parent);
    event_signal(agent_create_event, a);
    listen(a);
  }
  /* error case */
  else
  {
    THREAD_FATAL("exec failed");
  }

  return NULL;
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
gint pid_compare(gconstpointer a, gconstpointer b, gpointer user_data)
{
  return *(int*)a - *(int*)b;
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

  /* confirm valid inputs */
  if(strlen(name) > MAX_NAME || strlen(cmd) > MAX_CMD)
  {
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
agent agent_init(char* meta_agent_name, host host_machine, job j)
{
  /* local variables */
  agent a;
  int child_to_parent[2];
  int parent_to_child[2];

  /* allocate memory and do trivial assignments */
  a = (agent)calloc(1, sizeof(struct agent_internal));
  a->meta_data = g_tree_lookup(meta_agents, meta_agent_name);
  a->status = AG_CREATED;

  /* create the pipes between the child and the parent */
  if(pipe(parent_to_child) != 0)
    FATAL("failed to create parent to child pipe");
  if(pipe(child_to_parent) != 0)
    FATAL("failed to create child to parent pipe");

  /* set file identifiers to correctly talk to children */
  a->from_parent = parent_to_child[0];
  a->to_child = parent_to_child[1];
  a->from_child = child_to_parent[0];
  a->to_parent = child_to_parent[1];

  /* initialize other info */
  a->host_machine = host_machine;
  a->owner = j;

  /* open the relevant file pointers */
  if((a->read = fdopen(a->from_child, "r")) == NULL)
    FATAL("read_from init failed")
  if((a->write = fdopen(a->to_child, "w")) == NULL)
    FATAL("write_to init failed")

  /* spawn the listen thread */
  pthread_create(&a->thread, NULL, spawn, a);
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
  if(a->generation == MAX_GENERATION)
    return NULL;

  agent cpy = agent_init(a->meta_data->name, a->host_machine, a->owner);
  cpy->data = a->data;
  cpy->generation = a->generation + 1;
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
  /* locals */
  int status;

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
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

/**
 * TODO
 *
 * @return
 */
int num_agents()
{
  return g_tree_nnodes(agents);
}

/**
 * TODO
 *
 * @param a
 * @return
 */
host agent_host(agent a)
{
  return a->host_machine;
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
  agent a;        // agent to be accessed

  /* for each agent, check its status and change accordingly */
  for(curr = (int*)pids; *curr; curr++)
  {
    a = g_tree_lookup(agents, curr);

    if(a->status != AG_CLOSING)
    {
      write(a->to_parent, "@@@1\n", 5);
      a->status = AG_FAILED;
      job_update(a->owner);
      // TODO logging
    }
  }

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
  g_tree_insert(agents, &a->pid, a);
  a->status = AG_SPAWNED;
  job_add_agent(a);
  // TODO logging
}

/**
 * TODO
 *
 * @param a
 */
void agent_ready_event(agent a)
{
  if(a->status == AG_SPAWNED)
    a->status = AG_RUNNING;

  if(a->generation == 0)
  {
    if((a->data = job_next(a->owner)) == NULL)
    {
      a->status = PAUSED;
      job_update(a->owner);
    }
    else
    {
      a->updated = 1;
      a->generation = 0;
      write(a->to_parent, "@@@0\n", 5);
    }
  }
}

/**
 * TODO
 *
 * @param a
 */
void agent_close_event(agent a)
{
  pthread_join(a->thread, NULL);
  g_tree_remove(agents, &a->pid);
  // TODO logging
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
 */
void agent_fail(agent a)
{
  a->status = AG_FAILED;
  kill(a->pid, SIGKILL);
  write(a->to_parent, "@@@1\n", 5);
  job_update(a->owner);
}

/**
 * TODO
 *
 * @param name
 * @param cmd
 * @param max
 * @param spc
 */
void add_meta_agent(char* name, char* cmd, int max, int spc)
{
  if(meta_agents == NULL)
  {
    meta_agents = g_tree_new_full(string_compare, NULL, NULL, (GDestroyNotify)meta_agent_destroy);
    agents      = g_tree_new_full(pid_compare   , NULL, NULL, (GDestroyNotify)agent_destroy);
  }

  g_tree_insert(meta_agents, name, meta_agent_init(name, cmd, max, spc));
}

