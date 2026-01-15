/*
 SPDX-FileCopyrightText: © 2010-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef JOB_H_INCLUDE
#define JOB_H_INCLUDE

/* local includes */
#include <logging.h>

/* std library includes */
#include <stdio.h>
#include <event.h>
#include <libpq-fe.h>

/* glib includes */
#include <glib.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

#define JOB_STATUS_TYPES(apply)                        \
  apply(NOT_AVAILABLE)                                 \
  /** Checkout out from the DB, but not started yet */ \
  apply(CHECKEDOUT)                                    \
  /** Agents have started analysis on the job */       \
  apply(STARTED)                                       \
  /** All the data for this job has been analyzed */   \
  apply(COMPLETE)                                      \
  /** @FIXME NOT USED */                               \
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
 * @brief The job structure
 */
typedef struct
{
    /* associated agent information */
    char*  agent_type;      ///< The type of agent used to analyze the data
    char*  required_host;   ///< If not NULL, this job must run on a specific host machine
    GList* running_agents;  ///< The list of agents assigned to this job that are still working
    GList* finished_agents; ///< The list of agents that have completed their tasks
    GList* failed_agents;   ///< The list of agents that failed while working
    log_t*  log;            ///< The log to print any agent logging messages to

    /* information for data manipulation */
    job_status status;    ///< The current status for the job
    gchar*     data;      ///< The data associated with this job (jq_args)
    gchar     *jq_cmd_args; ///< Command line arguments for this job
    PGresult*  db_result; ///< Results from the sql query (if any)
    GMutex*    lock;      ///< Lock to maintain data integrity
    uint32_t   idx;       ///< The current index into the sql results

    /* information about job status */
    gchar*   message;      ///< Message that will be sent with job notification email
    gchar*   upload_name;  ///< Name of the upload associated with this job (used for delagent)
    int32_t  priority;     ///< Importance of the job, maps directory to unix priority
    int32_t  verbose;      ///< The verbose level for all of the agents in this job
    int32_t  parent_id;    ///< The identifier for the parent of this job (its queue id)
    int32_t  id;           ///< The identifier for this job
    int32_t  user_id;      ///< The id of the user that created the job
    int32_t  group_id;     ///< The id of the group that created the job
} job_t;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

job_t* job_init(GTree* job_list, GSequence* job_queue, char* type, char* host,
    int id, int parent_id, int user_id, int group_id, int priority, char *jq_cmd_args);
void   job_destroy(job_t* job);

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

void job_verbose_event (scheduler_t* scheduler, job_t* j);
void job_status_event  (scheduler_t* scheduler, arg_int* params);
void job_pause_event   (scheduler_t* scheduler, arg_int* params);
void job_restart_event (scheduler_t* scheduler, arg_int* params);
void job_priority_event(scheduler_t* scheduler, arg_int* params);
void job_fail_event    (scheduler_t* scheduler, job_t* job);

void job_add_agent(job_t* job, void* a);
void job_remove_agent(job_t* job, GTree* job_list, void* a);
void job_finish_agent(job_t* job, void* a);
void job_fail_agent(job_t* job, void* a);
void job_set_data(scheduler_t* scheduler, job_t* job, char* data, int sql);
void job_update(scheduler_t* scheduler, job_t* job);

gboolean  job_is_open(scheduler_t* scheduler, job_t* job);
gchar*    job_next(job_t* job);
log_t*    job_log(job_t* job);

/* ************************************************************************** */
/* **** Job list Functions ************************************************** */
/* ************************************************************************** */

job_t*   next_job(GSequence* job_queue);
job_t*   peek_job(GSequence* job_queue);
uint32_t active_jobs(GTree* job_list);

#endif /* JOB_H_INCLUDE */
