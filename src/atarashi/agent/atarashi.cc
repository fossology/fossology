/*
 * Copyright (C) 2019-2020, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

#include "atarashi.hpp"
#include <iostream>
#include <chrono>

using namespace fo;

typedef std::chrono::high_resolution_clock Clock;
typedef std::chrono::time_point<Clock> TimePoint;

inline TimePoint now() {
  return Clock::now();
}

inline long long duration_ms(TimePoint start, TimePoint end) {
  return std::chrono::duration_cast<std::chrono::milliseconds>(end - start).count();
}

/**
 * @def return_sched(retval)
 * Send disconnect to scheduler with retval
 * @return function with retval
 */
#define return_sched(retval) \
  do {\
    fo_scheduler_disconnect((retval));\
    return (retval);\
  } while(0)

int main(int argc, char** argv)
{
  /* before parsing argv and argc make sure */
  /* to initialize the scheduler connection */

  auto t_start = now();
  DbManager dbManager(&argc, argv);
  AtarashiDatabaseHandler databaseHandler(dbManager);
  auto t_afterInit = now();
  std::cout << "[DEBUG] Init took " 
            << duration_ms(t_start, t_afterInit) << " ms\n";

  std::string agentName;
  std::string similarityMethod;
  bool verboseMode = false;
  bool ignoreFilesWithMimeType = false;

  std::string configDir = "/usr/local/etc/fossology";
  for (int i = 1; i < argc; ++i) {
    if (std::string(argv[i]).rfind("--config=", 0) == 0) {
      configDir = std::string(argv[i]).substr(9);
    }
  }
  auto t_beforeConfig = now();
  readAtarashiConfig(configDir, agentName, similarityMethod);
  auto t_afterConfig = now();
  std::cout << "[DEBUG] Config parsing took " 
            << duration_ms(t_beforeConfig, t_afterConfig) << " ms\n";

  auto t_beforeCLIParsing = now();
  if (!parseCommandLine(argc, argv, agentName, similarityMethod, verboseMode)) {
    return_sched(1);
  }
  auto t_afterCLIParsing = now();
  std::cout << "[DEBUG] CLI parsing took " 
            << duration_ms(t_beforeCLIParsing, t_afterCLIParsing) << " ms\n";

  
  State state = getState(dbManager);
  state.setAgentName(agentName);
  state.setSimilarityMethod(similarityMethod);
  state.setVerbose(verboseMode);

  std::cout << "Using agent: " << state.getAgentName()
            << " | Similarity: " << state.getSimilarityMethod() << "\n";

  while (fo_scheduler_next() != NULL)
  {
    auto t_uploadStart = now();
    int uploadId = atoi(fo_scheduler_current());
    auto t_afterUploadIDfetched = now();
    std::cout << "[DEBUG] Upload ID fetched in: " 
            << duration_ms(t_uploadStart, t_afterUploadIDfetched) << " ms\n";

    if (uploadId == 0) continue;

    auto t_beforeWriteARS = now();
    int arsId = writeARS(state, 0, uploadId, 0, dbManager);
    auto t_afterWriteARS = now();
    std::cout << "[DEBUG] Writing ARS new entry took: " 
            << duration_ms(t_beforeWriteARS, t_afterWriteARS) << " ms\n";

    if (arsId <= 0)
      bail(5);

    auto t_beforeuploadIDProcess = now();
    if (!processUploadId(state, uploadId, databaseHandler, ignoreFilesWithMimeType))
      bail(2);
    auto t_afteruploadIDProcess = now();
    std::cout << "[DEBUG] Processing Upload ID took: " 
            << duration_ms(t_beforeuploadIDProcess, t_afteruploadIDProcess) << " ms\n";
    
    auto beforeFirstSchedulerHeart = now();
    fo_scheduler_heart(0);
    auto afterFirstSchedulerHeart = now();
    std::cout << "[DEBUG] First Scheduler heart took: " 
            << duration_ms(beforeFirstSchedulerHeart, afterFirstSchedulerHeart) << " ms\n";

    auto t_before2ndWriteARS = now();
    writeARS(state, arsId, uploadId, 1, dbManager);
    auto t_after2ndWriteARS = now();
    std::cout << "[DEBUG] Writing Successful ARS took: " 
            << duration_ms(t_before2ndWriteARS, t_after2ndWriteARS) << " ms\n";
  }
  auto t_lastSchedulerStart = now();
  fo_scheduler_heart(0);
  auto t_lastSchedulerEnd = now();
  std::cout << "[DEBUG] Last Scheduler Heart took: " 
            << duration_ms(t_lastSchedulerStart, t_lastSchedulerEnd) << " ms\n";

  std::cout << "[DEBUG] Whole file scanned in: " 
            << duration_ms(t_start, t_lastSchedulerEnd) << " ms\n";

  /* do not use bail, as it would prevent the destructors from running */
  fo_scheduler_disconnect(0);
  return 0;
}
