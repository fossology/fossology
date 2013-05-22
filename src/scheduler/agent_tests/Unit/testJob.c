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
/**
 * Local function for testing data prepare
 */
int Prepare_Testing_Data_Job(scheduler_t * scheduler)
{
  char sql[512];
  int upload_pk, job_pk, jq_pk;
  PGresult* db_result;

  sprintf(sql, "INSERT INTO upload (upload_desc,upload_filename,user_fk,upload_mode,upload_origin) VALUES('testing upload data', 'testing file', '1', '100', 'testing file')");
  database_exec(scheduler, sql);

  /* get upload_pk of just added upload */
  sprintf(sql, "SELECT currval('upload_upload_pk_seq') as mykey FROM %s", "upload");
  db_result = database_exec(scheduler, sql);
  upload_pk = atoi(PQget(db_result, 0, "mykey")); 
  PQclear(db_result);

  /* Add the upload record to the folder */
  sprintf(sql, "INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES ('1',2,'%d')", upload_pk);
  database_exec(scheduler, sql);
  
  /* Add the job info */
  sprintf(sql, "INSERT INTO job (job_user_fk,job_queued,job_priority,job_name,job_upload_fk) VALUES('1',now(),'0','testing file',%d)", upload_pk);
  database_exec(scheduler, sql);

  /* get job_pk of just added upload */
  sprintf(sql, "SELECT currval('job_job_pk_seq') as mykey FROM %s", "job");
  db_result = database_exec(scheduler, sql);
  job_pk = atoi(PQget(db_result, 0, "mykey"));
  PQclear(db_result);

  sprintf(sql, "INSERT INTO jobqueue (jq_job_fk,jq_type,jq_args,jq_runonpfile,jq_starttime,jq_endtime,jq_end_bits,jq_host) VALUES ('%d', 'ununpack', '%d', NULL, NULL, NULL, 0, NULL)", job_pk, upload_pk);
  database_exec(scheduler, sql);

  sprintf(sql, "SELECT currval('jobqueue_jq_pk_seq') as mykey FROM %s", "jobqueue");
  db_result = database_exec(scheduler, sql);
  jq_pk = atoi(PQget(db_result, 0, "mykey"));
  PQclear(db_result);
  return(jq_pk);
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
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data_Job(scheduler);

  database_update_event(scheduler, NULL);

  job = g_tree_lookup(scheduler->job_list, &jq_pk);
  FO_ASSERT_PTR_NOT_NULL(job);
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

  scheduler_destroy(scheduler);
}

void test_job_fun()
{
  scheduler_t* scheduler;
  job_t* job;
  char* res = NULL;
  log_t* log = NULL;
  uint32_t result = 0;
  int jq_pk;

  scheduler = scheduler_init(testdb, NULL);
  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data_Job(scheduler);

  database_update_event(scheduler, NULL);

  job = g_tree_lookup(scheduler->job_list, &jq_pk);
  FO_ASSERT_PTR_NOT_NULL(job);

  res = job_next(job);
  FO_ASSERT_STRING_EQUAL(res, "6");
  //log = job_log(job);
  FO_ASSERT_PTR_NULL(log);
  job = next_job(scheduler->job_queue);
  FO_ASSERT_EQUAL(job->id, 1);
  FO_ASSERT_PTR_NOT_NULL(job);
  job = peek_job(scheduler->job_queue);
  FO_ASSERT_PTR_NOT_NULL(job);
  FO_ASSERT_EQUAL(job->id, 6);
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
