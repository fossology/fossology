/*********************************************************************
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
  char sql[512];
  int upload_pk, job_pk, jq_pk;
  PGresult* db_result;

  sprintf(sql, "INSERT INTO upload (upload_desc,upload_filename,user_fk,upload_mode,upload_origin) "
      "VALUES('testing upload data', 'testing file', '1', '100', 'testing file')");
  database_exec(scheduler, sql);

  /* get upload_pk of just added upload */
  sprintf(sql, "SELECT currval('upload_upload_pk_seq') as mykey FROM %s", "upload");
  db_result = database_exec(scheduler, sql);
  upload_pk = atoi(PQget(db_result, 0, "mykey"));
  PQclear(db_result);

  /* Add the upload record to the folder */
  sprintf(sql, "INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES ('1',2,'%d')", upload_pk);
  database_exec(scheduler, sql);

  job_pk = 1;
  /* Add the job info */
  sprintf(sql, "INSERT INTO job (job_pk,job_user_fk,job_queued,job_priority,job_name,job_upload_fk) "
      "VALUES(%d,'1',now(),'0','testing file',%d)", job_pk,upload_pk);
  database_exec(scheduler, sql);

  jq_pk = 1;
  sprintf(sql, "INSERT INTO jobqueue "
      "(jq_pk,jq_job_fk,jq_type,jq_args,jq_runonpfile,jq_starttime,jq_endtime,jq_end_bits,jq_host) "
      "VALUES (%d,'%d', 'ununpack', '%d', NULL, NULL, NULL, 0, NULL)", jq_pk, job_pk, upload_pk);
  database_exec(scheduler, sql);

  return jq_pk;
}
