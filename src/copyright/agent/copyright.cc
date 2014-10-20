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

  if (!state->copyrightDatabaseHandler.createTables(dbManager)) {
    std::cout << "FATAL: initialization failed" << std::endl;
    bail(state, 9);
  }

  fillMatchers(state);

  if (argc>1)
  {
    for (int argn=1; argn<argc; ++argn)
    {
      const char* fileName = argv[argn];

      fo::File file(argn, fileName);
      vector<CopyrightMatch> matches = findAllMatches(file, state);

      typedef vector<CopyrightMatch>::const_iterator cpm;
      cout << fileName << " ::" << endl;
      for (cpm it = matches.begin(); it != matches.end(); ++it)
        cout << "\t" << *it << endl;
    }
  }
  else
  {
    while (fo_scheduler_next() != NULL) {
      int uploadId = atoi(fo_scheduler_current());

      if (uploadId == 0) continue;

      int arsId = writeARS(state, 0, uploadId, 0);

      if (!processUploadId(state, uploadId))
        bail(state, 2);

      fo_scheduler_heart(1);
      writeARS(state, arsId, uploadId, 1);
    }
    fo_scheduler_heart(0);
  }

  /* after cleaning up agent, disconnect from */
  /* the scheduler, this doesn't return */
  bail(state, 0);
}
