/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* fossology includes */
#include <libfossology.h>

/**
 * @file
 * @date June 1, 2012
 * @brief This is a simple test agent meant to be used by Unit and functional
 *        tests to confirm a correctly working scheduler.
 *
 *        This agent passes a
 *        database pointer to the fo_scheduler_connect function and checks that
 *        the database was successfully connected to.
 *
 * @note This is a correct agent.
 */

int main(int argc, char** argv)
{
  PGconn* db_conn;

  fo_scheduler_connect(&argc, argv, &db_conn);
  fo_scheduler_next();

  if(PQstatus(db_conn) == CONNECTION_OK)
    fo_scheduler_disconnect(0);
  else
    fo_scheduler_disconnect(-1);

  return 0;
}

