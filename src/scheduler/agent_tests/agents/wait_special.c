/*********************************************************************
Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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

/* fossology includes */
#include <libfossology.h>

#define MINUTES_TO_WAIT 1

/**
 * @file simple.c
 * @date June 1, 2012
 * @brief This is a simple test agent meant to be used by Unit and functional
 *        tests to confirm a correctly working scheduler. This agent will set
 *        itself to NOKILL, wait long enough that the scheduler would have
 *        killed it. Then set itself back to be able to be killed, and wait
 *        till the scheduler would kill it. Then finish.
 *
 * This is a failing agent
 */

int main(int argc, char** argv)
{
  int i;
  PGconn* db_conn;

  fo_scheduler_connect(&argc, argv, &db_conn);
  fo_scheduler_set_special(SPECIAL_NOKILL, 1);

  for(i = 0; i < MINUTES_TO_WAIT; i++)
    sleep(60);

  fo_scheduler_set_special(SPECIAL_NOKILL, 0);

  for(i = 0; i < MINUTES_TO_WAIT; i++)
    sleep(60);

  fo_scheduler_next();
  fo_scheduler_disconnect(0);

  PQfinish(db_conn);
  return 0;
}




