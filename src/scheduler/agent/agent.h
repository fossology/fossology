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

#ifndef AGENT_H_INCLUDE
#define AGENT_H_INCLUDE

/* local includes */
#include <host.h>
#include <job.h>

/* unix library includes */
#include <sys/types.h>

/* glib includes */
#include <gio/gio.h>
#include <glib.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

#define MAX_CMD  1023   ///< the maximum length for an agent's start command  (arbitrary)
#define MAX_NAME 255    ///< the maximum length for an agent's name           (arbitrary)
#define CHECK_TIME 120  ///< wait time between agent updates                  (arbitrary)

#define SAG_NONE      0x1   ///< There is nothing special about this agent
#define SAG_EXCLUSIVE 0x2   ///< This agent must not run at the same time as any other agent
#define SAG_NOEMAIL   0x4   ///< This agent should not send notification emails

/** Enum to keep track of the state of an agent */
typedef enum
{
  AG_FAILED = 0,  ///< AG_FAILED   The agent has failed during execution
  AG_CREATED = 1, ///< AG_CREATED  The agent has been allocated but is not running yet
  AG_SPAWNED = 2, ///< AG_SPAWNED  The agent has finished allocation but has registered work yet
  AG_RUNNING = 3, ///< AG_RUNNING  The agent has received a set of files to work on and is running
  AG_PAUSED = 4,  ///< AG_PAUSED   The agent is waiting either for new data or for processor time
  AG_CLOSED = 5   ///< AG_CLOSED   The agent has shut down, is no longer part of the system and should be destroyed
} agent_status;
extern const char* agent_status_strings[];

/**
 * Class to hold all of the information associated with creating a specific
 * type of agent.
 *
 * To create:
 *   meta_agent ma;
 *   meta_agent_init(&ma);
 *
 */
typedef struct meta_agent_internal* meta_agent;

/**
 * Class to hold all of the information associated with an agent.
 *
 * To create:
 *   agent a;
 *   agent_init(&a);
 */
typedef struct agent_internal* agent;

/**
 * TODO
 */
typedef int agent_pk;

/**
 * Internal declaration of private members for the meta_agent type. Meta agents
 * are used to store the information necessary to create a new agent of the same
 * type as the meta_agent.
 */
struct meta_agent_internal
{
    char name[256];             ///< the name associated with this agent i.e. nomos, copyright...
    char raw_cmd[MAX_CMD + 1];  ///< the raw command that will start the agent, used for ssh
    int max_run;                ///< the maximum number that can run at once -1 if no limit
    int special;                ///< any special condition associated with the agent
    char* version;              ///< the version of the agent that is running on all hosts
    int valid;                  ///< flag indicating if the meta_agent is valid
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
    int updated;          ///< boolean flag to indicate if the scheduler has updated the data
    int check_analyzed;   ///< the number that were analyzed between last heartbeats
    int total_analyzed;   ///< the total number that this agent has analyzed
    int return_code;      ///< what was returned by the agent when it disconnected
};

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/* meta agent */
void agent_list_clean();
void agent_list_init();
meta_agent meta_agent_init(char* name, char* cmd, int max, int spc);
void meta_agent_destroy(meta_agent ma);

/* agent */
agent agent_init(host host_machine, job owner);
void  agent_destroy(agent a);

/* ************************************************************************** */
/* **** Modifier Functions and events *************************************** */
/* ************************************************************************** */

void agent_death_event(pid_t* pids);
void agent_create_event(agent a);
void agent_ready_event(agent a);
void agent_update_event(void* unused);

void agent_transition(agent a, agent_status new_status);
void agent_pause(agent a);
void agent_unpause(agent a);
void agent_print_status(agent a, GOutputStream* ostr);
void agent_kill(agent a);
int  aprintf(agent a, const char* fmt, ...);
ssize_t agent_write(agent a, const void* buf, int count);

/* ************************************************************************** */
/* **** static functions and meta agents ************************************ */
/* ************************************************************************** */

void test_agents(host h);
void kill_agents(void);
void list_agents(GOutputStream* ostr);
int  add_meta_agent(char* name, char* cmd, int max, int spc);
int  is_meta_agent(char* name);
int  is_special(char* name, int special_type);
int  num_agents(void);

#endif /* AGENT_H_INCLUDE */
