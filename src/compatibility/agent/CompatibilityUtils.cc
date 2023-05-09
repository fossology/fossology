/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * The utility functions for OJO agent
 */

#include "CompatibilityUtils.hpp"

#include "CompatibilityAgent.hpp"

#include <iostream>

using namespace fo;

/**
 * @brief Create a new state for the current agent based on CliOptions.
 *
 * Called during instantiation of agent.
 * @param cliOptions CLI options passed to the agent
 * @return New CompatibilityState object for the agent
 */
CompatibilityState getState(DbManager& dbManager,
                            CompatibilityCliOptions&& cliOptions)
{
  int agentId = queryAgentId(dbManager);
  return CompatibilityState(agentId, std::move(cliOptions));
}

/**
 * @brief Create a new state for the agent without DB manager
 * @param cliOptions CLI options passed
 * @return New CompatibilityState object
 */
CompatibilityState getState(CompatibilityCliOptions&& cliOptions)
{
  return CompatibilityState(-1, std::move(cliOptions));
}

/**
 * Query the agent ID from the DB.
 * @param dbManager DbManager to be used
 * @return The agent if found, bail otherwise.
 */
int queryAgentId(DbManager& dbManager)
{
  char* COMMIT_HASH = fo_sysconfig(AGENT_NAME, "COMMIT_HASH");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char* agentRevision;

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
int writeARS(const CompatibilityState& state, int arsId, int uploadId,
             int success, DbManager& dbManager)
{
  PGconn* connection = dbManager.getConnection();
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
 * @param groupId         Group who scheduled the agent
 * @return True in case of successful scan, false otherwise.
 */
bool processUploadId(const CompatibilityState& state, int uploadId,
                     CompatibilityDatabaseHandler& databaseHandler, int groupId)
{
  vector<unsigned long> fileIds =
      databaseHandler.queryFileIdsForScan(uploadId, state.getAgentId());
  vector<unsigned long> agentIds = databaseHandler.queryScannerIdsForUpload
                                   (uploadId);
  auto mainLicenses = databaseHandler.queryMainLicenseForUpload(uploadId,
                                                                groupId);
  bool errors = false;

#pragma omp parallel default(none) \
    shared(databaseHandler, fileIds, agentIds, state, errors, stdout, \
           mainLicenses)
  {
    CompatibilityDatabaseHandler threadLocalDatabaseHandler(
        databaseHandler.spawn());

    size_t pFileCount = fileIds.size();
    CompatibilityAgent agentObj = state.getCompatibilityAgent();
#pragma omp for
    for (size_t it = 0; it < pFileCount; ++it)
    {
      if (errors)
        continue;

      unsigned long pFileId = fileIds[it];

      if (pFileId == 0)
        continue;

      vector<unsigned long> licId =
          threadLocalDatabaseHandler.queryLicIdsFromPfile(pFileId, agentIds);
      if (!mainLicenses.empty())
      {
        set<unsigned long> licSet;
        licSet.insert(licId.begin(), licId.end());
        licSet.insert(mainLicenses.begin(), mainLicenses.end());

        licId.clear();
        licId.insert(licId.end(), licSet.begin(), licSet.end());
      }

      if (licId.size() < 2)
      {
        continue;
      }

      bool identified;
      try
      {
        identified = agentObj.checkCompatibilityForPfile(
            licId, pFileId,
            threadLocalDatabaseHandler); // pass licID vector, return the
                                         // result(true or false)
      }
      catch (std::runtime_error& e)
      {
        LOG_FATAL("Unable to read %s.", e.what());
        errors = true;
        continue;
      }

      if (!identified)
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
 * @brief Parse the options sent by CLI to CliOptions object
 * @param[in]  argc
 * @param[in]  argv
 * @param[out] dest      The parsed OjoCliOptions object
 * @param[out] types Path of the csv file to be scanned
 * @param[out] rules Path of the yaml file to be scanned
 * @param[out] jFile Path of the json file to be scanned
 * @param[out] mainLicense Main license for the package
 * @return True if success, false otherwise
 */
bool parseCliOptions(int argc, char** argv, CompatibilityCliOptions& dest,
                     std::string& types, std::string& rules, string& jFile,
                     string& mainLicense)
{
  boost::program_options::options_description desc(AGENT_NAME
                                                   ": recognized options");
  desc.add_options()
      ("help,h", "shows help")
      ("verbose,v", "increase verbosity")
      ("file,f", boost::program_options::value<string>(),
       "json file, containing fileNames and licenses within that fileNames")
      ("json,J", "output as JSON")
      ("main_license", boost::program_options::value<string>(),
       "name of main license to check licenses in files against")
      ("config,c", boost::program_options::value<string>(),
       "path to the sysconfigdir")
      ("scheduler_start",
       "specifies, that the agent was called by the scheduler")
      ("userID", boost::program_options::value<int>(),
      "the id of the user that created the job (only in combination with "
       "--scheduler_start)")
      ("groupID", boost::program_options::value<int>(),
       "the id of the group of the user that created the job (only in "
       "combination with --scheduler_start)")
      ("jobId", boost::program_options::value<int>(),
       "the id of the job (only in combination with --scheduler_start)")
      ("types,t", boost::program_options::value<string>(),
       "license types for compatibility rules")
      ("rules,r", boost::program_options::value<string>(),
       "license compatibility rules");

  boost::program_options::positional_options_description p;
  boost::program_options::variables_map vm;

  try
  {
    boost::program_options::store(
        boost::program_options::command_line_parser(argc, argv)
            .options(desc)
            .positional(p)
            .run(),
        vm);

    if (vm.count("help") > 0)
    {
      cout << desc << '\n';
      exit(0);
    }

    if (vm.count("rules"))
    {
      rules = vm["rules"].as<std::string>();
    }

    if (vm.count("types"))
    {
      types = vm["types"].as<std::string>();
    }

    if (vm.count("file"))
    {
      jFile = vm["file"].as<std::string>();
    }

    if (vm.count("main_license"))
    {
      mainLicense = vm["main_license"].as<std::string>();
    }

    int verbosity = (int) vm.count("verbose");
    bool json = vm.count("json") > 0;

    dest = CompatibilityCliOptions(verbosity, json);

    return true;
  }
  catch (boost::bad_any_cast&)
  {
    cout << "wrong parameter type\n";
    cout << desc << '\n';
    return false;
  }
  catch (boost::program_options::error&)
  {
    cout << "wrong command line arguments\n";
    cout << desc << '\n';
    return false;
  }
}

/**
 * Append a new result from scanner to STDOUT
 * @param fileName   File which was scanned
 * @param resultPair Contains the first license name, second license name and
 * their compatibility result
 * @param printComma Set true to print comma. Will be set true after first
 *                   data is printed
 */
void appendToJson(const std::vector<tuple<string, string, bool>>& resultPair,
                  const std::string& fileName, bool& printComma)
{
  Json::Value result;
#if JSONCPP_VERSION_HEXA < ((1 << 24) | (4 << 16))
  // Use FastWriter for versions below 1.4.0
  Json::FastWriter jsonBuilder;
#else
  // Since version 1.4.0, FastWriter is deprecated and replaced with
  // StreamWriterBuilder
  Json::StreamWriterBuilder jsonBuilder;
  jsonBuilder["commentStyle"] = "None";
  jsonBuilder["indentation"] = "";
#endif
  // jsonBuilder.omitEndingLineFeed();
  Json::Value licenses;
  Json::Value res(Json::arrayValue);
  for (const auto& i : resultPair)
  {
    Json::Value license(Json::arrayValue);
    Json::Value comp;
    Json::Value final;
    license.append(get<0>(i));
    license.append(get<1>(i));
    comp = get<2>(i);
    final["license"] = license;
    final["compatibility"] = comp;
    res.append(final);
  }

  if (fileName != "null")
  {
    result["file"] = fileName;
    result["results"] = res;
  }
  else
  {
    result["package-level-result"] = res;
  }

  // Thread-Safety: output all matches JSON at once to STDOUT
#pragma omp critical(jsonPrinter)
  {
    if (printComma)
    {
      cout << ",\n";
    }
    else
    {
      printComma = true;
    }
    string jsonString;
#if JSONCPP_VERSION_HEXA < ((1 << 24) | (4 << 16))
    // For version below 1.4.0, every writer append `\n` at end.
    // Find and replace it.
    jsonString = jsonBuilder.write(result);
    jsonString.replace(jsonString.find("\n"), string("\n").length(), "");
#else
    // For version >= 1.4.0, \n is not appended.
    jsonString = Json::writeString(jsonBuilder, result);
#endif
    cout << "  " << jsonString;
  }
}

/**
 * Print the result of current scan to stdout
 * @param fileName   File which was scanned
 * @param resultPair Contains the first license name, second license name and
 * their compatibility result
 */
void printResultToStdout(
    const std::vector<tuple<string, string, bool>>& resultPair,
    const std::string& fileName)
{
  stringstream ss;
  if (fileName != "null")
  {
    cout << "----" << fileName << "----\n";
    for (const auto& i : resultPair)
    {
      string result = get<2>(i) ? "true" : "false";
      cout << get<0>(i) << "," << get<1>(i) << "::" << result << '\n';
    }
  }
  else
  {
    cout << "----all licenses with their compatibility----\n";
    for (const auto& i : resultPair)
    {
      string result = get<2>(i) ? "true" : "false";
      cout << get<0>(i) << "," << get<1>(i) << "::" << result << '\n';
    }
  }
}

/**
 * Converts a main license string (which may contain AND) to a set of licenses.
 * @param mainLicense Main license string from CLI
 * @return List of individual licenses
 */
std::set<std::string> mainLicenseToSet(const string& mainLicense)
{
  std::set<std::string> licenses;
  std::string delimiter = " AND ";
  std::string s = mainLicense;
  size_t pos;

  while ((pos = s.find(delimiter)) != std::string::npos) {
    licenses.insert(s.substr(0, pos));
    s.erase(0, pos + delimiter.length());
  }
  if (licenses.empty()) {
    licenses.insert(mainLicense);
  }
  return licenses;
}
