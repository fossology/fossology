/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * The utility functions for OJO agent
 */

#include <iostream>

#include "OjoUtils.hpp"
#include "OjoAgent.hpp"

using namespace fo;

/**
 * @brief Create a new state for the current agent based on CliOptions.
 *
 * Called during instantiation of agent.
 * @param cliOptions CLI options passed to the agent
 * @return New OjoState object for the agent
 */
OjoState getState(DbManager &dbManager, OjoCliOptions &&cliOptions)
{
  int agentId = queryAgentId(dbManager);
  return OjoState(agentId, std::move(cliOptions));
}

/**
 * @brief Create a new state for the agent without DB manager
 * @param cliOptions CLI options passed
 * @return New OjoState object
 */
OjoState getState(OjoCliOptions &&cliOptions)
{
  return OjoState(-1, std::move(cliOptions));
}

/**
 * Query the agent ID from the DB.
 * @param dbManager DbManager to be used
 * @return The agent if found, bail otherwise.
 */
int queryAgentId(DbManager &dbManager)
{
  char* COMMIT_HASH = fo_sysconfig(AGENT_NAME, "COMMIT_HASH");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
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
 * Write ARS to the agent's ars table
 * @param state     State of the agent
 * @param arsId     ARS id (0 for new entry)
 * @param uploadId  Upload ID
 * @param success   Success status
 * @param dbManager DbManager to use
 * @return ARS ID.
 */
int writeARS(const OjoState &state, int arsId, int uploadId, int success,
    DbManager &dbManager)
{
  PGconn *connection = dbManager.getConnection();
  int agentId = state.getAgentId();

  return fo_WriteARS(connection, arsId, uploadId, agentId, AGENT_ARS, NULL,
    success);
}

/**
 * Disconnect scheduler and exit in case of failure.
 * @param exitval Exit code to be sent to scheduler and returned by program
 */
void bail(int exitval)
{
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

/**
 * Process a given upload id
 * @param state           State of the agent
 * @param uploadId        Upload ID to be scanned
 * @param databaseHandler Database handler to be used
 * @param ignoreFilesWithMimeType To ignore files with particular mimetype
 * @return True in case of successful scan, false otherwise.
 */
bool processUploadId(const OjoState &state, int uploadId,
    OjosDatabaseHandler &databaseHandler, bool ignoreFilesWithMimeType)
{
  vector<unsigned long> fileIds = databaseHandler.queryFileIdsForUpload(
      uploadId, state.getAgentId(), ignoreFilesWithMimeType);
  char const *repoArea = "files";

  bool errors = false;
#pragma omp parallel
  {
    OjosDatabaseHandler threadLocalDatabaseHandler(databaseHandler.spawn());

    size_t pFileCount = fileIds.size();
    OjoAgent agentObj = state.getOjoAgent();
#pragma omp for
    for (size_t it = 0; it < pFileCount; ++it)
    {
      if (errors)
        continue;

      unsigned long pFileId = fileIds[it];

      if (pFileId == 0)
        continue;

      char *fileName = threadLocalDatabaseHandler.getPFileNameForFileId(
        pFileId);
      char *filePath = NULL;
#pragma omp critical (repo_mk_path)
      filePath = fo_RepMkPath(repoArea, fileName);

      if (!filePath)
      {
        LOG_FATAL(
          AGENT_NAME" was unable to derive a file path for pfile %ld.  Check your HOSTS configuration.",
          pFileId);
        errors = true;
      }

      vector<ojomatch> identified;
      try
      {
        identified = agentObj.processFile(filePath, threadLocalDatabaseHandler,
                                          state.getCliOptions().getGroupId(),
                                          state.getCliOptions().getUserId());
      }
      catch (std::runtime_error &e)
      {
        LOG_FATAL("Unable to read %s.", e.what());
        continue;
      }

      if (!storeResultInDb(identified, threadLocalDatabaseHandler,
          state.getAgentId(), pFileId))
      {
        LOG_FATAL("Unable to store results in database for pfile %ld.",
          pFileId);
        bail(-20);
      }

      fo_scheduler_heart(1);
    }
  }

  return !errors;
}

/**
 * @brief Store the results from scan to DB.
 *
 * Store the license finding (if found) and highlight to the database.
 *
 * Store not found entries for empty matches to the database.
 * @param matches        List of matches.
 * @param databaseHandle Database handler to be used
 * @param agent_fk       Current agent id
 * @param pfile_fk       Current pfile id
 * @return True on success, false otherwise.
 */
bool storeResultInDb(const vector<ojomatch> &matches,
    OjosDatabaseHandler &databaseHandle, const int agent_fk, const int pfile_fk)
{
  if (!databaseHandle.begin())
  {
    return false;
  }

  size_t count = 0;
  if (matches.size() == 0)
  {
    OjoDatabaseEntry entry(-1, agent_fk, pfile_fk);
    databaseHandle.insertNoResultInDatabase(entry);
    return databaseHandle.commit();
  }
  for (auto m : matches)
  {
    OjoDatabaseEntry entry(m.license_fk, agent_fk, pfile_fk);

    if (entry.license_fk > 0)
    {
      ++count;
      unsigned long int fl_pk = databaseHandle.saveLicenseToDatabase(entry);
      if (!(fl_pk > 0) || !databaseHandle.saveHighlightToDatabase(m, fl_pk))
      {
        databaseHandle.rollback();
        return false;
      }
    }
    else
    {
      databaseHandle.insertNoResultInDatabase(entry);
    }
  }

  return databaseHandle.commit();
}

/**
 * @brief Parse the options sent by CLI to CliOptions object
 * @param[in]  argc
 * @param[in]  argv
 * @param[out] dest      The parsed OjoCliOptions object
 * @param[out] fileNames List of files to be scanned
 * @param[out] directoryToScan Path of the directory to be scanned
 * @return True if success, false otherwise
 */
bool parseCliOptions(int argc, char **argv, OjoCliOptions &dest,
    std::vector<std::string> &fileNames, string &directoryToScan)
{
  boost::program_options::options_description desc(
    AGENT_NAME ": recognized options");
  desc.add_options()
    (
      "help,h", "shows help"
    )
    (
      "verbose,v", "increase verbosity"
    )
    (
      "files",
      boost::program_options::value<vector<string> >(),
      "files to scan"
    )
    (
      "json,J", "output JSON"
    )
    (
      "ignoreFilesWithMimeType,I", "ignoreFilesWithMimeType"
    )
    (
      "config,c",
      boost::program_options::value<string>(),
      "path to the sysconfigdir"
    )
    (
      "scheduler_start",
      "specifies, that the command was called by the scheduler"
    )
    (
      "userID",
      boost::program_options::value<int>(),
      "the id of the user that created the job (only in combination with --scheduler_start)"
    )
    (
      "groupID",
      boost::program_options::value<int>(),
      "the id of the group of the user that created the job (only in combination with --scheduler_start)"
    )
    (
      "jobId",
      boost::program_options::value<int>(),
      "the id of the job (only in combination with --scheduler_start)"
    )
    (
      "directory,d",
      boost::program_options::value<string>(),
      "directory to scan (recursive)"
    )
    ;

  boost::program_options::positional_options_description p;
  p.add("files", -1);

  boost::program_options::variables_map vm;

  try
  {
    boost::program_options::store(
      boost::program_options::command_line_parser(argc, argv).options(desc).positional(
        p).run(), vm);

    if (vm.count("help") > 0)
    {
      cout << desc << endl;
      exit(0);
    }

    if (vm.count("files"))
    {
      fileNames = vm["files"].as<std::vector<string> >();
    }

    unsigned long verbosity = vm.count("verbose");
    bool json = vm.count("json") > 0 ? true : false;
    bool  ignoreFilesWithMimeType = vm.count("ignoreFilesWithMimeType") > 0 ?  true : false;

    dest = OjoCliOptions(verbosity, json, ignoreFilesWithMimeType);

    if (vm.count("userID") > 0)
    {
      dest.setUserId(vm["userID"].as<int>());
    }

    if (vm.count("groupID") > 0)
    {
      dest.setGroupId(vm["groupID"].as<int>());
    }

    if (vm.count("directory"))
    {
      if (vm.count("files"))
      {
        cout << "cannot pass files and directory at the same time" << endl;
        cout << desc << endl;
        fileNames.clear();
        return false;
      }
      directoryToScan = vm["directory"].as<std::string>();
    }

    return true;
  }
  catch (boost::bad_any_cast&)
  {
    cout << "wrong parameter type" << endl;
    cout << desc << endl;
    return false;
  }
  catch (boost::program_options::error&)
  {
    cout << "wrong command line arguments" << endl;
    cout << desc << endl;
    return false;
  }
}

/**
 * Append a new result from scanner to STDOUT
 * @param fileName   File which was scanned
 * @param resultPair The result pair from scanSingleFile()
 * @param printComma Set true to print comma. Will be set true after first
 *                   data is printed
 */
void appendToJson(const std::string fileName,
    const std::pair<string, vector<ojomatch>> resultPair,
    bool &printComma)
{
  Json::Value result;
#if JSONCPP_VERSION_HEXA < ((1 << 24) | (4 << 16))
  // Use FastWriter for versions below 1.4.0
  Json::FastWriter jsonWriter;
#else
  // Since version 1.4.0, FastWriter is deprecated and replaced with
  // StreamWriterBuilder
  Json::StreamWriterBuilder jsonWriter;
  jsonWriter["commentStyle"] = "None";
  jsonWriter["indentation"] = "";
#endif
  if (resultPair.first.empty())
  {
    result["file"] = fileName;
    result["results"] = "Unable to read file";
  }
  else
  {
    vector<ojomatch> resultList = resultPair.second;
    Json::Value results;
    for (auto m : resultList)
    {
      Json::Value j;
      j["start"] = Json::Value::UInt(m.start);
      j["end"] = Json::Value::UInt(m.end);
      j["len"] = Json::Value::UInt(m.len);
      j["license"] = m.content;
      results.append(j);
    }
    result["file"] = fileName;
    result["results"] = results;
  }
  // Thread-Safety: output all matches JSON at once to STDOUT
#pragma omp critical (jsonPrinter)
  {
    if (printComma)
    {
      cout << "," << endl;
    }
    else
    {
      printComma = true;
    }
    string jsonString;
#if JSONCPP_VERSION_HEXA < ((1 << 24) | (4 << 16))
    // For version below 1.4.0, every writer append `\n` at end.
    // Find and replace it.
    jsonString = jsonWriter.write(result);
    jsonString.replace(jsonString.find("\n"), string("\n").length(), "");
#else
    // For version >= 1.4.0, \n is not appended.
    jsonString = Json::writeString(jsonWriter, result);
#endif
    cout << "  " << jsonString << flush;
  }
}

/**
 * Print the result of current scan to stdout
 * @param fileName   File which was scanned
 * @param resultPair Result pair from scanSingleFile()
 */
void printResultToStdout(const std::string fileName,
    const std::pair<string, vector<ojomatch>> resultPair)
{
  if (resultPair.first.empty())
  {
    cout << fileName << " :: Unable to read file" << endl;
    return;
  }
  stringstream ss;
  ss << fileName << " ::" << endl;
  // Output matches
  vector<ojomatch> resultList = resultPair.second;
  for (auto m : resultList)
  {
    ss << "\t[" << m.start << ':' << m.end << "]: '" << m.content << "'" << endl;
  }
  // Thread-Safety: output all matches (collected in ss) at once to cout
  cout << ss.str();
}
