/*********************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * @date May 14, 2011
 * @brief This is a simple test agent meant to be used by Unit and functional
 *        tests to confirm a correctly working scheduler. This particular agent
 *        will startup and then do nothing forever. This tests if the scheduler
 *        will kill an agent that has NOKILL set.
 */

int main(int argc, char** argv) {
  fo_scheduler_connect(&argc, argv);

  fo_scheduler_set_special(SPECIAL_NOKILL, 1);

  while(1) {
    sleep(100);
  }
}


