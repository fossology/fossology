/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Job handling operations
 */

/* local includes */
#include <libfossrepo.h>
#include <agent.h>
#include <database.h>
#include <job.h>
#include <scheduler.h>

/* std library includes */
#include <stdlib.h>

/* unix library includes */
#include <sys/types.h>
#include <unistd.h>
#include <sys/time.h>
#include <sys/resource.h>

/* other library includes */
#include <glib.h>
#include <gio/gio.h>

#define TEST_NULV(j) if(!j) { errno = EINVAL; ERROR("job passed is NULL, cannot proceed"); return; }
#define TEST_NULL(j, ret) if(!j) { errno = EINVAL; ERROR("job passed is NULL, cannot proceed"); return ret; }
#define MAX_SQL 512;JOB_STATUS_TYPES

/* ************************************************************************** */
/* **** Locals ************************************************************** */
/* ************************************************************************** */

/**
 * Array of C-Strings used to pretty-print the job status in the log file.
 * Uses the X-Macro defined in @link job.h
 */
#define SELECT_STRING(passed) MK_STRING_LIT(JOB_##passed),
const char* job_status_strings[] = { JOB_STATUS_TYPES(SELECT_STRING) };
#undef SELECT_STRING

/**
 * @brief Tests if a job is active
 *
 * If the job is active, the integer pointed to by counter will be
 * incremented by 1. This is used when determining if the scheduler can shutdown
 * and will be called from within a g_tree_foreach().
 *
 * @param job_id  The id number used as the key in the Gtree
 * @param job     The job that is being tested for activity
 * @param counter The count of the number of active jobs
 * @return always returns 0
 */
static int is_active(int* job_id, job_t* job, int* counter)
{
  if((job->running_agents != NULL || job->failed_agents != NULL) || job->id < 0)
    ++(*counter);
  return 0;
}

/**
 * @brief Prints the jobs status to the output stream.
 *
 * The output will be in this format:
 *   job:<id> status:<status> type:<agent type> priority:<priority> running:<# running> finished:<#finished> failed:<# failed>
 *
 * @param job_id the id number that the job was created with
 *   @note if the int pointed to by the job_id is value 0, that means
 *         print all agent status as well
 * @param job  the job itself
 * @param ostr the output stream to write everything to
 * @return always returns 0
 */
static int job_sstatus(int* job_id, job_t* job, GOutputStream* ostr)
{
  gchar* status_str = g_strdup_printf(
      "job:%d status:%s type:%s, priority:%d running:%d finished:%d failed:%d\n",
      job->id,
      job_status_strings[job->status],
      job->agent_type,
      job->priority,
      g_list_length(job->running_agents),
      g_list_length(job->finished_agents),
      g_list_length(job->failed_agents));

  V_JOB("JOB_STATUS: %s", status_str);
  g_output_stream_write(ostr, status_str, strlen(status_str), NULL, NULL);

  if(*job_id == 0)
    g_list_foreach(job->running_agents, (GFunc)agent_print_status, ostr);

  g_free(status_str);
  return 0;
}

/**
 * Changes the status of the job and updates the database with the new job status
 *
 * @param scheduler   The scheduler that this job belongs to
 * @param job         The job to update the status on
 * @param new_status  the new status for the job
 */
static void job_transition(scheduler_t* scheduler, job_t* job, job_status new_status)
{
  /* book keeping */
  TEST_NULV(job);
  V_JOB("JOB[%d]: job status changed: %s => %s\n",
      job->id, job_status_strings[job->status], job_status_strings[new_status]);

  /* change the job status */
  job->status = new_status;

  /* only update database for real jobs */
  if(job->id >= 0)
    database_update_job(scheduler, job, new_status);
}

/**
 * @brief Used to compare two different jobs in the priority queue.
 *
 * This simply compares their priorities so that jobs with a high priority are
 * scheduler before low priority jobs.
 *
 * @param a       The first job
 * @param b       The second job
 * @param user_data  unused
 * @return        The comparison of the two jobs
 */
