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

/* library includes */
#include <fcntl.h>
#include <pthread.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#define MAX_ARGS 32     ///< the maximum number arguments passed to children (arbitrary)
#define MAX_CMD  1024   ///< the maximum of the length of a line             (arbitrary)
#define TILL_DEATH 180  ///< how long to wait before agent is dead           (3 minutes)

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/** internal structure for the meta agent */
struct meta_agent_internal
{
    /* information relating to creation of agent */
    char command[MAX_CMD + 1];  ///< command that will create this type of agent
    char* name[256];            ///< the name associated with this agent i.e. nomos, copyright...
    int max_run;                ///< the maximun number that can run at once -1 if no limit
};

/** internal structure for the agent. */
struct agent_internal
{
    /* we need all the informtion on creating the agent */
    meta_agent meta_data;   ///< the type of agent this is i.e. bucket, copyright...
    /* thread management */
    pthread_t thread;       ///< the thread that communicates with this agent
    pid_t pid;              ///< the pid of the process this agent is running in
    agent_status status;    ///< the state of execution the agent is currently in
    time_t check_in;        ///< the time that the agent last generated anything
    int analyzed_in;        ///< the number that had been analyzed at the last checkin
    /* pipes connecting to the child */
    int from_parent;        ///< file identifier to read from the parent (child stdin)
    int to_child;           ///< file identifier to print to the child
    int from_child;         ///< file identifier to read from child
    int to_parent;          ///< file identifier to print to the parent  (child stdout)
    /* data management */
    int data_start;         ///< the first file in the range that this agent is working on
    int data_end;           ///< the last file in the range that this agent is working on
};

/* ************************************************************************** */
/* **** Local Functions ***************************************************** */
/* ************************************************************************** */

/**
 * Listens for information from the agent. Starts by waiting for the agent to
 * send SPAWNED, then it will wait for any other information from the agent.
 * Information that it can recieve includes:
 *
 * CLOSED:        The agent has finished execution and is closing
 * 0xFFFFFFFF:    The agent has been killed by the scheduler, usually for lack of heart beat
 * FATAL %s:      The agent has hit an error that it cannot recover from
 * ERROR %s:      The agent has hut a non-fatal error and will continue to execute
 *                If an agent has actually died, the heart beat should take care of this
 * HEART %d:      registers the heart beat for the agent, this number provided should be increasing
 * UPDATE %d %d:  Updates the range of numbers that the agent is working on
 *
 * @param a
 */
