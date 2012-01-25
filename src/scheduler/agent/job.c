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
#include <libfossrepo.h>
#include <agent.h>
#include <database.h>
#include <job.h>
#include <logging.h>
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
#define MAX_SQL 512;

/* ************************************************************************** */
/* **** Locals ************************************************************** */
/* ************************************************************************** */

/**
 * An array of c-strings to make it clearer when a job status is printed
 */
const char* job_status_strings[] = {
    "JOB_CHECKEDOUT",
    "JOB_STARTED",
    "JOB_COMPLETE",
    "JOB_RESTART",
    "JOB_FAILED",
    "JOB_SCH_PAUSED",
    "JOB_CLI_PAUSED",
    "\"ERROR: unknown status\""};

/** Map of jobs that are currently running, maps jq_pk to job struct */
GTree* job_list = NULL;

/** Heap of jobs that haven't been scheduled, heap is based on priority */
GSequence* job_queue = NULL;

/**
 * Tests if a job is active, if it is, the integer pointed to by counter will be
 * incremented by 1. This is used when determining if the scheduler can shutdown
 * and will be called from within a g_tree_foreach().
 *
 * @param job_id the id number used as the key in the Gtree
 * @param j the job that is being tested for activity
 * @param counter the count of the number of active jobs
 * @return always returns 0
 */
int is_active(int* job_id, job j, int* counter)
{
  if((j->running_agents != NULL || j->failed_agents != NULL) || j->id < 0)
    ++(*counter);
  return 0;
}

/**
 * Prints the jobs status to the output stream. The output will be in this
 * format:
 *   job:<id> status:<status> type:<agent type> priority:<priority> running:<# running> finished:<#finished> failed:<# failed>
 *
 * @param job_id the id number that the job was created with
 *   @note if the int pointed to by the job_id is value 0, that means
 *         print all agent status as well
 * @param j the job itself
 * @param ostr the output stream to write everything to
 * @return always returns 0
 */
int job_sstatus(int* job_id, job j, GOutputStream* ostr)
{
  gchar* status_str = g_strdup_printf("job:%d status:%s type:%s, priority:%d running:%d finished:%d failed:%d\n",
      j->id,
      job_status_strings[j->status],
      j->agent_type,
      j->priority,
      g_list_length(j->running_agents),
      g_list_length(j->finished_agents),
      g_list_length(j->failed_agents));

  V_JOB("JOB_STATUS: %s", status_str);
  g_output_stream_write(ostr, status_str, strlen(status_str), NULL, NULL);

  if(*job_id == 0)
  {
    g_list_foreach(j->running_agents, (GFunc)agent_print_status, ostr);
    g_list_foreach(j->failed_agents, (GFunc)agent_print_status, ostr);
  }

  g_free(status_str);
  return 0;
}

/**
 * Changes the status of the job and updates the database with the new job status
 *
 * @param j the job to update the status on
 * @param new_status the new status for the job
 */
void job_transition(job j, job_status new_status)
{
  /* book keeping */
  TEST_NULV(j);
  V_JOB("JOB[%d]: job status changed: %s => %s\n",
      j->id, job_status_strings[j->status], job_status_strings[new_status]);

  /* change the job status */
  j->status = new_status;

  /* only update database for real jobs */
  if(j->id >= 0)
    database_update_job(j, new_status);
}

/**
 * Causes all agents that are working on the job to pause. This will simply cause
 * the scheduler to stop sending new information to the agents in question.
 *
 * @param j the job to pause
 */
void job_pause(job j, int cli)
{
  GList* iter;

  TEST_NULV(j);

  if(cli) job_transition(j, JB_CLI_PAUSED);
  else job_transition(j, JB_SCH_PAUSED);

  for(iter = j->running_agents; iter != NULL; iter = iter->next)
    agent_pause(iter->data);
}

/**
 * Restart the agents that are working on this job. This will cause the scheduler
 * to start sending information to the agents again.
 *
 * @param j the job to restart
 */
void job_restart(job j)
{
  GList* iter;

  TEST_NULV(j);
  if(j->status != JB_SCH_PAUSED && j->status != JB_CLI_PAUSED)
  {
    ERROR("attempt to restart job %d failed, job status was %s", j->id, job_status_strings[j->status]);
    return;
  }

  for(iter = j->running_agents; iter != NULL; iter = iter->next)
  {
    if(j->db_result != NULL) agent_write(iter->data, "OK\n", 3);
    agent_unpause(iter->data);
  }
  job_transition(j, JB_STARTED);
}

/**
 * Used to compare two different jobs in the priority queue. This simply compares
 * their priorities so that jobs with a high priority are scheduler before low
 * priority jobs.
 *
 * @param a the first job
 * @param b the second job
 * @param user_data unused
 * @return the comparison of the two jobs
 */
