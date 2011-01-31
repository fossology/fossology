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
#include <event.h>
#include <logging.h>
#include <scheduler.h>

/* std library includes */
#include <stdlib.h>

/* other library includes */
#include <glib.h>

#define TEST_NULV(j) if(!j) { errno = EINVAL; ERROR("job passed is NULL, cannot proceed"); return; }
#define TEST_NULL(j, ret) if(!j) { errno = EINVAL; ERROR("job passed is NULL, cannot proceed"); return ret; }

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
struct job_internal
{
    char*  agent_type;      ///< the type of agent used to analyze the data
    GList* running_agents;  ///< the list of agents assigned to this job that are still working
    GList* finsihed_agents; ///< the list of agents that have successfully finish their task
    GList* failed_agents;   ///< the list of agents that failed while working
    char** data_begin;      ///< the list of data to be analyzed
    char** data_end;        ///< a pointer to one past the last valid datum
    char** curr;            ///< the current location in the data block
    int priority;           ///< importance of the job, currently only two types
    int verbose;            ///< the verbose level for all of the agents in this job
    int paused;             ///< if this job has been paused until further notice
    int id;                 ///< the identifier for this job
};

int job_id_gen = 0;
GTree* job_list = NULL;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

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
job job_init(char* type, char** data, int data_size)
{
  job j = (job)calloc(1, sizeof(struct job_internal));

  j->agent_type = type;
  j->running_agents =  NULL;
  j->finsihed_agents = NULL;
  j->failed_agents =   NULL;
  j->data_begin = data;
  j->data_end = data + data_size;
  j->curr = data;
  j->priority = 0;
  j->verbose = 0;
  j->paused = 0;
  j->id = job_id_gen++;

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
  for(j->curr = j->data_begin; j->curr && *j->curr; j->curr++)
  {
    free(*j->curr);
  }

  g_list_free(j->running_agents);
  g_list_free(j->finsihed_agents);
  g_list_free(j->failed_agents);

  free(j->data_begin);
  free(j);
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
  TEST_NULV(j);
  TEST_NULV(a);
  j->finsihed_agents = g_list_remove(j->finsihed_agents, a);
}

/**
 * Moves a job from the running agent list to the finished agent list.
 *
 * @param j the job that the agent belongs to
 * @param a the agent to move to the finished list
 */
void job_finish_agent(job j, void* a)
{
  TEST_NULV(j);
  TEST_NULV(a);
  j->running_agents  = g_list_remove(j->running_agents,  a);
  j->finsihed_agents = g_list_append(j->finsihed_agents, a);
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
 * Changes the priority of a job. Since all jobs are stated with the lowest possible
 * priority, a call to this function is required if a higher priority job is necessary.
 *
 * @param j the job to change the priority of
 * @param pri the new priority for the job
 */
void job_set_priority(job j, int pri)
{
  TEST_NULV(j);
  j->priority = pri;
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
  agent a;
  int restart = 0;

  TEST_NULV(j)
  if(!j->paused && j->running_agents == NULL)
  {
    if(j->failed_agents == NULL)
    {
      for(iter = j->finsihed_agents; iter != NULL; iter = iter->next)
        agent_close(iter->data);
    }
    else
    {
      for(iter = j->failed_agents; iter != NULL; iter = iter->next)
      {
        /* get a new agent to handle the data from the fail agent */
        if(j->finsihed_agents != NULL)
        {
          a = (agent)g_list_first(j->finsihed_agents);
          j->finsihed_agents = g_list_remove(j->finsihed_agents, a);
          j->running_agents  = g_list_append(j->running_agents,  a);
          agent_restart(a, (agent)iter->data);
          restart++;
        }
        else if(agent_copy((agent)iter->data) != NULL)
        {
          restart++;
        }

        /* get rid of the failed agent */
        a = iter->data;
        agent_close(a);
      }

      g_list_free(j->failed_agents);
      j->failed_agents = NULL;
    }

    if(restart == 0)
    {
      g_tree_remove(job_list, &j->id);
    }
  }
}

/**
 * Causes all agents that are working on the job to pause. This will simply cause
 * the scheduler to stop sending new information to the agents in question.
 *
 * @param j the job to pause
 */
void job_pause(job j)
{
  TEST_NULV(j);
  j->paused = 1;
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
  if(j->paused)
  {
    ERROR("attempt to restart job %d failed, job wasn't paused", j->id);
    return;
  }

  j->paused = 0;
  for(iter = j->running_agents; iter != NULL; iter = iter->next)
    agent_write(iter->data, "OK\n", 3);
}

/**
 * Gets the id number for the job.
 *
 * @param j the job to get the id of;
 */
int job_id(job j)
{
  TEST_NULL(j, -1);
  return j->id;
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
  return j->paused;
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
 * Gets the next block of data that needs to be analyzed. This function will make sure
 * that the next block is valid and if it isn't return NULL.
 *
 * @param j the job to get the data for
 * @return a pointer to the next block of data or NULL
 */
char** job_next(job j)
{
  char** ret;

  TEST_NULL(j, NULL);
  if(j->curr < j->data_end)
  {
    ret = j->curr;
    j->curr += CHECKOUT_SIZE;
    return ret;
  }

  return NULL;
}

/* ************************************************************************** */
/* **** Job list Functions ************************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
void check_list()
{
  if(job_list == NULL)
    job_list = g_tree_new_full(int_compare, NULL, NULL, (GDestroyNotify)job_destroy);
}

/**
 * TODO
 */
void job_list_clean()
{
  if(job_list != NULL)
  {
    g_tree_destroy(job_list);
    job_list = NULL;
  }
}

/**
 * TODO
 *
 * @param j
 */
void add_job(job j)
{
  TEST_NULV(j);
  check_list();
  g_tree_insert(job_list, &j->id, j);
}

/**
 * TODO
 *
 * @param id
 * @return
 */
job get_job(int id)
{
  check_list();
  return g_tree_lookup(job_list, &id);
}

/**
 * TODO
 *
 * @return
 */
int num_jobs()
{
  return g_tree_nnodes(job_list);
}



