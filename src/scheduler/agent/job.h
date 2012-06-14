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


#define JOB_STATUS_TYPES(apply)                        \
  apply(NOT_AVAILABLE)                                 \
  /** Checkout out from the db, but not started yet */ \
  apply(CHECKEDOUT)                                    \
  /** Agents have started analysis on the job */       \
  apply(STARTED)                                       \
  /** All the data for this job has been analyzed */   \
  apply(COMPLETE)                                      \
  /** FIXME NOT USED */                                \
  apply(RESTART)                                       \
  /** The job has failed, likely an agent failure */   \
  apply(FAILED)                                        \
  /** Paused by some user interface */                 \
  apply(PAUSED)

#define SELECT_ENUM(passed) JB_##passed,
typedef enum { JOB_STATUS_TYPES(SELECT_ENUM) } job_status;
#undef SELECT_ENUM

extern const char* job_status_strings[];

/**
 * Internal declaration of private members of the job structure.
 */
struct job_internal
{
    /* associated agent information */
    char*  agent_type;      ///< the type of agent used to analyze the data
    char*  required_host;   ///< If not NULL, this job must run on a specific host machine
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
job  job_init(char* type, char* host, int id, int priority);
void job_destroy(job j);

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

void job_verbose_event(job j);
void job_status_event(arg_int* params);
void job_pause_event(arg_int* params);
void job_restart_event(arg_int* params);
void job_priority_event(arg_int* params);

void job_add_agent(job j, void* a);
void job_remove_agent(job j, void* a);
void job_finish_agent(job j, void* a);
void job_fail_agent(job j, void* a);
void job_set_data(job j, char* data, int sql);
void job_update(job j);
void job_fail_event(job j);
int  job_is_paused(job j);
int  job_is_open(job j);
job  job_verbose(job j, int level);
char* job_message(job j);
char* job_next(job j);
FILE* job_log(job j);

/* ************************************************************************** */
/* **** Job list Functions ************************************************** */
/* ************************************************************************** */

job  next_job();
job  peek_job();
job  get_job(int id);
int  num_jobs();
int  active_jobs();

#endif /* JOB_H_INCLUDE */
