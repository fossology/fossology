/*********************************************************************
Copyright (C) 2011, 2012 Hewlett-Packard Development Company, L.P.

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

/**
 * @file simple.c
 * @date Dec. 15, 2011
 * @brief This is a simple test agent meant to be used by Unit and functional
 *        tests to confirm a correctly working scheduler. This particular agent
 *        is meant to simply work. It will start, call scheduler_connect,
 *        wait a few seconds and call scheduler_disconnect.
 *
 * This is a working agent
 */

int main(int argc, char** argv)
{
  fo_scheduler_connect(&argc, argv);
  if(fo_scheduler_next() == NULL)
    fo_scheduler_disconnect(0);
  else
    fo_scheduler_disconnect(-1);

  return 0;
}



