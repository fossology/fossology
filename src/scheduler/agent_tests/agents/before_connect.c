/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* fossology includes */
#include <libfossology.h>

/**
 * @dir
 * @brief Contains sample agents which have specific behaviors
 * @file
 * @date June 1, 2012
 * @brief This is a simple test agent meant to be used by Unit and functional
 *        tests to confirm a correctly working scheduler.
 *
 * This agent is identical to the simple agent, but it writes to stdout before
 * calling fo_scheduler_connect().
 *
 * @note This is a failing agent
 */

int main(int argc, char** argv)
{
  printf("before connect");

  fo_scheduler_connect(&argc, argv, NULL);
  if(fo_scheduler_next() == NULL)
    fo_scheduler_disconnect(0);
  else
    fo_scheduler_disconnect(-1);

  return 0;

}


