/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* fossology includes */
#include <libfossology.h>

/**
 * @file
 * @date Dec. 15, 2011
 * @brief This is a simple test agent meant to be used by Unit and functional
 *        tests to confirm a correctly working scheduler.
 *
 *        This particular agent
 *        is meant connect to the scheduler, wait for a message from it and then
 *        close without calling fo_scheduler_disconnect
 *
 * @note This is a failing agent
 */

int main(int argc, char** argv)
{
  fo_scheduler_connect(&argc, argv, NULL);
  fo_scheduler_next();

  return 0;
}
