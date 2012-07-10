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
#include <scheduler.h>

/* unix library includes */
#include <sys/types.h>

/* glib includes */
#include <gio/gio.h>
#include <glib.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

#define MAX_CMD     1023 ///< the size of the agent's command buffer (arbitrary)
#define MAX_NAME    255  ///< the size of the agent's name buffer    (arbitrary)
#define MAX_ARGS    32   ///< the size of the argument buffer        (arbitrary)
#define DEFAULT_RET -1   ///< default return code                    (arbitrary)

#define LOCAL_HOST "localhost"

#define SAG_NOKILL     (1 << 0) ///< This agent should not be killed when updating the agent
#define SAG_EXCLUSIVE  (1 << 1) ///< This agent must not run at the same time as any other agent
#define SAG_NOEMAIL    (1 << 2) ///< This agent should not send notification emails
#define SAG_LOCAL      (1 << 3) ///< This agent should only run on localhost

/**
 * Implementation of x macros used for the creation of the agent status enum.
 * This is used so that if a new agent status is needed, it can be added here
 * and all relevant declarations can be changed.
 *
 * If you are unfamiliar with X macros, this is a very simply implementation of
 * them and I suggest you look them up online.
 */
#define AGENT_STATUS_TYPES(apply)                                   \
  /** The agent failed during execution */                          \
  apply(FAILED)                                                     \
  /** The agent is allocated but not running yet */                 \
  apply(CREATED)                                                    \
  /** The agent has started running put not registered yet */       \
  apply(SPAWNED)                                                    \
  /** The agent is processing data */                               \
  apply(RUNNING)                                                    \
  /** The agent finished processing data and is waiting for more */ \
  apply(PAUSED)                                                     \
  /** The agent has shut down and is not longer in the system  */   \
  apply(CLOSED)                                                     \

/** Enum to keep track of the state of an agent */
#define SELECT_ENUM(passed) AG_##passed,
typedef enum { AGENT_STATUS_TYPES(SELECT_ENUM) } agent_status;
#undef SELECT_ENUM

extern const char* agent_status_strings[];

/**
 * TODO
 */
typedef int agent_pk;

/**
 * Internal declaration of private members for the meta_agent type. Meta agents
 * are used to store the information necessary to create a new agent of the same
 * type as the meta_agent.
 */
typedef struct
{
    char name[256];             ///< the name associated with this agent i.e. nomos, copyright...
    char raw_cmd[MAX_CMD + 1];  ///< the raw command that will start the agent, used for ssh
    int max_run;                ///< the maximum number that can run at once -1 if no limit
    int special;                ///< any special condition associated with the agent
    char* version_source;       ///< the machine that reported the version information
    char* version;              ///< the version of the agent that is running on all hosts
    int valid;                  ///< flag indicating if the meta_agent is valid
} meta_agent_t;

/**
 * Internal declaration of private members for the agent type. The agent type is
 * used to communicate with other the associated agent process. Holds host,
 * threading, status, pipes and data information relevant to what the process is
 * doing.
 */
typedef struct
{
    /* we need all the information on creating the agent */
    meta_agent_t* type; ///< the type of agent this is i.e. bucket, copyright...
    host_t*       host; ///< the host that this agent will start on

    /* thread management */
    agent_status status;    ///< the state of execution the agent is currently in
    GThread*     thread;    ///< the thread that communicates with this agent
    time_t       check_in;  ///< the time that the agent last generated anything
    uint8_t      n_updates; ///< keeps track of the number of times the agent has updated
    pid_t        pid;       ///< the pid of the process this agent is running in

    /* pipes connecting to the child */
    int from_parent;  ///< file identifier to read from the parent (child stdin)
    int to_child;     ///< file identifier to print to the child
    int from_child;   ///< file identifier to read from child
    int to_parent;    ///< file identifier to print to the parent  (child stdout)
    FILE* read;       ///< FILE* that abstracts the use of the from_child socket
    FILE* write;      ///< FILE* that abstracts the use of the to_child socket

    /* data management */
    job_t*   owner;           ///< the job that this agent is assigned to
    gchar*   data;            ///< the data that has been sent to the agent for analysis
    gboolean updated;         ///< boolean flag to indicate if the scheduler has updated the data
    uint64_t total_analyzed;  ///< the total number that this agent has analyzed
    gboolean alive;           ///< flag to tell the scheduler if the agent is still alive
    uint8_t  return_code;     ///< what was returned by the agent when it disconnected
    uint32_t special;         ///< any special flags that the agent has set
} agent_t;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/* meta agent */
meta_agent_t* meta_agent_init(char* name, char* cmd, int max, int spc);
void meta_agent_destroy(meta_agent_t* meta_agent);

/* agent */
agent_t* agent_init(scheduler_t* scheduler, host_t* host, job_t* owner);
void agent_destroy(agent_t* agent);

/* ************************************************************************** */
/* **** Modifier Functions and events *************************************** */
/* ************************************************************************** */

void agent_death_event(scheduler_t* scheduler, pid_t* pids);
void agent_create_event(scheduler_t* scheduler, agent_t* agent);
void agent_ready_event(scheduler_t* scheduler, agent_t* agent);
void agent_update_event(scheduler_t* scheduler, void* unused);
void agent_fail_event(scheduler_t* scheduler, agent_t* agent);
void list_agents_event(scheduler_t* scheduler, GOutputStream* ostr);

void agent_transition(agent_t* agent, agent_status new_status);
void agent_pause(agent_t* agent);
void agent_unpause(agent_t* agent);
void agent_print_status(agent_t* agent, GOutputStream* ostr);
void agent_kill(agent_t* agent);
int  aprintf(agent_t* agent, const char* fmt, ...);
ssize_t agent_write(agent_t* agent, const void* buf, int count);

/* ************************************************************************** */
/* **** static functions and meta agents ************************************ */
/* ************************************************************************** */

void test_agents(scheduler_t* scheduler);
int  add_meta_agent(GTree* meta_agents, char* name, char* cmd, int max, int spc);

void kill_agents(scheduler_t* scheduler);

int  is_meta_special(meta_agent_t* ma, int special_type);
int  is_agent_special(agent_t* agent, int special_type);

#endif /* AGENT_H_INCLUDE */
