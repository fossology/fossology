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
#include <database.h>
#include <scheduler.h>

/* library includes */


/* testing sql statements */
char sqltmp[1024] = {0};
extern char* check_scheduler_tables;
/**
 * Local function for testing data prepare
 */
int Prepare_Testing_Data(scheduler_t * scheduler)
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
/* **** database function tests ******************************************** */
/* ************************************************************************** */

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
  if(PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) != 0)
  {
    //printf("result: %s\n",  g_strdup(PQgetvalue(db_result, 0, 0)));
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 0, 0)), "user_pk");
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 1, 0)), "user_name");
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 2, 0)), "root_folder_fk");
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 3, 0)), "user_desc");
    FO_ASSERT_STRING_EQUAL(g_strdup(PQgetvalue(db_result, 4, 0)), "user_seed");
  }
  PQclear(db_result);
  g_string_free(sql, TRUE);
  scheduler_destroy(scheduler);
}

void test_database_exec_event()
{
  scheduler_t* scheduler;
  GString* sql;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  sprintf(sqltmp, check_scheduler_tables, PQdb(scheduler->db_conn));
  sql = g_string_new(sqltmp);
  g_string_append(sql, "'user';");
  
  database_exec_event(scheduler, sql->str);
  scheduler_destroy(scheduler);
}

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
  job = job_init(scheduler->job_list, scheduler->job_queue, "ununpack", "localhost", -1, 0, 0, 0, 0);
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
    {"Test database_init",          test_database_init          },
    {"Test database_exec_event",       test_database_exec_event       },
    {"Test database_update_event",       test_database_update_event       },
    {"Test database_update_job",       test_database_update_job       },
    {"Test database_job",       test_database_job       },
    CU_TEST_INFO_NULL
};

CU_TestInfo tests_email[] =
{
    {"Test email_notify",  test_email_notify  },
    CU_TEST_INFO_NULL
};




