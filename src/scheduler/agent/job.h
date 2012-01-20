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

#ifndef JOB_H_INCLUDE
#define JOB_H_INCLUDE

#include <stdio.h>
#include <event.h>
#include <libpq-fe.h>

/* glib includes */
#include <glib.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
typedef struct job_internal* job;

/**
 * TODO
 */
typedef enum
{
  JB_CHECKEDOUT = 0,    ///< JB_CHECKEDOUT
  JB_STARTED = 1,       ///< JB_STARTED
  JB_COMPLETE = 2,      ///< JB_COMPLETE
  JB_RESTART = 3,       ///< JB_RESTART
  JB_FAILED = 4,        ///< JB_FAILED
  JB_SCH_PAUSED = 5,    ///< JB_SCH_PAUSED
  JB_CLI_PAUSED = 6,    ///< JB_CLI_PAUSED
  JB_ERROR = 7          ///< JB_ERROR
} job_status;
extern const char* job_status_strings[];

/**
 * Internal declaraction of private members of the job strucutre.
 */
struct job_internal
{
    /* associated agent information */
    char*  agent_type;      ///< the type of agent used to analyze the data
    GList* running_agents;  ///< the list of agents assigned to this job that are still working
    GList* finished_agents; ///< the list of agents that have completed their tasks
    GList* failed_agents;   ///< the list of agents that failed while working
    FILE*  log;             ///< the log to print any agent logging messages to
    /* information for data manipluation */
    job_status status;      ///< the current status for the job
    char* data;             ///< the data associated with this job
    PGresult* db_result;    ///< results from the sql query (if any)
    GMutex* lock;           ///< lock to maintain data integrity
    int idx;                ///< the current index into the sql results
    /* information about job status */
    char* message;          ///< message that will be sent with job notification email
    int priority;           ///< importance of the job, maps directory to unix priority
    int verbose;            ///< the verbose level for all of the agents in this job
    int id;                 ///< the identifier for this job
};

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void job_list_init();
void job_list_clean();
job  job_init(char* type, int id, int priority);
void job_destroy(job j);

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

void job_verbose_event(job j);
void job_status_event(void* param);
void job_pause_event(void* param);
void job_restart_event(void* param);
void job_priority_event(arg_int* params);

void job_add_agent(job j, void* a);
void job_remove_agent(job j, void* a);
void job_finish_agent(job j, void* a);
void job_fail_agent(job j, void* a);
void job_set_data(job j, char* data, int sql);
void job_update(job j);
void job_fail_event(job j);
void job_set_message(job j, char* message);
int  job_id(job j);
int  job_priority(job j);
int  job_is_paused(job j);
int  job_is_open(job j);
job  job_verbose(job j, int level);
char* job_message(job j);
char* job_type(job j);
char* job_next(job j);
FILE* job_log(job j);

job_status job_get_status(job j);

/* ************************************************************************** */
/* **** Job list Functions ************************************************** */
/* ************************************************************************** */

job  next_job();
job  peek_job();
job  get_job(int id);
int  num_jobs();
int  active_jobs();

#endif /* JOB_H_INCLUDE */
