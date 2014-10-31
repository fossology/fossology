/*
Author: Daniele Fognini, Andreas Wuerl, Johannes Najjar
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include <stdio.h>
#include <iostream>
#include <sstream>

#include "copyright.hpp"

using namespace std;
using namespace fo;

#define return_sched(retval) \
  do {\
    fo_scheduler_disconnect((retval));\
    return (retval);\
  } while(0)

int main(int argc, char** argv)
{
  /* before parsing argv and argc make sure */
  /* to initialize the scheduler connection */

  DbManager dbManager(&argc, argv);

  CliOptions cliOptions;
  vector<string> fileNames;
  if (!parseCliOptions(argc, argv, cliOptions, fileNames))
  {
    return_sched(1);
  }

  CopyrightState state = getState(dbManager, cliOptions);
  CopyrightDatabaseHandler copyrightDatabaseHandler(dbManager);

  if (!copyrightDatabaseHandler.createTables())
  {
    std::cout << "FATAL: initialization failed" << std::endl;
    return_sched(9);
  }

  fillMatchers(state);

  if (!fileNames.empty())
  {
    const vector<RegexMatcher>& regexMatchers = state.getRegexMatchers();

    const unsigned long fileNamesCount = fileNames.size();
#pragma omp parallel
    {
#pragma omp for
      for (int argn = 0; argn < fileNamesCount; ++argn)
      {
        const string fileName = fileNames[argn];
        fo::File file((unsigned long) argn, fileName);
        vector<CopyrightMatch> matches = findAllMatches(file, regexMatchers);

        stringstream ss;
        ss << fileName << " ::" << endl << matches << endl;
        cout << ss.str();
      }
    }
  }
  else
  {
    while (fo_scheduler_next() != NULL)
    {
      int uploadId = atoi(fo_scheduler_current());

      if (uploadId == 0) continue;

      int arsId = writeARS(state, 0, uploadId, 0, dbManager);

      if (arsId <= 0)
        return_sched(5);

      if (!processUploadId(state, uploadId, copyrightDatabaseHandler))
        return_sched(2);

      fo_scheduler_heart(1);
      writeARS(state, arsId, uploadId, 1, dbManager);
    }
    fo_scheduler_heart(0);
  }

  /* do not use bail, as it would prevent the destructors from running */
  return_sched(0);
}

