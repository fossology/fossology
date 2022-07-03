/*
 SPDX-FileCopyrightText: © 2011, 2012 Hewlett-Packard Development Company, L.P.

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
 *        is meant connect correctly to the scheduler and then instantly die.
 *
 * @note This is a failing agent
 */

int main(int argc, char** argv)
{
  fo_scheduler_connect(&argc, argv, NULL);

  return 0;
}
