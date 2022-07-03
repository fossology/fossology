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
 *        is meant to simply work. It will start, call scheduler_connect,
 *        wait a few seconds and call scheduler_disconnect.
 *
 * @note This is a working agent
 */

int main(int argc, char** argv)
{
  fo_scheduler_connect(&argc, argv, NULL);
  if(fo_scheduler_next() == NULL)
    fo_scheduler_disconnect(0);
  else
    fo_scheduler_disconnect(-1);

  return 0;
}



