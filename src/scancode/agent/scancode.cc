/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scancode.hpp"

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

int main(int argc, char* argv[])
{
  DbManager dbManager(&argc, argv);
  
  fo_conf* agentConfig = NULL;
  GError* error = NULL;
  char agentConfigPath[FILENAME_MAX];
  
  snprintf(agentConfigPath, FILENAME_MAX, "%s/mods-enabled/scancode/scancode.conf", sysconfigdir ? sysconfigdir : "/usr/local/etc/fossology");
  agentConfig = fo_config_load(agentConfigPath, &error);
  
  if (!error && agentConfig) {
    fo_config_join(sysconfig, agentConfig, &error);
    if (error) {
      g_error_free(error);
    } else {
      LOG_NOTICE("Loaded conf from %s\n", agentConfigPath);
    }
    fo_config_free(agentConfig);
  } else {
    if (error) {
      LOG_WARNING("Error: %s\n", error->message);
      g_error_free(error);
    }
  }
  
  ScancodeDatabaseHandler databaseHandler(dbManager);
  string scanFlags;
  bool ignoreFilesWithMimeType;
  int parallelParams[5]; 
  if(!parseCommandLine(argc, argv, scanFlags, ignoreFilesWithMimeType, parallelParams)){
    return_sched(1);
  }

  State state = getState(dbManager);
  state.setCliOptions(scanFlags);
    if (!databaseHandler.createTables())
    {
      LOG_FATAL("initialization failed \n");
      return_sched(9);
    }

  while (fo_scheduler_next() != NULL)
  {
    int uploadId = atoi(fo_scheduler_current());

    if (uploadId == 0) continue;

    int arsId = writeARS(state, 0, uploadId, 0, dbManager);

    if (arsId <= 0)
      bail(5);
    if (!processUploadId(state, uploadId, databaseHandler,ignoreFilesWithMimeType, parallelParams))
      bail(2);

    fo_scheduler_heart(0);
    writeARS(state, arsId, uploadId, 1, dbManager);
  }
  fo_scheduler_heart(0);

  /* do not use bail, as it would prevent the destructors from running */
  fo_scheduler_disconnect(0);
  return 0;
}