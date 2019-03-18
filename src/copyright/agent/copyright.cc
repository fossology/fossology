/*
Author: Daniele Fognini, Andreas Wuerl, Johannes Najjar
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2
as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
/**
 * \file copyright.cc
 * \brief Copyright agent
 * \page copyright Copyright Agent
 * \tableofcontents
 *
 * Copyright agent uses regular expressions to find out copyright
 * statments, author statements, URLs and Emails in uploads.
 *
 * Copyright agent also create ecc agent which also uses regular
 * expressions to find ecc statements in uploads.
 *
 * The agent runs in multi-threaded mode and creates a new thread for
 * every pfile for faster processing.
 *
 * \section copyrightactions Supported actions
 * | Command line flag | Description |
 * | ---: | :--- |
 * | -h [--help] | Shows help |
 * | -T [--type] arg (=15) | Type of regex to try |
 * | -v [--verbose] | Increase verbosity |
 * | --regex arg | User defined Regex to search: |
 * || `[{name=cli}@@][{matchingGroup=0}@@]{regex}` |
 * || e.g. 'linux@@1@@(linus) torvalds' |
 * | --files arg | Files to scan |
 * | -J [--json] | Output JSON |
 * \section copyrightsource Agent source
 *   - \link src/copyright/agent \endlink
 *   - \link src/copyright/ui \endlink
 *   - Functional test cases \link src/copyright/agent_tests/Functional \endlink
 *   - Unit test cases \link src/copyright/agent_tests/Unit \endlink
 */
#include <stdio.h>
#include <iostream>
#include <sstream>

#include "copyright.hpp"

#include <json/json.h>

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

  CliOptions cliOptions;
  vector<string> fileNames;
  if (!parseCliOptions(argc, argv, cliOptions, fileNames))
  {
    return_sched(1);
  }

  bool json = cliOptions.doJsonOutput();
  CopyrightState state = getState(std::move(cliOptions));

  if (!fileNames.empty())
  {
    const list<unptr::shared_ptr<scanner>>& scanners = state.getScanners();

    const unsigned long fileNamesCount = fileNames.size();
    bool fileError = false;

#pragma omp parallel
    {
#pragma omp for
      for (unsigned int argn = 0; argn < fileNamesCount; ++argn)
      {
        const string fileName = fileNames[argn];
        // Read file into one string
        string s;
        if (!ReadFileToString(fileName, s))
        {
          // File error
          fileError = true;
        }
        else
        {
          list<match> l;
          for (auto sc = scanners.begin(); sc != scanners.end(); ++sc)
          {
            (*sc)->ScanString(s, l);
          }

          if (json) {
            Json::Value results;
            for (auto m = l.begin(); m != l.end(); ++m)
            {
              Json::Value j;
              j["start"] = m->start;
              j["end"] = m->end;
              j["type"] = m->type;
              j["content"] = cleanMatch(s, *m);
              results.append(j);
            }
            Json::Value output;
            output["results"] = results;
            Json::FastWriter builder;
            cout << builder.write(output);
          } else {
            stringstream ss;
            ss << fileName << " ::" << endl;
            // Output matches
            for (auto m = l.begin();  m != l.end(); ++m)
            {
              ss << "\t[" << m->start << ':' << m->end << ':' << m->type << "] '"
                 << cleanMatch(s, *m)
                 << "'" << endl;
            }
            // Thread-Safety: output all matches (collected in ss) at once to cout
            cout << ss.str();
          }
        }
      }
    }
    return fileError ? 1 : 0;
  }
  else
  {
    DbManager dbManager(&argc, argv);
    int agentId = queryAgentId(dbManager.getConnection());

    CopyrightDatabaseHandler copyrightDatabaseHandler(dbManager);
    if (!copyrightDatabaseHandler.createTables())
    {
      std::cout << "FATAL: initialization failed" << std::endl;
      return_sched(9);
    }

    while (fo_scheduler_next() != NULL)
    {
      int uploadId = atoi(fo_scheduler_current());

      if (uploadId <= 0) continue;

      int arsId = writeARS(agentId, 0, uploadId, 0, dbManager);

      if (arsId <= 0)
        return_sched(5);

      if (!processUploadId(state, agentId, uploadId, copyrightDatabaseHandler))
        return_sched(2);

      fo_scheduler_heart(0);
      writeARS(agentId, arsId, uploadId, 1, dbManager);
    }
    fo_scheduler_heart(0);
    /* do not use bail, as it would prevent the destructors from running */
    return_sched(0);
  }

}

