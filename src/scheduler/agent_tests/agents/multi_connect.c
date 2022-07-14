/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* fossology includes */
#include <libfossology.h>

/**
 * @file multi_connect.c
 * @date June 1, 2012
 * @brief This is a simple test agent meant to be used by Unit and functional
 *        tests to confirm a correctly working scheduler.
 *
 *        This agent will
 *        call fo_scheduler_connect() twice. There is technically nothing that
 *        should disallow this currently.
 *
 * @note This is a working agent
 */

int main(int argc, char** argv)
{
  fo_scheduler_connect(&argc, argv, NULL);
  fo_scheduler_connect(&argc, argv, NULL);

  fo_scheduler_next();
  fo_scheduler_disconnect(0);

  return 0;
}
