/*********************************************************************
Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
*********************************************************************/

/* include functions to test */
#include <testRun.h>

/* scheduler includes */
#include <scheduler.h>
#include <job.h>

#include <utils.h>


int Prepare_Testing_Data_Job(scheduler_t* scheduler) {
  return Prepare_Testing_Data(scheduler);
}

/* ************************************************************************** */
/* **** job function tests ******************************************** */
/* ************************************************************************** */
void test_job_event()
{
  scheduler_t* scheduler;
  job_t* job;
  arg_int* params;
  int jq_pk;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL_FATAL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data_Job(scheduler);

  database_update_event(scheduler, NULL);

  job = g_tree_lookup(scheduler->job_list, &jq_pk);
  FO_ASSERT_PTR_NOT_NULL_FATAL(job);
  job_verbose_event(scheduler, job);
  FO_ASSERT_EQUAL(job->id, jq_pk);

  params = g_new0(arg_int, 1);
  params->first = job;
  params->second = jq_pk;
  job_pause_event(scheduler, params);
  FO_ASSERT_EQUAL(job->status, JB_PAUSED);

  params = g_new0(arg_int, 1);
  params->first = job;
  params->second = jq_pk;
  job_restart_event(scheduler, params);
  FO_ASSERT_EQUAL(job->status, JB_RESTART);
  
  params = g_new0(arg_int, 1);
  params->first = job;
  params->second = 1;
  job_priority_event(scheduler, params);
  FO_ASSERT_EQUAL(job->status, JB_RESTART);

  job_fail_event(scheduler, job);
  FO_ASSERT_EQUAL(job->status, JB_FAILED);
  
  scheduler_update(scheduler);

  scheduler_destroy(scheduler);
}

void test_job_fun()
{
  scheduler_t* scheduler;
  job_t* job;
  char* res = NULL;
  uint32_t result = 0;
  int jq_pk;

  scheduler = scheduler_init(testdb, NULL);
  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data_Job(scheduler);

  database_update_event(scheduler, NULL);
  
  job = g_tree_lookup(scheduler->job_list, &jq_pk);
  FO_ASSERT_PTR_NOT_NULL_FATAL(job);

  res = job_next(job);
  FO_ASSERT_STRING_EQUAL(res, "6");
  job = next_job(scheduler->job_queue);
  FO_ASSERT_PTR_NOT_NULL_FATAL(job);
  FO_ASSERT_EQUAL(job->id, 1);
  job = peek_job(scheduler->job_queue);
  FO_ASSERT_PTR_NOT_NULL_FATAL(job);
  FO_ASSERT_EQUAL(job->id, 1);
  result = active_jobs(scheduler->job_list);
  FO_ASSERT_EQUAL(result, 0);

  scheduler_destroy(scheduler);
}

/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_job[] =
{
    {"Test job_event",          test_job_event          },
    {"Test job_fun",          test_job_fun          },
    CU_TEST_INFO_NULL
};
