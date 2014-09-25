/*
Author: Daniele Fognini, Andreas Wuerl, Johannes Najjar
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include <stdio.h>



#include <iostream>
#include "copyright.hpp"

using namespace std;

int main(int argc, char** argv) {
  /* before parsing argv and argc make sure */
  /* to initialize the scheduler connection */

  DbManager* dbManager = new DbManager(&argc, argv);

  int verbosity=8;
  CopyrightState* state;
  state = getState(dbManager, verbosity);
  if (!state->copyrightDatabaseHandler.checkTables(dbManager)) {
    if (!state->copyrightDatabaseHandler.createTables(dbManager)) {
      std::cout << "FATAL: initialization failed" << std::endl;
      bail(state, 9);
    }
  }

  fillMatchers(state);

  while (fo_scheduler_next() != NULL) {
    int uploadId = atoi(fo_scheduler_current());

    if (uploadId == 0) continue;

    int arsId = fo_WriteARS(state->getConnection(),
                            0, uploadId, state->getAgentId(), AGENT_ARS, NULL, 0);

    if (!processUploadId(state, uploadId))
      bail(state, 2);


    fo_scheduler_heart(1);
    fo_WriteARS(state->getConnection(),
                arsId, uploadId, state->getAgentId(), AGENT_ARS, NULL, 1);
  }
  fo_scheduler_heart(0);

  /* after cleaning up agent, disconnect from */
  /* the scheduler, this doesn't return */
  bail(state, 0);
}
