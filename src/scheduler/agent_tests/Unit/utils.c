/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Utility functions for tests
 */

/* include functions to test */
#include <testRun.h>

/* scheduler includes */
#include <database.h>
#include <scheduler.h>

/* library includes */

/**
 * Local function for testing data prepare
 */
int Prepare_Testing_Data(scheduler_t * scheduler)
{
  char sql[1024];
  int upload_pk, job_pk, jq_pk, user_pk, folder_pk;
  PGresult* db_result;

  /* Remove stale test data so basic_checkout's LIMIT 10 always includes ours. */
  database_exec(scheduler,
      "DELETE FROM jobqueue WHERE jq_job_fk IN "
      "(SELECT job_pk FROM job WHERE job_name = 'testing file')");
  database_exec(scheduler, "DELETE FROM job WHERE job_name = 'testing file'");
  database_exec(scheduler,
      "DELETE FROM foldercontents WHERE child_id IN "
      "(SELECT upload_pk FROM upload WHERE upload_desc = 'testing upload data')");
  database_exec(scheduler, "DELETE FROM upload WHERE upload_desc = 'testing upload data'");

  /* Ensure a user exists.
   * The test DB is built with createPlainTables() which strips DEFAULT clauses,
   * so sequence-backed columns have no auto-increment.  We must call nextval()
   * explicitly in every INSERT and use RETURNING to get the real pk. */
  db_result = database_exec(scheduler, "SELECT user_pk FROM users LIMIT 1");
  if(PQntuples(db_result) > 0)
  {
    user_pk = atoi(PQget(db_result, 0, "user_pk"));
    PQclear(db_result);
  }
  else
  {
    PQclear(db_result);

    db_result = database_exec(scheduler,
        "INSERT INTO folder (folder_pk, folder_name, folder_desc)"
        " VALUES (nextval('folder_folder_pk_seq'),"
        " 'Software Repository', 'Top Folder')"
        " RETURNING folder_pk");
    folder_pk = (PQntuples(db_result) > 0) ? atoi(PQget(db_result, 0, "folder_pk")) : 1;
    PQclear(db_result);

    sprintf(sql,
        "INSERT INTO users"
        " (user_pk, user_name, user_desc, user_seed, user_pass, user_perm,"
        "  user_email, email_notify, root_folder_fk, default_folder_fk)"
        " VALUES (nextval('users_user_pk_seq'), 'testuser', '', '', '', 10, '', 'n', %d, -1)"
        " RETURNING user_pk",
        folder_pk);
    db_result = database_exec(scheduler, sql);
    user_pk = (PQntuples(db_result) > 0) ? atoi(PQget(db_result, 0, "user_pk")) : 1;
    PQclear(db_result);
  }

  sprintf(sql,
      "INSERT INTO upload"
      " (upload_pk, upload_desc, upload_filename, user_fk, upload_mode, upload_origin)"
      " VALUES (nextval('upload_upload_pk_seq'),"
      " 'testing upload data', 'testing file', '%d', '100', 'testing file')"
      " RETURNING upload_pk",
      user_pk);
  db_result = database_exec(scheduler, sql);
  upload_pk = (PQntuples(db_result) > 0) ? atoi(PQget(db_result, 0, "upload_pk")) : 0;
  PQclear(db_result);

  /* Add the upload record to the folder */
  sprintf(sql,
      "INSERT INTO foldercontents (parent_fk, foldercontents_mode, child_id)"
      " VALUES (1, 2, '%d')",
      upload_pk);
  database_exec(scheduler, sql);

  /* High priority ensures this job is within basic_checkout's LIMIT 10. */
  sprintf(sql,
      "INSERT INTO job"
      " (job_pk, job_user_fk, job_queued, job_priority, job_name, job_upload_fk)"
      " VALUES (nextval('job_job_pk_seq'), '%d', now(), '9999', 'testing file', %d)"
      " RETURNING job_pk",
      user_pk, upload_pk);
  db_result = database_exec(scheduler, sql);
  job_pk = (PQntuples(db_result) > 0) ? atoi(PQget(db_result, 0, "job_pk")) : 0;
  PQclear(db_result);

  /* Let the sequence assign jq_pk */
  sprintf(sql,
      "INSERT INTO jobqueue"
      " (jq_pk, jq_job_fk, jq_type, jq_args, jq_runonpfile,"
      "  jq_starttime, jq_endtime, jq_end_bits, jq_host)"
      " VALUES (nextval('jobqueue_jq_pk_seq'), '%d', 'ununpack', '%d',"
      "  NULL, NULL, NULL, 0, NULL)"
      " RETURNING jq_pk",
      job_pk, upload_pk);
  db_result = database_exec(scheduler, sql);
  jq_pk = (PQntuples(db_result) > 0) ? atoi(PQget(db_result, 0, "jq_pk")) : 0;
  PQclear(db_result);

  return jq_pk;
}
