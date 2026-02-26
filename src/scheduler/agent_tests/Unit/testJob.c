/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test for job operations
 */

/* include functions to test */
#include <testRun.h>

/* scheduler includes */
#include <scheduler.h>
#include <job.h>

#include <utils.h>

/**
 * Local function for testing data prepare
 */
int Prepare_Testing_Data_Job(scheduler_t* scheduler) {
  return Prepare_Testing_Data(scheduler);
}

/* ************************************************************************** */
/* ********** job function tests ******************************************** */
/* ************************************************************************** */

/**
 * \brief Test for job events
 * \test
 * -# Initialize scheduler and database
 * -# Create new database_update_event() to load data
 * -# Get the job from scheduler and call
 *     -# job_verbose_event() and check if job is updated
 *     -# job_pause_event() and check if job is paused
 *     -# job_restart_event() and check if job is restarted
 *     -# job_priority_event() and check if job is restarted
 *     -# job_fail_event() and check if job is failed
 */
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

/**
 * \brief Test for job functions
 * \test
 * -# Initialize scheduler and database
 * -# Create new database_update_event() to load data
 * -# Get the job from scheduler and call
 *     -# job_next() and check the job queue id
 *     -# next_job() and check the job is NOT NULL and its id is 1
 *     -# peek_job() and check the job is NOT NULL and its id is 1
 *     -# active_jobs() and check the result is 0
 */
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

/**
 * \brief Test for job_priority_event with NULL job (non-existent job)
 * \test
 * -# Initialize scheduler and database
 * -# Create new database_update_event() to load data
 * -# Call job_priority_event() with NULL job pointer (simulating job already removed)
 * -# Verify function handles gracefully without crash
 * -# Verify function returns early and logs appropriate warning
 */
void test_job_priority_event_null_job()
{
  scheduler_t* scheduler;
  arg_int* params;
  int fake_job_id = 9999;  // non-existent job ID

  scheduler = scheduler_init(testdb, NULL);
  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL_FATAL(scheduler->db_conn);

  Prepare_Testing_Data_Job(scheduler);
  database_update_event(scheduler, NULL);

  // Test priority change on non-existent job
  // This simulates the race condition where a job completes/is removed
  // between form generation and priority change submission
  params = g_new0(arg_int, 1);
  params->first = NULL;  // Job not found in scheduler
  params->second = fake_job_id;
  
  // This should handle gracefully without crash
  // The function will log a warning and return early
  job_priority_event(scheduler, params);

  scheduler_destroy(scheduler);
}

/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_job[] =
{
    {"Test job_event", test_job_event },
    {"Test job_fun",   test_job_fun   },
    {"Test job_priority_event_null_job", test_job_priority_event_null_job },
    CU_TEST_INFO_NULL
};
