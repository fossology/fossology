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
#include <scheduler.h>

/* library includes */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* unix library includes */
#include <fcntl.h>
#include <pthread.h>
#include <unistd.h>
#include <limits.h>

/* other library includes */
#include <glib.h>

/* agent defines */
#define MAX_ARGS 32     ///< the maximum number arguments passed to children (arbitrary)
#define MAX_CMD  1023   ///< the maximum length for an agent's start command (arbitrary)
#define MAX_NAME 155    ///< the maximum length for an agent's name          (arbitrary)
#define TILL_DEATH 180  ///< how long to wait before agent is dead           (3 minutes)

/** The mater list of all of the agents */
GTree* agents;

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
    meta_agent meta_data;   ///< the type of agent this is i.e. bucket, copyright...
    char* host;             ///< the host that this agent will start on, TODO change to host object
    /* thread management */
    agent_status status;    ///< the state of execution the agent is currently in
    pthread_t thread;       ///< the thread that communicates with this agent
    time_t check_in;        ///< the time that the agent last generated anything
    pid_t pid;              ///< the pid of the process this agent is running in
    /* pipes connecting to the child */
    int from_parent;        ///< file identifier to read from the parent (child stdin)
    int to_child;           ///< file identifier to print to the child
    int from_child;         ///< file identifier to read from child
    int to_parent;          ///< file identifier to print to the parent  (child stdout)
    FILE* read;             ///< FILE* that abstracts the use of the from_child socket
    FILE* write;            ///< FILE* that abstracts the use of the to_child socket
    /* data management */
    int data_start;         ///< the first file in the range that this agent is working on
    int data_end;           ///< the last file in the range that this agent is working on
    int num_analyzed;       ///< the number that had been analyzed at the last checkin
};

/* ************************************************************************** */
/* **** Local Functions ***************************************************** */
/* ************************************************************************** */

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
  char buffer[1024];          // buffer to store c strings read from agent, size is arbitrary
  long int items_processed;   // used by the heart beat to log how many items have been processed
  size_t bytes;               // the number of bytes read or written

  /* initalize memory */
  memset(buffer, '\0', sizeof(buffer));
  items_processed = 0;

  /* read SPAWNED from the agent, if not then fail the agent */
  /* TODO currently this limits the number of agents to 256  */
  bytes = fread(buffer, 2, sizeof(char), a->read);
  if(buffer[0] != 1 || ((unsigned char)buffer[1]) != a->pid)
  {
    kill(a->pid, SIGKILL);
    agent_fail(a);
    return;
  }

  bytes = fread(buffer, 1, sizeof(char), a->read);
  switch(buffer[0])
  {
    /* should not be recieved */
    case 1: ERROR("recieved spawning signal from already spawned agent");
    /* agent is dead, simply close */
    case 2: case 3: return;
    /* the agent has sent a heart beat, record the value provided */
    case 4:
      bytes = fread(&items_processed, 1, sizeof(items_processed), a->read);
      break;
    default:
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
  agent a = (agent)passed;              // the agent that is being spawned
  char* args[MAX_ARGS + 1];             // the arguments that will be passed to the child
  char* curr;                           // a pointer to the current location in the argument array
  char buffer[MAX_CMD + 1];             // buffer to hold different c strings
  int num_args = 0;                     // the number of arguments that haved been parsed

  /* we are in the child */
  if((a->pid = fork()) == 0)
  {
    /* set the child's stdin and stdout to use the pipes */
    dup2(a->from_parent, fileno(stdin));
    dup2(a->to_parent, fileno(stdout));
    dup2(a->to_parent, fileno(stderr));

    /* close all the unnecessary file descriptors */
    close(a->from_child);
    close(a->to_child);

    /* close all of the other agent's pipes and files */
    g_tree_foreach(agents, (GTraverseFunc)agent_close, a);

    /* if host is null, the agent will run locally to */
    /* run the agent localy, use the commands that    */
    /* were parsed when the meta_agent was created    */
    if(a->host == NULL)
    {
      execv(a->meta_data->parsed_cmd[0], a->meta_data->parsed_cmd);
    }
    /* otherwise the agent will be started using ssh   */
    /* if the agent is started using ssh we don't need */
    /* to fully parse the arguments, just pass the run */
    /* command as the last argument to the ssh command */
    else
    {
      args[0] = "/usr/bin/ssh";
      args[1] = a->host;
      args[2] = a->meta_data->raw_cmd;
      args[3] = 0;
      execv(args[0], args);
    }

    /* we should never reach here */
    THREAD_FATAL("exec failed");
  }
  /* we are in the parent */
  else if(a->pid > 0)
  {
    close(a->to_parent);
    close(a->from_parent);
    listen(a);
  }
  /* error case */
  else
  {
    THREAD_FATAL("exec failed");
  }
}

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
  memset(ma->parsed_cmd, NULL, sizeof(ma->parsed_cmd));

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
agent agent_init(meta_agent meta_data, char* host)
{
  /* local variables */
  agent a;
  int child_to_parent[2];
  int parent_to_child[2];

  /* allocate memory and do trivial assignments */
  a = (agent)calloc(1, sizeof(struct agent_internal));
  a->meta_data = meta_data;
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
  a->host = host;

  /* spawn the listen thread */
  pthread_create(&(*a)->thread, NULL, spawn, *a);
  return a;
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
  fclose(a->write);
  fclose(a->read);

  /* release the child process */
  waitpid(a->pid, &status, 0);
  free(a);
}

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

/**
 * TODO
 *
 * @param a
 */
void agent_fail(agent a)
{
  a->status = AG_FAILED;
  // TODO determine if anything else needs to happen here
}

