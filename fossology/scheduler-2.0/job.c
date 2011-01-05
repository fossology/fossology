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

/* std library includes */

/* other library includes */
#include <glib.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
struct job_internal
{
    char*  agent_type;  ///< the type of agent used to analyze the data
    GList* agents;      ///< the list of agents assigned to this job
    char** data_begin;  ///< the list of data to be analyzed
    char** data_end;    ///< a pointer to one past the last valid datum
    char** curr;        ///< the current location in the data block
    int priority;       ///< importance of the job, currently only two types
    int paused;         ///< if this job has been paused untill further notice
};

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * Create a new job. Every different task will create a new job and as a result
 * the job will only deal with one type of agent. This is important because if an
 * agent fails when processing data from a job, that job might need to create a
 * new agent to deal with the data.
 *
 * @param type the name of the type of agent (i.e. copyright, nomos, buckets...)
 * @param data the data that this job will process
 * @return the new job
 */
job job_init(char* type, char** data, int data_size)
{
  job j = (job)calloc(1, sizeof(struct job_internal));

  j->agent_type = type;
  j->agents = NULL;
  j->data_begin = data;
  j->data_end = data + data_size;
  j->curr = data;
  j->priority = 0;
  j->paused = 0;

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
  for(j->curr = j->data_begin; *j->curr; j->curr++)
  {
    free(*j->curr);
  }

  free(j->data_begin);
  free(j);
}

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

/**
 * Adds a new agent to the jobs list of agents. When a job is created it doesn't
 * contain any agents that can process its data. When an agent is ready, it will
 * add itself to the job using this function and begin processing the jobs data.
 *
 * @param j the job that the agent will be added to
 * @param a the agent to add to the job
 */
void job_add_agent(job j, agent a)
{
  j->agents = g_list_append(j->agents, a);
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
  GList* a;
  int finished = 1;

  if(!j->paused)
  {
    for(a = j->agents; a != NULL; a = a->next)
      if(((agent)a->data)->status != AG_PAUSED && ((agent)a->data)->status != AG_FAILED)
        finished = 0;

    if(finished)
    {
      for(a = j->agents; a != NULL; a = a->next)
      {
        if(((agent)a->data)->status != AG_FAILED)
        {

        }
      }
    }
  }
}

/**
 * TODO
 *
 * @param j
 */
void job_pause(job j)
{
  // TODO
}

/**
 * TODO
 *
 * @param j
 */
void job_restart(job j)
{
  // TODO
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
  /* locals */
  char** ret;

  if(j->curr < j->data_end)
  {
    ret = j->curr;
    j->curr += CHECKOUT_SIZE;
    return ret;
  }

  return NULL;
}

