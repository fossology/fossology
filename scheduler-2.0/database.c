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
#include <database.h>
#include <logging.h>
#include <scheduler.h>

/* std library includes */

/* other library includes */
#include <libfossdb.h>

/* ************************************************************************** */
/* **** data and sql statements ********************************************* */
/* ************************************************************************** */

void* db_struct = NULL;
PGconn* db_conn = NULL;

const char* basic_checkout = "\
    SELECT * FROM getrunnable()\
    LIMIT 10;";

const char* jobsql_started = "\
    UPDATE jobqueue \
      SET jq_starttime = now(), \
          jq_schedinfo ='%s.%d', \
          jq_endtext = 'Started' \
      WHERE jq_pk = '%d';";

const char* jobsql_complete = "\
    UPDATE jobqueue \
      SET jq_endtime = now(), \
          jq_end_bits = jq_end_bits | 1, \
          jq_schedinfo = null, \
          jq_endtext = 'Completed' \
      WHERE jq_pk = '%d';";

const char* jobsql_restart = "\
    UPDATE jobqueue \
      SET jq_endtime = now(), \
          jq_end_bits = jq_end_bits | 2, \
          jq_schedinfo = null, \
          jq_endtext = 'Restart' \
      WHERE jq_pk = '%d';";

const char* jobsql_failed = "\
    UPDATE jobqueue \
      SET jq_starttime = null, \
          jq_endtime = null, \
          jq_schedinfo = null, \
          jq_endtext = 'Failed' \
      WHERE jq_pk = '%d';";

const char* jobsql_paused = "\
    UPDATE jobqueue \
      SET jq_endtext = 'Paused' \
      WHERE jq_pk = '%d';";

/* ************************************************************************** */
/* **** local functions ***************************************************** */
/* ************************************************************************** */

#define PQget(db_result, row, col) PQgetvalue(db_result, row, PQfnumber(db_result, col))

/**
 *
 */
void database_init()
{
  if(db_struct == NULL)
    db_struct = DBopen();

  if(db_conn == NULL)
    db_conn = DBgetconn(db_struct);
}

/**
 *
 */
void database_destroy()
{
  DBclose(db_struct);
  db_struct = NULL;
  db_conn = NULL;
}

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

/**
 * Resets the any jobs in the job queue that are not completed. This is to make
 * sure that any jobs that were running with the scheduler shutdown are run correctly
 * when it starts up again.
 */
void database_reset_queue()
{
  PGresult* db_result = PQexec(db_conn, "\
      UPDATE jobqueue \
        SET jq_starttime=null, \
            jq_endtext=null, \
            jq_schedinfo=null\
        WHERE jq_endtime is NULL;");

  if(PQresultStatus(db_result) != PGRES_COMMAND_OK)
  {
    lprintf("ERROR %s.%d: failed to reset job queue\n");
    lprintf("ERROR postgresql error: %s\n", PQresultErrorMessage(db_result));
  }

  PQclear(db_result);
}

/**
 * TODO
 *
 * @param unused
 */
void database_update_event(void* unused)
{
  /* locals */
  PGresult* db_result;
  int i, job_id;
  char* value, * type, * pfile;
  job j;

  if(closing)
  {
    lprintf("ERROR %s.%d: scheduler is closing, will not perform database update\n", __FILE__, __LINE__);
    return;
  }

  /* make the database query */
  db_result = PQexec(db_conn, basic_checkout);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    lprintf("ERROR %s.%d: database update failed on call to PQexec\n", __FILE__, __LINE__);
    lprintf("ERROR postgresql error: %s\n", PQresultErrorMessage(db_result));
    PQclear(db_result);
    return;
  }

  VERBOSE2("DB: retrieved %d entries from the job queue\n", PQntuples(db_result));
  for(i = 0; i < PQntuples(db_result); i++)
  {
    /* start by checking that the job hasn't already been grabed */
    if(get_job(job_id = atoi(PQget(db_result, i, "jq_pk"))) != NULL)
      continue;

    /* get relevant values out of the job queue */
    type   =      PQget(db_result, i, "jq_type");
    pfile  =      PQget(db_result, i, "jq_runonpfile");
    value  =      PQget(db_result, i, "jq_args");

    VERBOSE2("DB: JOB[%d] added:\n   type = %s\n   pfile = %d\n   value = %s\n",
        job_id, type, (pfile != NULL && pfile[0] != '\0'), value);

    /* check if this is a command */
    if(strcmp(type, "command") == 0)
    {
      lprintf("DB: got a command from job queue\n");
      // TODO handle command
      continue;
    }

    /* make sure that we have an agent of that type */
    if(!is_meta_agent(type))
    {
      ERROR("Invalid meta agent: %s", type);
      continue;
    }

    j = job_init(type, job_id);
    job_set_data(j, value, (pfile && pfile[0] != '\0'));
  }

  PQclear(db_result);
}

/**
 * TODO
 *
 * @param j_id
 * @param status
 */
void database_update_job(int j_id, job_status status)
{
  /* locals */
  gchar* sql = NULL;
  PGresult* db_result;

  /* check how to update database */
    switch(status)
    {
      case JB_CHECKEDOUT:
        break;
      case JB_STARTED:
        sql = g_strdup_printf(jobsql_started, "localhost", getpid(), j_id);
        break;
      case JB_COMPLETE:
        sql = g_strdup_printf(jobsql_complete, j_id);
        break;
      case JB_RESTART:
        sql = g_strdup_printf(jobsql_restart, j_id);
        break;
      case JB_FAILED:
        sql = g_strdup_printf(jobsql_failed, j_id);
        break;
      case JB_SCH_PAUSED: case JB_CLI_PAUSED:
        sql = g_strdup_printf(jobsql_paused, j_id);
        break;
    }

    /* update the database job queue */
    db_result = PQexec(db_conn, sql);
    if(sql != NULL && PQresultStatus(db_result) != PGRES_COMMAND_OK)
    {
      lprintf("ERROR %s.%d: failed to update job status in job queue\n", __FILE__, __LINE__);
      lprintf("ERROR postgresql error: %s\n", PQresultErrorMessage(db_result));
    }
    PQclear(db_result);
    g_free(sql);
}