void listen(agent a)
{
  /* locals */
  ssize_t bytes;            // the number of bytes read from the agent pipe
  char buffer[1024];        // buffer to store c strings read from agent, size is arbitrary
  char* inside_buffer;      // pointer used to point to locations within buffer
  int items_processed;      // used by the heart beat to log how many items have been processed

  /* initalize memory */
  memset(buffer, '\0', sizeof(buffer));
  bytes = 0;

  /* read SPAWNED from the agent, if not then fail the agent */
  bytes = read(a->from_child, buffer, sizeof(buffer));
  if(bytes < 0 || strcmp(buffer, "SPAWNED"))
  {
    a->status = AG_FAILED;
    return;
  }

  /* clean up memory to enter read loop */
  memset(buffer, '\0', sizeof(buffer));
  bytes = 0;

  /* enter the loop to listen to the agent */
  while((bytes = read(a->from_child, buffer, sizeof(buffer))) > 0)
  {
    /* the agent has finished and is closing */
    if(!strcmp(buffer, "CLOSED"))
    {
      a->status = AG_FINISHED;
      a->data_start = -1;
      a->data_end = -1;
      return;
    }
    /* scheduler has kill the agent */
    else if(*((unsigned int*)buffer) == 0xFFFFFFFF)
    {
      // TODO log that the agent died
      a->status = AG_FAILED;
      return;
    }
    /* the agent has hit a FATAL error */
    else if(!strncmp(buffer, "FATAL", 5))
    {
      // TODO log that the agent died
      a->status = AG_FAILED;
      return;
    }
    /* the agent has hit a non-FATAL error */
    else if(!strncmp(buffer, "ERROR", 5))
    {
      // TODO log the error and continue
    }
    /* the agent is registering a heart beat */
    else if(!strncmp(buffer, "HEART", 5))
    {
      items_processed = atoi(strchr(buffer, ' ') + 1);

      if(items_processed == a->analyzed_in && time(NULL) - a->check_in > TILL_DEATH)
      {
        // TODO log that the agent died
        a->status = AG_FAILED;
        return;
      }
      else
      {
        a->analyzed_in = items_processed;
        a->check_in = time(NULL);
      }
    }
    /* the agent has updated its file range */
    else if(!stdncmp(buffer, "UPDATE", 6))
    {
      a->data_start = atoi((inside_buffer = strchr(buffer, ' ') + 1));
      a->data_end =   atoi(strchr(inside_buffer, ' ') + 1);

      if(a->data_end == 0) {
        a->status = AG_FAILED;
        a->data_start = -1;
        a->data_end = -1;
      }
    }
    else
    {
      // TODO agent printed debuggin info, log it
    }
    memset(buffer, '\0', sizeof(buffer));
    bytes = 0;
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
 *   scheduler, parse the commnad line options for the agent and start the
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
  agent a = (agent)passed;  // the agent that is being spawned
  char* args[MAX_ARGS + 1]; // the arguments that will be passed to the child
  char* curr;               // a pointer to the current location in the argument array
  char buffer[MAX_CMD + 1]; // buffer to hold different c strings
  int num_args = 0;         // the number of arguments that haved been parsed

  /* we are in the child */
  if((a->pid = fork()) == 0)
  {
    /* set the child's stdin and stdout to use the pipes */
    dup2(a->from_parent, fileno(stdin));
    dup2(a->to_parent, fileno(stdout));
    dup2(a->to_parent, fileno(stderr));

    /* set from_parent and to_parent to exist after exec TODO error handling */
    fcntl(a->from_parent, F_SETFL, (fcntl(a->from_parent, F_GETFL) | FD_CLOEXEC) ^ FD_CLOEXEC);
    fcntl(a->to_parent, F_SETFL, (fcntl(a->to_parent, F_GETFL) | FD_CLOEXEC) ^ FD_CLOEXEC);

    /* TODO parse command to something legal for creation */
    memset(args, 0, sizeof(args));
    memset(buffer, '\0', sizeof(buffer));
    strcpy(buffer, a->meta_data->command);
    curr = strtok(buffer, " ");
    while(curr != NULL)
    {
      args[num_args++] = curr;
      curr = strtok(NULL, " ");
    }

    /* create the new process */
    execv(args[0], args);

    /* we should never reach here */
    fprintf(stderr, "FATAL: %s.%d: Exec failed\n", __FILE__, __LINE__);
    fprintf(stderr, "FATAL: command was: %s\n", a->meta_data->command);
    exit(-1);
  }
  /* we are in the parent */
  else if(a->pid > 0)
  {
    listen(a);
  }
  /* error case */
  else
  {
    fprintf(stderr, "FATAL: %s.%d: fork failed for agent ", __FILE__, __LINE__);
    fprintf(stderr, "FATAL: Command given was: %s", a->meta_data->command);
    exit(-1);
  }
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * TODO
 *
 * @param ma
 */
void meta_agent_init(meta_agent* ma)
{
  // TODO function stub
}

/**
 * TODO
 *
 * @param ma
 */
void meta_agent_destroy(meta_agent ma)
{
  // TODO function stub
}

/**
 * TODO
 *
 * @param a
 */
void agent_init(agent* a, meta_agent meta_data)
{
  int child_to_parent[2];
  int parent_to_child[2];

  /* allocate memory and do trivial assignments */
  (*a) = (agent)calloc(1, sizeof(struct agent_internal));
  (*a)->meta_data = meta_data;
  (*a)->status = AG_CREATED;

  /* create the pipes between the child and the parent */
  if(pipe2(parent_to_child, FD_CLOEXEC) != 0)
  {
    fprintf(stderr, "FATAL: %s.%d: failed to create parent to child pipe\n", __FILE__, __LINE__);
    exit(-1);
  }
  if(pipe2(child_to_parent, FD_CLOEXEC) != 0)
  {
    fprintf(stderr, "FATAL: %s.%d: failed to create child to parent pipe\n", __FILE__, __LINE__);
    exit(-1);
  }

  /* set file identifiers to correctly talk to children */
  (*a)->from_parent = parent_to_child[0];
  (*a)->to_child = parent_to_child[1];
  (*a)->from_child = child_to_parent[0];
  (*a)->to_parent = child_to_parent[1];

  /* spawn the listen thread */
  pthread_create(&(*a)->thread, NULL, spawn, *a);
  pthread_detach((*a)->thread);
}

/**
 * TODO
 *
 * @param a
 */
void agent_destroy(agent a)
{
  if(a->from_parent) { close(a->from_parent); }
  if(a->to_child) { close(a->to_child); }
  if(a->from_child) { close(a->from_child); }
  if(a->to_parent) { close(a->to_parent); }
  free(a);
}

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

void agent_fail(agent a)
{
  a->status = AG_FAILED;
  // TODO determine if anything else needs to happen here
}