gint job_compare(gconstpointer a, gconstpointer b, gpointer user_data)
{
  return ((job)a)->priority - ((job)b)->priority;
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * Creates the job_list map and the job_queue heaps
 */
void job_list_init()
{
  job_list = g_tree_new_full(int_compare, NULL, NULL, (GDestroyNotify)job_destroy);
  job_queue = g_sequence_new(NULL);
}

/**
 * Cleans the list and queue. This clears all entries from both
 */
void job_list_clean()
{
  if(job_list) g_tree_destroy(job_list);
  if(job_queue != NULL) g_sequence_free(job_queue);
  job_list_init();
}

/**
 * Create a new job. Every different task will create a new job and as a result
 * the job will only deal with one type of agent. This is important because if an
 * agent fails when processing data from a job, that job might need to create a
 * new agent to deal with the data.
 *
 * @param id the id number for this job
 * @param type the name of the type of agent (i.e. copyright, nomos, buckets...)
 * @param data the data that this job will process
 * @param data_size the number of elements in the data array
 * @return the new job
 */
job job_init(char* type, int id, int priority)
{
  job j = g_new0(struct job_internal, 1);

  j->agent_type      = g_strdup(type);
  j->running_agents  = NULL;
  j->finished_agents = NULL;
  j->failed_agents   = NULL;
  j->log             = NULL;
  j->status          = JB_CHECKEDOUT;
  j->data            = NULL;
  j->db_result       = NULL;
  j->lock            = NULL;
  j->idx             = 0;
  j->message         = NULL;
  j->priority        = priority;
  j->verbose         = 0;
  j->id              = id;

  g_tree_insert(job_list, &j->id, j);
  if(id >= 0) g_sequence_insert_sorted(job_queue, j, job_compare, NULL);
  return j;
}

/**
 * Free the memory associated with a job. In addition to the job needing to be
 * freed, the job owns the data associated with it so this must also free that
 * information.
 *
 * @param j the job to free
 */
void job_destroy(job j)
{
  TEST_NULV(j);

  if(j->db_result != NULL)
  {
    PQclear(j->db_result);
    g_mutex_free(j->lock);
  }

  if(j->log)
  {
    fclose(j->log);
  }

  g_list_free(j->running_agents);
  g_list_free(j->finished_agents);
  g_list_free(j->failed_agents);
  g_free(j->message);
  g_free(j->agent_type);
  g_free(j->data);
  g_free(j);
}

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

/**
 * Causes the job to send its verbose level to all of the agents that belong to
 * it.
 *
 * @param j the job that needs to update the verbose level of its agents
 */
void job_verbose_event(job j)
{
  GList* iter;

  TEST_NULV(j);
  for(iter = j->running_agents; iter != NULL; iter = iter->next)
    aprintf(iter->data, "VERBOSE %d\n", j->verbose);
}

/**
 * Event to get the status of the scheduler or a specific job. This is only
 * generated by the interface receiving a status command. The parameter for this
 * function is a little odd since the function needs 2 parameters, but is an
 * event, so can only be passed 1. Because of this, the parameter is a pair, the
 * first is always the g_output_stream to write the status to. The second is
 * either 0 (scheduler status) or the jq_pk of the job that the status was
 * requested for (job status).
 *
 * @param ostr a pair of the g_output_stream and possible the jq_pk
 */
void job_status_event(arg_int* params)
{
  const char end[] = "end\n";
  GError* error = NULL;

  int tmp = 0;
  char buf[1024];

  if(!params->second)
  {
    memset(buf, '\0', sizeof(buf));
    sprintf(buf, "scheduler:%d revision:%s daemon:%d jobs:%d log:%s port:%d verbose:%d\n",
        s_pid, fo_config_get(sysconfig, "VERSION", "SVN_REV", &error),
        num_jobs(), s_daemon, log_name, s_port, verbose);

    g_output_stream_write(params->first, buf, strlen(buf), NULL, NULL);
    g_tree_foreach(job_list, (GTraverseFunc)job_sstatus, params->first);
  }
  else
  {
    job stat = g_tree_lookup(job_list, &params->second);
    if(stat)
    {
      job_sstatus(&tmp, g_tree_lookup(job_list, &params->second), params->first);
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
 * Event to pause a job. This event is created by the interface and like the
 * job status event uses the pair to pass multiple things to a event function.
 *
 * @param params  first is the job, second is if the ui or cli paused the job
 */
void job_pause_event(arg_int* params)
{
  job_pause(params->first, params->second);
  g_free(params);
}

/**
 * Event to restart a paused job. This is called by the interface.
 *
 * @param j  the job that should be restarted.
 */
void job_restart_event(job j)
{
  job_restart(j);
}

/**
 * Event to change the priority of job. This is only called by the interface.
 * This event uses the pair to pass multiple things to an event function.
 *
 * @param params  first is the job, second is the new priority.
 */
void job_priority_event(arg_int* params)
{
  GList* iter;

  database_job_priority(params->first, params->second);
  ((job)params->first)->priority = params->second;
  for(iter = ((job)params->first)->running_agents; iter; iter = iter->next)
    setpriority(PRIO_PROCESS, ((agent)iter->data)->pid, params->second);
  g_free(params);
}

/**
 * Adds a new agent to the jobs list of agents. When a job is created it doesn't
 * contain any agents that can process its data. When an agent is ready, it will
 * add itself to the job using this function and begin processing the jobs data.
 *
 * @param j the job that the agent will be added to
 * @param a the agent to add to the job
 */
void job_add_agent(job j, void* a)
{
  TEST_NULV(j);
  TEST_NULV(a);
  j->running_agents = g_list_append(j->running_agents, a);
}

/**
 * Removes an agent from a jobs list of agents, if a job no longer has any agents
 * in any of it lists, this will then remove the job from the system.
 *
 * @param j the job to remove the agent from
 * @param a the agent to remove from the job
 */
void job_remove_agent(job j, void* a)
{
  GList* curr;
  TEST_NULV(j);

  if(j->finished_agents && a)
    j->finished_agents = g_list_remove(j->finished_agents, a);

  if(j->finished_agents == NULL && (j->status == JB_COMPLETE || j->status == JB_FAILED))
  {
    V_JOB("JOB[%d]: job removed from system\n", j->id);

    for(curr = j->running_agents; curr != NULL; curr = curr->next)
      ((agent)curr->data)->owner = NULL;
    for(curr = j->failed_agents; curr != NULL; curr = curr->next)
      ((agent)curr->data)->owner = NULL;
    for(curr = j->finished_agents; curr != NULL; curr = curr->next)
      ((agent)curr->data)->owner = NULL;

    g_tree_remove(job_list, &j->id);
  }
}

/**
 * Moves an agent from the running agent list to the finished agent list.
 *
 * @param j the job to change
 * @param a the agent to move
 */
void job_finish_agent(job j, void* a)
{
  TEST_NULV(j);
  TEST_NULV(a);

  j->running_agents  = g_list_remove(j->running_agents,  a);
  j->finished_agents = g_list_append(j->finished_agents, a);
}

/**
 * Moves a job from the running agent list to the failed agent list.
 *
 * @param j the job that the agent belong to
 * @param a the agent to move the failed list
 */
void job_fail_agent(job j, void* a)
{
  TEST_NULV(j);
  TEST_NULV(a);
  j->running_agents  = g_list_remove(j->running_agents,  a);
  j->failed_agents   = g_list_append(j->failed_agents,   a);
}

/**
 * Sets the data that a job should be working on. Currently runonpfile is not
 * implemented, so sql will always be true.
 *
 *
 * @param j     the job to set the data for
 * @param data  the data that the job should be processing
 * @param sql   currently, always false
 */
void job_set_data(job j, char* data, int sql)
{
  j->data = g_strdup(data);
  j->idx = 0;

  if(sql)
  {
    // TODO
    //j->db_result = PQexec(db_conn, j->data);
    //j->lock = g_mutex_new();
  }
}

/**
 * updates the status of the job. This will check the status of all agents that belong
 * to this job and if the job has finished or all of the agents have fail
 *
 * @param j
 */
void job_update(job j)
{
  GList* iter;
  int finished = 1;

  TEST_NULV(j)

  for(iter = j->running_agents; iter != NULL; iter = iter->next)
    if(((agent)iter->data)->status != AG_PAUSED)
      finished = 0;

  if(j->status != JB_SCH_PAUSED && j->status != JB_CLI_PAUSED &&
     j->status != JB_COMPLETE && finished)
  {
    if(j->failed_agents == NULL)
    {
      job_transition(j, JB_COMPLETE);
      for(iter = j->finished_agents; iter != NULL; iter = iter->next)
      {
        aprintf(iter->data, "CLOSE\n");
      }
    }
    /* this indicates a failed agent */
    else
    {
      g_list_free(j->failed_agents);
      j->failed_agents = NULL;
      j->message = NULL;
      job_fail_event(j);
    }
  }
}

/**
 * Events that causes a job to be marked a failed. This really only needs to
 * call the job_transition function with JB_FAILED as that will change the job's
 * status in the database.
 *
 * @param j  the job that has failed
 */
void job_fail_event(job j)
{
  GList* iter;

  if(j->status != JB_FAILED)
    job_transition(j, JB_FAILED);

  for(iter = j->running_agents; iter != NULL; iter = iter->next)
  {
    agent_kill(iter->data);
  }
}

/**
 * Checks if the job is paused
 *
 * @param j the job to check
 * @return true if it is paused, false otherwise
 */
int job_is_paused(job j)
{
  TEST_NULL(j, -1);
  return j->status == JB_SCH_PAUSED || j->status == JB_CLI_PAUSED;
}

/**
 * Tests to see if there is still data available for this job
 *
 * @param j the job to test
 * @return if the job still has data available
 */
int job_is_open(job j)
{
  /* local */
  int retval = 0;

  if(j == NULL)
    return -1;

  /* check to make sure that the job status is correct */
  if(j->status == JB_CHECKEDOUT)
    job_transition(j, JB_STARTED);

  /* check to see if we even need to worry about sql stuff */
  if(j->db_result == NULL)
    return (j->idx == 0 && j->data != NULL);

  g_mutex_lock(j->lock);
  if(j->idx < PQntuples(j->db_result))
  {
    retval = 1;
  }
  else
  {
    PQclear(j->db_result);
    j->db_result = PQexec(db_conn, j->data);
    j->idx = 0;

    retval = PQntuples(j->db_result) != 0;
  }

  g_mutex_unlock(j->lock);
  return retval;
}

/**
 * Changes and returns the verbose level for this job.
 *
 * @param j the job to change the verbose on
 * @param level the level of verbose to set all the agents to
 * @return the new verbose level of the job
 */
job job_verbose(job j, int level)
{
  TEST_NULL(j, NULL);
  j->verbose = level;
  return j;
}

/**
 * Gets the message that will be sent with the job notification email
 *
 * @param j the job to get the message for
 * @return the message
 */
char* job_message(job j)
{
  TEST_NULL(j, "");
  return j->message == NULL ? "" : j->message;
}

/**
 * Gets the next piece of data that should be analyzed, if there is no more data
 * to analyze, this will return NULL;
 *
 * @param j the job to get the data for
 * @return a pointer to the next block of data or NULL
 */
char* job_next(job j)
{
  char* retval = NULL;

  TEST_NULL(j, NULL);
  if(j->db_result == NULL)
  {
    j->idx = 1;
    return j->data;
  }

  g_mutex_lock(j->lock);

  if(j->idx < PQntuples(j->db_result))
    retval = PQgetvalue(j->db_result, j->idx++, 0);

  g_mutex_unlock(j->lock);
  return retval;
}

/**
 * Gets the log file for the particular job. If the job hasn't had anything to
 * log yet, this will create the file from the repository and then return it.
 *
 * @param j the job to get the file for
 * @return the FILE* to print the job's log infot o
 */
FILE* job_log(job j)
{
  char  file_name[7];
  char* file_path;

  if(j->id < 0)
    return NULL;

  if(j->log)
    return j->log;

  snprintf(file_name, sizeof(file_name), "%06d", j->id);
  file_path = fo_RepMkPath("logs", file_name);
  V_JOB("JOB[%d]: job created log file:\n    %s\n", j->id, file_path);

  database_job_log(j->id, file_path);
  j->log = fo_RepFwrite("logs", file_name);
  free(file_path);
  return j->log;
}

/* ************************************************************************** */
/* **** Job list Functions ************************************************** */
/* ************************************************************************** */

/**
 * Gets the next job from the job queue. If there isn't a waiting in the job
 * queue this will return NULL.
 *
 * @return the job or NULL
 */
job next_job()
{
  job retval = NULL;
  GSequenceIter* beg = g_sequence_get_begin_iter(job_queue);

  if(g_sequence_get_length(job_queue) != 0)
  {
    retval = g_sequence_get(beg);
    g_sequence_remove(beg);
  }

  return retval;
}

/**
 * Gets the job that is at the top of the queue if there is one
 *
 * @return the job at the top of the job queue, NULL if queue is empty
 */
job peek_job()
{
  GSequenceIter* beg = g_sequence_get_begin_iter(job_queue);

  if(g_sequence_get_length(job_queue) == 0)
    return NULL;

  return g_sequence_get(beg);
}

/**
 * get a job based upon the job's id number.
 *
 * @param id
 * @return
 */
job get_job(int id)
{
  return g_tree_lookup(job_list, &id);
}

/**
 * gets the number of jobs that are currently registers to the scheduler
 *
 * @return integer representing number of currently known jobs
 */
int num_jobs()
{
  return g_tree_nnodes(job_list);
}

/**
 * gets the number of jobs that are not paused
 *
 * @return number of non-paused jobs
 */
int active_jobs()
{
  int count = 0;
  g_tree_foreach(job_list, (GTraverseFunc)is_active, &count);
  return count;
}

