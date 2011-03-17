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

char* basic_checkout = "\
    SELECT * FROM getrunnable()\
    LIMIT 10;";

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
  PQclear(database_exec("\
      UPDATE jobqueue \
        SET jq_starttime=null, \
            jq_endtext=null, \
            jq_schedinfo=null\
        WHERE jq_endtime is NULL;"));
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
    lprintf("ERRO %s.%d: scheduler is closing, will not perform database update\n", __FILE__, __LINE__);
    return;
  }

  /* make the database query */
  db_result = PQexec(db_conn, basic_checkout);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    lprintf("ERROR %s.%d: database update failed on call to PQexec\n", __FILE__, __LINE__);
    lprintf("ERROR postgresql error: %s\n", PQresultErrorMessage(db_result));
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
 * Used by other parts of the scheduler to gain access to the database. The
 * libpq that is being used should have been compiled for thread safety since
 * this can be called from any thread.
 *
 * @param sql the sql command to execute
 * @return the PGresult that is created by the exec
 */
PGresult* database_exec(char* sql)
{
  return PQexec(db_conn, sql);
}
