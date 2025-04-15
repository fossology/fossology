/*
 SPDX-FileCopyrightText: © 2014,2022, Siemens AG
 Author: Daniele Fognini, Andreas Wuerl, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
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
 * | -d [--directory] | Directory to scan (recursive) |
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
  string directoryToScan;

  // Set global locale to C to avoid problems
  std::locale::global(std::locale("C"));

  if (!parseCliOptions(argc, argv, cliOptions, fileNames, directoryToScan))
  {
    return_sched(1);
  }

  bool json = cliOptions.doJsonOutput();
  bool ignoreFilesWithMimeType = cliOptions.doignoreFilesWithMimeType();

  if (!fileNames.empty())
  {
    const unsigned long fileNamesCount = fileNames.size();
    bool fileError = false;
    bool printComma = false;
    CopyrightState state = getState(std::move(cliOptions));

    if (json)
    {
      cout << "[" << endl;
    }

#pragma omp parallel num_threads(THREADS)
    {
#pragma omp for
      for (unsigned int argn = 0; argn < fileNamesCount; ++argn)
      {
        const string fileName = fileNames[argn];
        pair<icu::UnicodeString, list<match>> scanResult = processSingleFile(state, fileName);
        if (json)
        {
          appendToJson(fileName, scanResult, printComma);
        }
        else
        {
          printResultToStdout(fileName, scanResult);
        }
        if (scanResult.first.isEmpty())
        {
          fileError = true;
        }
      }
    }
    if (json)
    {
      cout << endl << "]" << endl;
    }
    return fileError ? 1 : 0;
  }
  else if (directoryToScan.length() > 0)
  {
    CopyrightState state = getState(std::move(cliOptions));
    scanDirectory(state, json, directoryToScan);
  }
  else
  {
    DbManager dbManager(&argc, argv);
    int agentId = queryAgentId(dbManager.getConnection());
    CopyrightState state = getState(std::move(cliOptions));

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

      if (!processUploadId(state, agentId, uploadId, copyrightDatabaseHandler, ignoreFilesWithMimeType))
        return_sched(2);

      fo_scheduler_heart(0);
      writeARS(agentId, arsId, uploadId, 1, dbManager);
    }
    fo_scheduler_heart(0);
    /* do not use bail, as it would prevent the destructors from running */
    return_sched(0);
  }
}

