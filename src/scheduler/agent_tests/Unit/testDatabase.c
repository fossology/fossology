/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test cases for db operations
 */

/* include functions to test */
#include <testRun.h>

/* scheduler includes */
#include <database.h>
#include <scheduler.h>

/* library includes */
#include <utils.h>

/* testing sql statements */
char sqltmp[1024] = {0};
extern char* check_scheduler_tables;
extern char* jobsql_processed;

/* ************************************************************************** */
/* **** database function tests ******************************************** */
/* ************************************************************************** */

/**
 * \brief Test for database_init()
 * \todo not complete
 * \test
 * -# Call database_init() with a scheduler
 * -# Check if the required tables with required columns are created
 */
void test_database_init()
{
  scheduler_t* scheduler;
  PGresult* db_result;
  GString* sql;

  scheduler = scheduler_init(testdb, NULL);
  database_init(scheduler);

  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  sprintf(sqltmp, check_scheduler_tables, PQdb(scheduler->db_conn));
  sql = g_string_new(sqltmp);
  g_string_append(sql, "'users';");

  /* get the url for the fossology instance */
  db_result = database_exec(scheduler, sql->str);
  //printf("sql: %s\n", sql->str);
  // TODO skip this test since the order reported here is random, also it will crash if PQntuples < 5
  #if 0
  if(PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) != 0)
  {
    //printf("result: %s\n",  g_strdup(PQgetvalue(db_result, 0, 0)));
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 0, 0)), "user_pk");
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 1, 0)), "user_name");
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 2, 0)), "root_folder_fk");
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 3, 0)), "user_desc");
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 4, 0)), "user_seed");
  }
  #endif
  PQclear(db_result);
  g_string_free(sql, TRUE);
  scheduler_destroy(scheduler);
}

/**
 * \brief Test for database_exec_event()
 * \test
 * -# Initialize database and call database_exec_event()
 */
void test_database_exec_event()
{
  scheduler_t* scheduler;
  gchar* sql = NULL;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  sql = g_strdup_printf(jobsql_processed, 0, 123);

  database_exec_event(scheduler, sql);
  scheduler_destroy(scheduler);
}

/**
 * \brief Test for database_update_event()
 * \test
 * -# Initialize test database
 * -# Call database_update_event()
 * -# Check if new jobs are added to the queue with proper names
 * -# Reset the queue
 */
void test_database_update_event()
{
  scheduler_t* scheduler;
  char sql[512];
  PGresult* db_result;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  Prepare_Testing_Data(scheduler);

  database_update_event(scheduler, NULL);
  sprintf(sql, "SELECT * FROM job;");
  db_result = database_exec(scheduler, sql);
  //printf("result: %s", PQget(db_result, 0, "job_name"));
  if(PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) != 0)
  {
    FO_ASSERT_STRING_EQUAL(PQget(db_result, 0, "job_name"), "testing file");
    FO_ASSERT_EQUAL(atoi(PQget(db_result, 0, "job_user_fk")), 1);
  }
  PQclear(db_result);

  database_reset_queue(scheduler);

  scheduler_destroy(scheduler);
}

/**
 * \brief Test for database_update_job()
 * \test
 * -# Initialize test database
 * -# Create a mock job
 * -# Check the job status
 * -# Call database_update_job() to update the job status
 * -# Check if the job status is not changed for the structure but updated in DB
 */
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

/**
 * \brief Test for database_job_processed(),database_job_log(),database_job_priority()
 * \test
 * -# Initialize test database
 * -# Create a mock job
 * -# Call database_job_processed() to update items processed
 * -# Call database_job_log() to create a test log
 * -# Call database_job_priority() to update job priority
 * \todo Add checks for function calls
 */
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

  database_job_processed(jq_pk, 2);
  database_job_log(jq_pk, "test log");
  database_job_priority(scheduler, job, 1);

  g_free(params);
  scheduler_destroy(scheduler);
}

/**
 * \brief Test for email_notification()
 * \test
 * -# Initialize scheduler, DB and email
 * -# Create a job and update status using database_update_job()
 * -# Check if job checkedout by email
 */
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
  job = job_init(scheduler->job_list, scheduler->job_queue, "ununpack", "localhost", -1, 0, 0, 0, 0, NULL);
  job->id = jq_pk;

  database_update_job(scheduler, job, JB_FAILED);
  FO_ASSERT_STRING_EQUAL(job_status_strings[job->status], "JOB_CHECKEDOUT");

  scheduler_destroy(scheduler);
}
/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_database[] =
{
    {"Test database_init",          test_database_init        },
    {"Test database_exec_event",    test_database_exec_event  },
    {"Test database_update_event",  test_database_update_event},
    {"Test database_update_job",    test_database_update_job  },
    {"Test database_job",           test_database_job         },
    CU_TEST_INFO_NULL
};

CU_TestInfo tests_email[] =
{
    {"Test email_notify",  test_email_notify  },
    CU_TEST_INFO_NULL
};




