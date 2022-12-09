/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief The OJO agent
 * @file
 * @brief Entry point for ojo agent
 * @page ojo OJO Agent
 * @tableofcontents
 *
 * OJO agent uses regular expressions to find out SPDX License identifiers
 * from a file.
 *
 * The agent runs in multi-threaded mode and creates a new thread for
 * every pfile for faster processing.
 *
 * @section ojoactions Supported actions
 * | Command line flag | Description |
 * | ---: | :--- |
 * | -h [--help] | Shows help |
 * | -v [--verbose] | Increase verbosity |
 * | --files arg | Files to scan |
 * | -J [--json] | Output JSON |
 * | -d [--directory] | Directory to be scanned (recursive) |
 * | -c [ --config ] arg | Path to the sysconfigdir |
 * | --scheduler_start | Specifies, that the command was called by the |
 * || scheduler |
 * | --userID arg | The id of the user that created the job (only in |
 * || combination with --scheduler_start) |
 * | --groupID arg | The id of the group of the user that created the job |
 * || (only in combination with --scheduler_start) |
 * | --jobId arg | The id of the job (only in combination with |
 * || --scheduler_start) |
 * @section ojosource Agent source
 *   - @link src/ojo/agent @endlink
 *   - @link src/ojo/ui @endlink
 */

#include "ojos.hpp"

using namespace fo;

/**
 * @def return_sched(retval)
 * Send disconnect to scheduler with retval and return function with retval.
 */
#define return_sched(retval) \
  do {\
    fo_scheduler_disconnect((retval));\
    return (retval);\
  } while(0)

int main(int argc, char **argv)
{
  OjoCliOptions cliOptions;
  vector<string> fileNames;
  string directoryToScan;
  if (!parseCliOptions(argc, argv, cliOptions, fileNames, directoryToScan))
  {
    return_sched(1);
  }

  bool json = cliOptions.doJsonOutput();
  bool ignoreFilesWithMimeType = cliOptions.doignoreFilesWithMimeType();
  OjoState state = getState(std::move(cliOptions));

  if (!fileNames.empty())
  {
    const unsigned long fileNamesCount = fileNames.size();
    bool fileError = false;
    bool printComma = false;
    OjoAgent agentObj = state.getOjoAgent();

    if (json)
    {
      cout << "[" << endl;
    }

#pragma omp parallel shared(printComma)
    {
#pragma omp for
      for (unsigned int argn = 0; argn < fileNamesCount; ++argn)
      {
        const string fileName = fileNames[argn];

        vector<ojomatch> l;
        try
        {
          l = agentObj.processFile(fileName);
        }
        catch (std::runtime_error &e)
        {
          cerr << "Unable to read " << e.what();
          fileError = true;
          continue;
        }
        pair<string, vector<ojomatch>> scanResult(fileName, l);
        if (json)
        {
          appendToJson(fileName, scanResult, printComma);
        }
        else
        {
          printResultToStdout(fileName, scanResult);
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
    scanDirectory(json, directoryToScan);
  }
  else
  {
    DbManager dbManager(&argc, argv);
    OjosDatabaseHandler databaseHandler(dbManager);

    state.setAgentId(queryAgentId(dbManager));

    while (fo_scheduler_next() != NULL)
    {
      int uploadId = atoi(fo_scheduler_current());

      if (uploadId == 0)
        continue;

      int arsId = writeARS(state, 0, uploadId, 0, dbManager);

      if (arsId <= 0)
        bail(5);

      if (!processUploadId(state, uploadId, databaseHandler, ignoreFilesWithMimeType))
        bail(2);

      fo_scheduler_heart(0);
      writeARS(state, arsId, uploadId, 1, dbManager);
    }
    fo_scheduler_heart(0);

    /* do not use bail, as it would prevent the destructors from running */
    fo_scheduler_disconnect(0);
  }
  return 0;
}
