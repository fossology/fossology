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
#include <database.h>
#include <logging.h>

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

void db_init()
{
  if(db_struct == NULL)
    db_struct = DBopen();

  if(db_conn == NULL)
    db_conn = DBgetconn(db_struct);
}

void db_destroy()
{
  DBclose(db_struct);
  db_struct = NULL;
  db_conn = NULL;
}

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

/**
 * TODO
 *
 * @param unused
 */
void database_update_event(void* unused)
{
  /* locals */
  PGresult* db_result;
  int i;

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







  }
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