static gint job_compare(gconstpointer a, gconstpointer b, gpointer user_data)
{
  return ((job_t*)a)->priority - ((job_t*)b)->priority;
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * @brief Create a new job.
 *
 * Every different task will create a new job and as a result
 * the job will only deal with one type of agent. This is important because if an
 * agent fails when processing data from a job, that job might need to create a
 * new agent to deal with the data.
 *
 * @param job_list   The list of all jobs, the job will be added to this list
 * @param job_queue  The job queue, the job must be added to this for scheduling
 * @param type       The type of agent that will be created for this job
 * @param host       The name of the host that this job will execute on
 * @param id         The id number for the job in the database
 * @param parent_id  The parent id for the job in the database (queue)
 * @param user_id    The id of the user that created the job
 * @param group_id   The id of the group that created the job
 * @param priority   The priority of the job, this is just a Linux process priority
 * @param jq_cmd_args Command line arguments
 * @return the new job
 */
job_t* job_init(GTree* job_list, GSequence* job_queue,
    char* type, char* host, int id, int parent_id, int user_id, int group_id, int priority, char *jq_cmd_args)
{
  job_t* job = g_new0(job_t, 1);

  job->agent_type      = g_strdup(type);
  job->required_host   = g_strdup(host);
  job->running_agents  = NULL;
  job->finished_agents = NULL;
  job->failed_agents   = NULL;
  job->log             = NULL;
  job->status          = JB_CHECKEDOUT;
  job->data            = NULL;
  job->db_result       = NULL;
  job->lock            = NULL;
  job->idx             = 0;
  job->message         = NULL;
  job->upload_name     = NULL;
  job->priority        = priority;
  job->verbose         = 0;
  job->parent_id       = parent_id;
  job->id              = id;
  job->user_id         = user_id;
  job->group_id        = group_id;
  job->jq_cmd_args     = g_strdup(jq_cmd_args);

  g_tree_insert(job_list, &job->id, job);
  if(id >= 0) g_sequence_insert_sorted(job_queue, job, job_compare, NULL);
  return job;
}

/**
 * Free the memory associated with a job. In addition to the job needing to be
 * freed, the job owns the data associated with it so this must also free that
 * information.
 *
 * @param job the job to free
 */
void job_destroy(job_t* job)
{
  TEST_NULV(job);

  if(job->db_result != NULL)
  {
    SafePQclear(job->db_result);

    // Lock the mutex to prevent clearing locked mutex
    g_mutex_lock(job->lock);
    g_mutex_unlock(job->lock);
#if GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32
    g_mutex_clear(job->lock);
#else
    g_mutex_free(job->lock);
#endif
  }

  if(job->log)
    log_destroy(job->log);

  g_list_free(job->running_agents);
  g_list_free(job->finished_agents);
  g_list_free(job->failed_agents);
  g_free(job->message);
  g_free(job->upload_name);
  g_free(job->agent_type);
  g_free(job->required_host);
  g_free(job->data);
  if (job->jq_cmd_args) g_free(job->jq_cmd_args);
  g_free(job);
}

/* ************************************************************************** */
/* **** Events ************************************************************** */
/* ************************************************************************** */

/**
 * Causes the job to send its verbose level to all of the agents that belong to
 * it.
 *
 * @param scheduler  The scheduler to job belongs to
 * @param job        Update all verbose levels on the agents of this job
 */
void job_verbose_event(scheduler_t* scheduler, job_t* job)
{
  GList* iter;

  TEST_NULV(job);
  for(iter = job->running_agents; iter != NULL; iter = iter->next)
    aprintf(iter->data, "VERBOSE %d\n", job->verbose);
}

/**
 * @brief Event to get the status of the scheduler or a specific job.
 *
 * This is only generated by the interface receiving a status command. The
 * parameter for this function is a little odd since the function needs 2
 * parameters, but is an event, so can only be passed 1. Because of this, the
 * parameter is a pair, the first is always the g_output_stream to write the
 * status to. The second is either 0 (scheduler status) or the jq_pk of the job
 * that the status was requested for (job status).
 *
 * @param scheduler  the scheduler this event is called on
 * @param params     the g_output_stream and possibly the jq_pk of the job
 */
void job_status_event(scheduler_t* scheduler, arg_int* params)
{
  const char end[] = "end\n";
  GError* error = NULL;

  int tmp = 0;
  char buf[1024];

  if(!params->second)
  {
    memset(buf, '\0', sizeof(buf));
    sprintf(buf, "scheduler:%d revision:%s daemon:%d jobs:%d log:%s port:%d verbose:%d\n",
        scheduler->s_pid, fo_config_get(scheduler->sysconfig, "BUILD", "COMMIT_HASH", &error),
        scheduler->s_daemon, g_tree_nnodes(scheduler->job_list), main_log->log_name,
        scheduler->i_port, verbose);

    g_output_stream_write(params->first, buf, strlen(buf), NULL, NULL);
    g_tree_foreach(scheduler->job_list, (GTraverseFunc)job_sstatus, params->first);
  }
  else
  {
    job_t* stat = g_tree_lookup(scheduler->job_list, &params->second);
    if(stat)
    {
      job_sstatus(&tmp, g_tree_lookup(scheduler->job_list, &params->second), params->first);
    }
    else
    {
      sprintf(buf, "ERROR: invalid job id = %d\n", params->second);
      g_output_stream_write(params->first, buf, strlen(buf), NULL, NULL);
    }
  }

  g_output_stream_write(params->first, end, sizeof(end), NULL, NULL);
  g_free(params);
}

/**
 * @brief Event to pause a job.
 *
 * This event is created by the interface and like the
 * job status event uses the pair to pass multiple things to a event function.
 *
 * @param scheduler  the scheduler this event was called on
 * @param params     the job_t* and a boolean CLI or UI
 */
void job_pause_event(scheduler_t* scheduler, arg_int* params)
{
  job_t  tmp_job;
  job_t* job = params->first;
  GList* iter;

  // if the job doesn't exist, create a fake
  if(params->first == NULL)
  {
    tmp_job.id             = params->second;
    tmp_job.status         = JB_NOT_AVAILABLE;
    tmp_job.running_agents = NULL;
    tmp_job.message        = NULL;

    job = &tmp_job;
  }

  job_transition(scheduler, job, JB_PAUSED);
  for(iter = job->running_agents; iter != NULL; iter = iter->next)
    agent_pause(iter->data);

  g_free(params);
}

/**
 * Event to restart a paused job. This is called by the interface.
 *
 * @param scheduler  The scheduler this event was called on
 * @param params     The job that will be restarted
 */
void job_restart_event(scheduler_t* scheduler, arg_int* params)
{
  job_t  tmp_job;
  job_t* job = params->first;
  GList* iter;

  // if the job doesn't exist, create a fake
  if(job == NULL)
  {
    tmp_job.id             = params->second;
    tmp_job.status         = JB_PAUSED;
    tmp_job.running_agents = NULL;
    tmp_job.message        = NULL;

    event_signal(database_update_event, NULL);
    job = &tmp_job;
  }

  if(job->status != JB_PAUSED)
  {
    ERROR("attempt to restart job %d failed, job status was %s",
        job->id, job_status_strings[job->status]);
    g_free(params);
    return;
  }

  for(iter = job->running_agents; iter != NULL; iter = iter->next)
  {
    if(job->db_result != NULL) agent_write(iter->data, "OK\n", 3);
    agent_unpause(iter->data);
  }

  job_transition(scheduler, job, JB_RESTART);
  g_free(params);
}

/**
 * Event to change the priority of job. This is only called by the interface.
 * This event uses the pair to pass multiple things to an event function.
 *
 * @param scheduler  the scheduler this event was called on
 * @param params     the job and its new priority
 */
void job_priority_event(scheduler_t* scheduler, arg_int* params)
{
  GList* iter;

  database_job_priority(scheduler, params->first, params->second);
  ((job_t*)params->first)->priority = params->second;
  for(iter = ((job_t*)params->first)->running_agents; iter; iter = iter->next)
    setpriority(PRIO_PROCESS, ((agent_t*)iter->data)->pid, params->second);
  g_free(params);
}

/**
 * @brief Events that causes a job to be marked a failed.
 *
 * This really only needs to
 * call the job_transition function with JB_FAILED as that will change the job's
 * status in the database.
 *
 * @param scheduler Related scheduler structure
 * @param job       The job that has failed
 */
void job_fail_event(scheduler_t* scheduler, job_t* job)
{
  GList* iter;

  if(job->status != JB_FAILED)
    job_transition(scheduler, job, JB_FAILED);

  for(iter = job->running_agents; iter != NULL; iter = iter->next)
  {
    V_JOB("JOB[%d]: job failed, killing agents\n", job->id);
    agent_kill(iter->data);
  }
}

/* ************************************************************************** */
/* **** Functions *********************************************************** */
/* ************************************************************************** */

/**
 * @brief Adds a new agent to the jobs list of agents.
 *
 * When a job is created it doesn't
 * contain any agents that can process its data. When an agent is ready, it will
 * add itself to the job using this function and begin processing the jobs data.
 *
 * @param job    the job that the agent will be added to
 * @param agent  the agent to add to the job
 */
void job_add_agent(job_t* job, void* agent)
{
  TEST_NULV(job);
  TEST_NULV(agent);
  job->running_agents = g_list_append(job->running_agents, agent);
}

/**
 * Removes an agent from a jobs list of agents, if a job no longer has any agents
 * in any of it lists, this will then remove the job from the system.
 *
 * @param job       the job to remove the agent from
 * @param job_list  the list of all available jobs
 * @param agent     the agent to remove from the job
 */
void job_remove_agent(job_t* job, GTree* job_list, void* agent)
{
  GList* curr;
  TEST_NULV(job);

  if(job->finished_agents && agent)
    job->finished_agents = g_list_remove(job->finished_agents, agent);

  if(job->finished_agents == NULL && (job->status == JB_COMPLETE || job->status == JB_FAILED))
  {
    V_JOB("JOB[%d]: job removed from system\n", job->id);

    for(curr = job->running_agents; curr != NULL; curr = curr->next)
      ((agent_t*)curr->data)->owner = NULL;
    for(curr = job->failed_agents; curr != NULL; curr = curr->next)
      ((agent_t*)curr->data)->owner = NULL;
    for(curr = job->finished_agents; curr != NULL; curr = curr->next)
      ((agent_t*)curr->data)->owner = NULL;

    g_tree_remove(job_list, &job->id);
  }
}

/**
 * Moves an agent from the running agent list to the finished agent list.
 *
 * @param job    The job to change
 * @param agent  The agent to move
 */
void job_finish_agent(job_t* job, void* agent)
{
  TEST_NULV(job);
  TEST_NULV(agent);

  job->running_agents  = g_list_remove(job->running_agents,  agent);
  job->finished_agents = g_list_append(job->finished_agents, agent);
}

/**
 * Moves a job from the running agent list to the failed agent list.
 *
 * @param job    The job that the agent belong to
 * @param agent  The agent to move the failed list
 */
void job_fail_agent(job_t* job, void* agent)
{
  TEST_NULV(job);
  TEST_NULV(agent);
  job->running_agents  = g_list_remove(job->running_agents,  agent);
  job->failed_agents   = g_list_append(job->failed_agents,   agent);
}

/**
 * Sets the data that a job should be working on. Currently runonpfile is not
 * implemented, so sql will always be true.
 *
 * @param scheduler Scheduler containing database connection, used for runonpfile
 * @param job      the job to set the data for
 * @param data     the data that the job should be processing
 * @param sql      currently, always false
 * @todo runonpfile is not implemented
 */
void job_set_data(scheduler_t* scheduler, job_t* job, char* data, int sql)
{
  job->data = g_strdup(data);
  job->idx = 0;

  if(sql)
  {
    // TODO
    //j->db_result = PQexec(db_conn, j->data);
    //j->lock = g_mutex_new();
  }
}

/**
 * Updates the status of the job. This will check the status of all agents that belong
 * to this job and if the job has finished or all of the agents have fail
 *
 * @param scheduler
 * @param job
 */
void job_update(scheduler_t* scheduler, job_t* job)
{
  GList* iter;
  int finished = 1;

  TEST_NULV(job)

  for(iter = job->running_agents; iter != NULL; iter = iter->next)
    if(((agent_t*)iter->data)->status != AG_PAUSED)
      finished = 0;

  if(job->status != JB_PAUSED && job->status != JB_COMPLETE && finished)
  {
    if(job->failed_agents == NULL)
    {
      job_transition(scheduler, job, JB_COMPLETE);
      for(iter = job->finished_agents; iter != NULL; iter = iter->next)
      {
        aprintf(iter->data, "CLOSE\n");
      }
    }
    /* this indicates a failed agent */
    else
    {
      g_list_free(job->failed_agents);
      job->failed_agents = NULL;
      job->message = NULL;
      job_fail_event(scheduler, job);
    }
  }
}

/**
 * @brief Tests to see if there is still data available for this job
 *
 * @param scheduler
 * @param job the job to test
 * @return if the job still has data available
 */
int job_is_open(scheduler_t* scheduler, job_t* job)
{
  /* local */
  int retval = 0;

  TEST_NULL(job, -1);

  /* check to make sure that the job status is correct */
  if(job->status == JB_CHECKEDOUT)
    job_transition(scheduler, job, JB_STARTED);

  /* check to see if we even need to worry about sql stuff */
  if(job->db_result == NULL)
    return (job->idx == 0 && job->data != NULL);

  g_mutex_lock(job->lock);
  if(job->idx < PQntuples(job->db_result))
  {
    retval = 1;
  }
  else
  {
    SafePQclear(job->db_result);
    job->db_result = database_exec(scheduler, job->data);
    job->idx = 0;

    retval = PQntuples(job->db_result) != 0;
  }

  g_mutex_unlock(job->lock);
  return retval;
}

/**
 * Gets the next piece of data that should be analyzed, if there is no more data
 * to analyze, this will return NULL;
 *
 * @param job the job to get the data for
 * @return a pointer to the next block of data or NULL
 */
char* job_next(job_t* job)
{
  char* retval = NULL;

  TEST_NULL(job, NULL);
  if(job->db_result == NULL)
  {
    job->idx = 1;
    return job->data;
  }

  g_mutex_lock(job->lock);

  if(job->idx < PQntuples(job->db_result))
    retval = PQgetvalue(job->db_result, job->idx++, 0);

  g_mutex_unlock(job->lock);
  return retval;
}

/**
 * Gets the log file for the particular job. If the job hasn't had anything to
 * log yet, this will create the file from the repository and then return it.
 *
 * @param job the job to get the file for
 * @return the FILE* to print the job's log info
 */
log_t* job_log(job_t* job)
{
  FILE*  file;
  gchar* file_name;
  gchar* file_path;

  if(job->id < 0)
    return main_log;

  if(job->log)
    return job->log;

  file_name = g_strdup_printf("%06d", job->id);
  file_path = fo_RepMkPath("logs", file_name);

  if((file = fo_RepFwrite("logs", file_name)) == NULL)
  {
    ERROR("JOB[%d]: job unable to create log file: %s\n", job->id, file_path);
    g_free(file_name);
    free(file_path);
    return NULL;
  }

  V_JOB("JOB[%d]: job created log file:\n    %s\n", job->id, file_path);
  database_job_log(job->id, file_path);
  job->log = log_new_FILE(file, file_name, job->agent_type, 0);

  g_free(file_name);
  free(file_path);
  return job->log;
}

/* ************************************************************************** */
/* **** Job list Functions ************************************************** */
/* ************************************************************************** */

/**
 * @brief Gets the next job from the job queue.
 *
 * If there isn't a waiting in the job queue this will return NULL.
 *
 * @param job_queue The queue to get job from
 * @return the job or NULL
 */
job_t* next_job(GSequence* job_queue)
{
  job_t* retval = NULL;
  GSequenceIter* beg = g_sequence_get_begin_iter(job_queue);

  if(g_sequence_get_length(job_queue) != 0)
  {
    retval = g_sequence_get(beg);
    g_sequence_remove(beg);
  }

  return retval;
}

/**
 * @brief Gets the job that is at the top of the queue if there is one.
 *
 * @param job_queue The queue to get job from
 * @return the job at the top of the job queue, NULL if queue is empty
 */
job_t* peek_job(GSequence* job_queue)
{
  GSequenceIter* beg;

  if(g_sequence_get_length(job_queue) == 0)
  {
    return NULL;
  }

  beg = g_sequence_get_begin_iter(job_queue);
  return g_sequence_get(beg);
}

/**
 * @brief Gets the number of jobs that are not paused
 *
 * @param job_list The list to check
 * @return number of non-paused jobs
 */
uint32_t active_jobs(GTree* job_list)
{
  int count = 0;
  g_tree_foreach(job_list, (GTraverseFunc)is_active, &count);
  return count;
}

