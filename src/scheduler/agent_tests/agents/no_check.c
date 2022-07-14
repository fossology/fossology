/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* fossology includes */
#include <libfossology.h>

/**
 * @file no_check.c
 * @date June 1, 2012
 * @brief This is a simple test agent meant to be used by Unit and functional
 *        tests to confirm a correctly working scheduler.
 *
 *        This agent does not
 *        check for the return of fo_scheduler_next() to be NULL, so will fail
 *        the scheduler startup test since will never finish.
 *
 * @note This is a failing agent
 */

int main(int argc, char** argv)
{
  fo_scheduler_connect(&argc, argv, NULL);

  while(1)
  {
    fo_scheduler_next();
  }

  fo_scheduler_disconnect(0);
  return 0;
}




