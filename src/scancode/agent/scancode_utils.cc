/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scancode_utils.hpp"

namespace po = boost::program_options;

/**
 * @brief Disconnect with scheduler returning an error code and exit
 * @param exitval Error code
 */
void bail(int exitval) {
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

/**
 * @brief Get state of the agent
 * @param dbManager DBManager to be used
 * @return  state of the agent
 */
State getState(DbManager &dbManager) {
  int agentId = queryAgentId(dbManager);
  return State(agentId);
}

/**
 * @brief Get agent id, exit if agent id is incorrect
 * @param[in]  dbConn Database connection object
 * @return ID of the agent
 */
int queryAgentId(DbManager &dbManager) {
  char *COMMIT_HASH = fo_sysconfig(AGENT_NAME, "COMMIT_HASH");
  char *VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char *agentRevision;

  if (!asprintf(&agentRevision, "%s.%s", VERSION, COMMIT_HASH))
    bail(-1);

  int agentId = fo_GetAgentKey(dbManager.getConnection(), AGENT_NAME, 0,
                               agentRevision, AGENT_DESC);
  free(agentRevision);

  if (agentId <= 0)
    bail(1);

  return agentId;
}

/**
 * @brief Write ARS to the agent's ars table
 * @param state     State of the agent
 * @param arsId     ARS id (0 for new entry)
 * @param uploadId  Upload ID
 * @param success   Success status
 * @param dbManager DbManager to use
 * @return ARS ID.
 */
int writeARS(const State &state, int arsId, int uploadId, int success,
             DbManager &dbManager) {
  PGconn *connection = dbManager.getConnection();
  int agentId = state.getAgentId();

  return fo_WriteARS(connection, arsId, uploadId, agentId, AGENT_ARS, NULL,
                     success);
}

/**
 * @brief Process a given upload id, scan from statements and add to database
 * @param state           State of the agent
 * @param uploadId        Upload id to be processed
 * @param databaseHandler Database handler object
 * @return True when upload is successful
 */
bool processUploadId(const State &state, int uploadId,
                     ScancodeDatabaseHandler &databaseHandler, bool ignoreFilesWithMimeType) {
  vector<unsigned long> fileIds =
      databaseHandler.queryFileIdsForUpload(uploadId,ignoreFilesWithMimeType);

  bool errors = false;
#pragma omp parallel
  {
    ScancodeDatabaseHandler threadLocalDatabaseHandler(databaseHandler.spawn());

    size_t pFileCount = fileIds.size();
#pragma omp for
    for (size_t it = 0; it < pFileCount; ++it) {
      if (errors)
        continue;

      unsigned long pFileId = fileIds[it];

      if (pFileId == 0)
        continue;

      if (!matchPFileWithLicenses(state, pFileId, threadLocalDatabaseHandler)) {
        errors = true;
      }

      fo_scheduler_heart(1);
    }
  }

  return !errors;
}
/**
 * @brief match PFile with Licenses
 * @param state           State of the agent
 * @param pFileId         pfile Id of upload
 * @param databaseHandler Database handler object
 * @return true on success, false otherwise
 */
bool matchPFileWithLicenses(const State &state, unsigned long pFileId,
                            ScancodeDatabaseHandler &databaseHandler) {
  char *pFile = databaseHandler.getPFileNameForFileId(pFileId);

  if (!pFile) {
    LOG_FATAL("File not found %lu \n", pFileId);
    bail(8);
  }

  char *fileName = NULL;
  {
#pragma omp critical(repo_mk_path)
    fileName = fo_RepMkPath("files", pFile);
  }
  if (fileName) {
    fo::File file(pFileId, fileName);

    if (!matchFileWithLicenses(state, file, databaseHandler))
      return false;

    free(fileName);
    free(pFile);
  } else {
    LOG_FATAL("PFile not found in repo %lu \n", pFileId);
    bail(7);
  }
  return true;
}


/**
 * @brief match file with license
 * 
 * scan file with scancode and save result in the database
 *  
 * @param state state of the agent
 * @param file            code/binary file sent by scheduler 
 * @param databaseHandler databaseHandler Database handler object
 * @return true on saving scan result successfully, false otherwise
 */
bool matchFileWithLicenses(const State &state, const fo::File &file,
                           ScancodeDatabaseHandler &databaseHandler) {
  string scancodeResult = scanFileWithScancode(state, file);
map<string, vector<Match>> scancodeData =
      extractDataFromScancodeResult(scancodeResult, file.getFileName());

return saveLicenseMatchesToDatabase(
           state, scancodeData["scancode_license"], file.getId(),
           databaseHandler) &&
       saveOtherMatchesToDatabase(
           state, scancodeData["scancode_statement"], file.getId(),
           databaseHandler) &&
       saveOtherMatchesToDatabase(
           state, scancodeData["scancode_author"], file.getId(),
           databaseHandler);
}

/**
 * @brief save license matches to database
 * 
 * insert or catch license in license_ref table
 * save license in license_file table
 * save license highlight inforamtion in highlight table
 * 
 * @param state           state of the agent
 * @param matches         vector of objects of Match class
 * @param pFileId         pfile Id
 * @param databaseHandler databaseHandler Database handler object
 * @return true on success, false otherwise
 */
bool saveLicenseMatchesToDatabase(const State &state,
                                  const vector<Match> &matches,
                                  unsigned long pFileId,
                                  ScancodeDatabaseHandler &databaseHandler) 
                                  {
  for (const auto & match : matches) {
    databaseHandler.insertOrCacheLicenseIdForName(
        match.getMatchName(), match.getLicenseFullName(), match.getTextUrl());
  }

  if (!databaseHandler.begin()) {
    return false;
  }
  for (const auto & match : matches) {
    int agentId = state.getAgentId();
    string rfShortname = match.getMatchName();
    int percent = match.getPercentage();
    unsigned start = match.getStartPosition();
    unsigned length = match.getLength();
    unsigned long licenseId =
        databaseHandler.getCachedLicenseIdForName(rfShortname);

    if (licenseId == 0) {
      databaseHandler.rollback();
      LOG_ERROR("cannot get licenseId for shortname '%s' \n",
                rfShortname.c_str());
      return false;
    }
    if (rfShortname == "No_license_found") {
      if (!databaseHandler.insertNoResultInDatabase(agentId, pFileId, licenseId)) {
        databaseHandler.rollback();
        LOG_ERROR("failing save licenseMatch \n");
        return false;
      }
    } else {
      long licenseFileId = databaseHandler.saveLicenseMatch(agentId, pFileId,
                                                            licenseId, percent);
      if (licenseFileId > 0) {
        bool highlightRes =
            databaseHandler.saveHighlightInfo(licenseFileId, start, length);
        if (!highlightRes) {
          databaseHandler.rollback();
          LOG_ERROR("failing save licensehighlight \n");
        }
      } else {
        databaseHandler.rollback();
        LOG_ERROR("failing save licenseMatch \n");
        return false;
      }
    }
  }
  return databaseHandler.commit();
}

/**
 * @brief save copyright/copyright holder in the database
 * @param state           state of the agent
 * @param matches         vector of objects of Match class
 * @param pFileId         pfile Id
 * @param databaseHandler databaseHandler Database handler object
 * @return  true on success, false otherwise
 */
bool saveOtherMatchesToDatabase(const State &state,
                                const vector<Match> &matches,
                                unsigned long pFileId,
                                ScancodeDatabaseHandler &databaseHandler) {
  
  if (!databaseHandler.begin())
    return false;

  for (const auto & match : matches) {
    DatabaseEntry entry(match,state.getAgentId(),pFileId);

    if (!databaseHandler.insertInDatabase(entry))
    {
      databaseHandler.rollback();
      LOG_ERROR("failing save otherMatches \n");
      return false;
    }
  }
  return databaseHandler.commit();
}

// clueI add in this command line parser

/**
 * @brief parse command line options for scancode toolkit to get required falgs for scanning
 * @param[in] argc                     command line arguments count 
 * @param[in] argv                     command line argument vector 
 * @param[out] cliOption               command line options for scancode toolkit 
 * @param[out] ignoreFilesWithMimeType set this flag is user wants to ignore FilesWithMimeType
 * @return  true if parsing is successful otherwise false
 */
bool parseCommandLine(int argc, char **argv, string &cliOption, bool &ignoreFilesWithMimeType) 
{
  po::options_description desc(AGENT_NAME ": available options");
  desc.add_options()
  ("help,h", "show this help")
  ("ignoreFilesWithMimeType,I","ignoreFilesWithMimeType")
  ("license,l", "scancode license")
  ("copyright,r", "scancode copyright")
  ("email,e", "scancode email")
  ("url,u", "scancode url")
  ("config,c", po::value<string>(), "path to the sysconfigdir")
  ("scheduler_start", "specifies, that the command was called by the scheduler")
  ("userID", po::value<int>(), "the id of the user that created the job (only in combination with --scheduler_start)")
  ("groupID", po::value<int>(), "the id of the group of the user that created the job (only in combination with --scheduler_start)")
  ("jobId", po::value<int>(), "the id of the job (only in combination with --scheduler_start)");
  po::variables_map vm;
  try
  {
    po::store(po::command_line_parser(argc, argv).options(desc).run(), vm);
    if (vm.count("help") > 0) 
    {
      cout << desc << "\n";
      exit(EXIT_SUCCESS);
    }
    cliOption = "";
    cliOption += vm.count("license") > 0 ? "l" : "";
    cliOption += vm.count("copyright") > 0 ? "c" : "";
    cliOption += vm.count("email") > 0 ? "e" : "";
    cliOption += vm.count("url") > 0 ? "u" : "";
    ignoreFilesWithMimeType =
        vm.count("ignoreFilesWithMimeType") > 0 ? true : false;
  } 
  catch (boost::bad_any_cast &) 
  {
    LOG_FATAL("wrong parameter type\n ");
    cout << desc << "\n";
    return false;
  } 
  catch (po::error &) 
  {
    LOG_FATAL("wrong command line arguments\n");
    cout << desc << "\n";
    return false;
  }
  return true;
}