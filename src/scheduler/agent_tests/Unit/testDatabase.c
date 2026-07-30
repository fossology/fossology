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
  sprintf(sql, "SELECT * FROM job WHERE job_name = 'testing file' ORDER BY job_pk DESC LIMIT 1;");
  db_result = database_exec(scheduler, sql);
  if(PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) != 0)
  {
    FO_ASSERT_STRING_EQUAL(PQget(db_result, 0, "job_name"), "testing file");
    FO_ASSERT_NOT_EQUAL(atoi(PQget(db_result, 0, "job_user_fk")), 0);
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
 * \brief Test that database_update_event() excludes already-known jobs via
 *        the NOT IN clause.
 *
 * A host with max=1 forces checkout_limit=1 (LIMIT=1 per poll). Two jobs are
 * created: job1 at high priority (returned first) and job2 at low priority.
 * After the first poll loads job1, the second poll must load job2 -- which is
 * only possible if job1 is excluded by the NOT IN clause. Without the clause
 * job1 (jq_starttime IS NULL) would re-occupy the sole slot and job2 would
 * never be fetched.
 * \test
 * -# Add a host with max=1 to constrain LIMIT to 1 per poll
 * -# Insert job1 (priority 9999) and job2 (priority 0)
 * -# First poll: job1 loaded (highest priority wins LIMIT=1)
 * -# Second poll: job1 excluded by NOT IN → job2 loaded (fix validated)
 */
void test_database_update_event_excludes_known()
{
  scheduler_t* scheduler;
  host_t* host;
  char sql[512];
  PGresult* db_result;
  int jq_pk1, jq_pk2, job_pk2, user_pk, upload_pk;

  scheduler = scheduler_init(testdb, NULL);
  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  /* Clean up any leftover job2 from a previous run. */
  db_result = database_exec(scheduler,
      "DELETE FROM jobqueue WHERE jq_job_fk IN "
      "(SELECT job_pk FROM job WHERE job_name = 'testing file 2')");
  SafePQclear(db_result);
  db_result = database_exec(scheduler, "DELETE FROM job WHERE job_name = 'testing file 2'");
  SafePQclear(db_result);

  /* job1 at high priority (9999) via the standard helper. */
  jq_pk1 = Prepare_Testing_Data(scheduler);

  /* Retrieve user_pk and upload_pk from job1 to reuse in job2's INSERT. */
  sprintf(sql,
      "SELECT j.job_user_fk, j.job_upload_fk"
      " FROM job j INNER JOIN jobqueue jq ON jq.jq_job_fk = j.job_pk"
      " WHERE jq.jq_pk = %d",
      jq_pk1);
  db_result = database_exec(scheduler, sql);
  user_pk   = (PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) > 0)
              ? atoi(PQgetvalue(db_result, 0, 0)) : 1;
  upload_pk = (PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) > 0)
              ? atoi(PQgetvalue(db_result, 0, 1)) : 0;
  PQclear(db_result);

  /* job2 at priority 0: always ranked below job1 by ORDER BY priority DESC,
   * so job1 is deterministically returned first when LIMIT=1. */
  sprintf(sql,
      "INSERT INTO job"
      " (job_pk, job_user_fk, job_queued, job_priority, job_name, job_upload_fk)"
      " VALUES (nextval('job_job_pk_seq'), %d, now(), 0, 'testing file 2', %d)"
      " RETURNING job_pk",
      user_pk, upload_pk);
  db_result = database_exec(scheduler, sql);
  job_pk2 = (PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) > 0)
            ? atoi(PQgetvalue(db_result, 0, 0)) : 0;
  PQclear(db_result);
  FO_ASSERT_NOT_EQUAL(job_pk2, 0);

  sprintf(sql,
      "INSERT INTO jobqueue"
      " (jq_pk, jq_job_fk, jq_type, jq_args, jq_runonpfile,"
      "  jq_starttime, jq_endtime, jq_end_bits, jq_host)"
      " VALUES (nextval('jobqueue_jq_pk_seq'), %d, 'ununpack', '0',"
      "  NULL, NULL, NULL, 0, NULL)"
      " RETURNING jq_pk",
      job_pk2);
  db_result = database_exec(scheduler, sql);
  jq_pk2 = (PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) > 0)
           ? atoi(PQgetvalue(db_result, 0, 0)) : 0;
  PQclear(db_result);
  FO_ASSERT_NOT_EQUAL(jq_pk2, 0);

  /* Force checkout_limit=1: exposes the regression where the sole LIMIT slot
   * is wasted by a re-fetched in-flight job. */
  host = host_init("limit_test_host", "localhost", ".", 1);
  host_insert(host, scheduler);

  /* First poll: LIMIT=1, NOT IN(0) → job1 (priority 9999) loaded. */
  database_update_event(scheduler, NULL);
  FO_ASSERT_PTR_NOT_NULL_FATAL(g_tree_lookup(scheduler->job_list, &jq_pk1));

  /* Second poll: LIMIT=1.
   * Without NOT IN: job1 (jq_starttime IS NULL) re-fetched, skipped by
   * g_tree_lookup, slot wasted → job2 never loaded. Regression.
   * With NOT IN: job1 excluded → job2 loaded. Fix validated. */
  database_update_event(scheduler, NULL);
  FO_ASSERT_PTR_NOT_NULL(g_tree_lookup(scheduler->job_list, &jq_pk2));

  database_reset_queue(scheduler);
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
    {"Test database_init",                         test_database_init                        },
    {"Test database_exec_event",                   test_database_exec_event                  },
    {"Test database_update_event",                 test_database_update_event                },
    {"Test database_update_event excludes known",  test_database_update_event_excludes_known },
    {"Test database_update_job",                   test_database_update_job                  },
    {"Test database_job",                          test_database_job                         },
    CU_TEST_INFO_NULL
};

CU_TestInfo tests_email[] =
{
    {"Test email_notify",  test_email_notify  },
    CU_TEST_INFO_NULL
};




