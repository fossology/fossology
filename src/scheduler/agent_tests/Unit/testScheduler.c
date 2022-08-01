/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test for scheduler operations
 */

/* include functions to test */
#include <testRun.h>

/* scheduler includes */
#include <scheduler.h>
#include <utils.h>

/**
 * Local function for testing data prepare
 */
int Prepare_Testing_Data_Scheduler(scheduler_t * scheduler)
{
  return Prepare_Testing_Data(scheduler);
}


/* ************************************************************************** */
/* **** scheduler function tests ******************************************** */
/* ************************************************************************** */

/**
 * \brief Test for scheduler_sig_handle()
 * \test
 * -# Initialize scheduler and database
 * -# Call scheduler_sig_handle() and scheduler_signal()
 */
void test_scheduler_sig_handle()
{
  scheduler_t* scheduler;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  scheduler_sig_handle(1);
  scheduler_signal(scheduler);

  scheduler_destroy(scheduler);
}

/**
 * \brief Test for string_is_num()
 * \test
 * -# Call string_is_num() on a text, the return should be 0 (FALSE)
 * -# Call string_is_num() on a number, the return should be 1 (TRUE)
 */
void test_string_is_num()
{
  int res = 0;
  char* str = "a";
  char* str1 = "1";

  res = string_is_num(str);
  FO_ASSERT_EQUAL(res, 0);

  res = string_is_num(str1);
  FO_ASSERT_EQUAL(res, 1);
}

/**
 * \brief Test for scheduler_daemonize()
 * \test
 * -# Initialize scheduler and database
 * -# Call scheduler_daemonize(), the return should be OK
 * -# Call kill_scheduler(), the return should be -1
 */
void test_scheduler_daemonize()
{
  scheduler_t* scheduler;
  int res = 0;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  res = scheduler_daemonize(scheduler);
  FO_ASSERT_EQUAL(res, 0);

  res = kill_scheduler(1);
  FO_ASSERT_EQUAL(res, -1);

  scheduler_destroy(scheduler);
}

/**
 * \brief Test for scheduler_clear_config()
 * \test
 * -# Initialize scheduler and database
 * -# Call scheduler_clear_config()
 * -# Check for config pointers to be NULL
 */
void test_scheduler_clear_config()
{
  scheduler_t* scheduler;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  scheduler_clear_config(scheduler);
  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  FO_ASSERT_PTR_NULL(scheduler->host_queue);
  FO_ASSERT_PTR_NULL(scheduler->host_url);
  FO_ASSERT_PTR_NULL(scheduler->email_subject);
  FO_ASSERT_PTR_NULL(scheduler->sysconfig);

  scheduler_destroy(scheduler);
}
/*
void test_database_update_job()
{
  scheduler_t* scheduler;
  job_t* job;
  arg_int* params;
  int jq_pk;
  job_t tmp_job;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data(scheduler);

  params = g_new0(arg_int, 1);
  params->second = jq_pk;
  params->first = g_tree_lookup(scheduler->job_list, &params->second);
  job = params->first;
  if(params->first == NULL)
  {
    tmp_job.id             = params->second;
    tmp_job.status         = JB_NOT_AVAILABLE;
    tmp_job.running_agents = NULL;
    tmp_job.message        = NULL;

    job = &tmp_job;
  }

  FO_ASSERT_STRING_EQUAL(job_status_strings[job->status], "JOB_NOT_AVAILABLE");
  database_update_job(scheduler, job, JB_PAUSED);
  //job = g_tree_lookup(scheduler->job_list, &params->second);
  FO_ASSERT_STRING_EQUAL(job_status_strings[job->status], "JOB_NOT_AVAILABLE");

  g_free(params);
  scheduler_destroy(scheduler);
}

void test_database_job()
{
  scheduler_t* scheduler;
  job_t* job;
  arg_int* params;
  int jq_pk;
  job_t tmp_job;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data(scheduler);

  params = g_new0(arg_int, 1);
  params->second = jq_pk;
  params->first = g_tree_lookup(scheduler->job_list, &params->second);
  job = params->first;
  if(params->first == NULL)
  {
    tmp_job.id             = params->second;
    tmp_job.status         = JB_NOT_AVAILABLE;
    tmp_job.running_agents = NULL;
    tmp_job.message        = NULL;

    job = &tmp_job;
  }

  FO_ASSERT_STRING_EQUAL(job_status_strings[job->status], "JOB_NOT_AVAILABLE");

  printf("jq: %d\n", jq_pk);
  database_job_processed(jq_pk, 2);
  database_job_log(jq_pk, "test log");
  database_job_priority(scheduler, job, 1);

  g_free(params);
  scheduler_destroy(scheduler);
}

void test_email_notify()
{
  scheduler_t* scheduler;
  job_t* job;
  int jq_pk;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  email_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data(scheduler);
  job = job_init(scheduler->job_list, scheduler->job_queue, "ununpack", "localhost", -1, 0, 0, 0, NULL);
  job->id = jq_pk;

  database_update_job(scheduler, job, JB_FAILED);
  FO_ASSERT_STRING_EQUAL(job_status_strings[job->status], "JOB_CHECKEDOUT");

  scheduler_destroy(scheduler);
}
*/
/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_scheduler[] =
{
    {"Test scheduler_sig_handle",       test_scheduler_sig_handle       },
    {"Test string is number",       test_string_is_num       },
//TODO not working  {"Test scheduler_daemonize",       test_scheduler_daemonize       },
    {"Test scheduler_clear_config",       test_scheduler_clear_config       },
    CU_TEST_INFO_NULL
};




