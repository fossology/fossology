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

#include <iostream>
#include "atarashiwrapper.hpp"
#include "utils.hpp"

using namespace fo;

namespace po = boost::program_options;

State getState(DbManager& dbManager)
{
  int agentId = queryAgentId(dbManager);
  return State(agentId);
}

int queryAgentId(DbManager& dbManager)
{
  char* COMMIT_HASH = fo_sysconfig(AGENT_NAME, "COMMIT_HASH");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char* agentRevision;

  if (!asprintf(&agentRevision, "%s.%s", VERSION, COMMIT_HASH))
    bail(-1);

  int agentId = fo_GetAgentKey(dbManager.getConnection(), AGENT_NAME, 0, agentRevision, AGENT_DESC);
  free(agentRevision);

  if (agentId <= 0)
    bail(1);

  return agentId;
}

int writeARS(const State& state, int arsId, int uploadId, int success, DbManager& dbManager)
{
  PGconn* connection = dbManager.getConnection();
  int agentId = state.getAgentId();

  return fo_WriteARS(connection, arsId, uploadId, agentId, AGENT_ARS, NULL, success);
}

void bail(int exitval)
{
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

bool processUploadId(const State& state, int uploadId, AtarashiDatabaseHandler& databaseHandler, bool ignoreFilesWithMimeType)
{
  vector<unsigned long> fileIds = databaseHandler.queryFileIdsForUpload(uploadId,ignoreFilesWithMimeType);
  unordered_map<unsigned long, string> fileIdsMap;
  unordered_map<string, unsigned long> fileIdsMapReverse;

  size_t pFileCount = fileIds.size();
  for (size_t it = 0; it < pFileCount; ++it) {
    unsigned long pFileId = fileIds[it];
    if (pFileId == 0) continue;

    mapFileNameWithId(pFileId, fileIdsMap, fileIdsMapReverse, databaseHandler);

    fo_scheduler_heart(1);
  }

  string fileLocation = tmpnam(nullptr);
  string outputFile   = tmpnam(nullptr);

  writeFileNameToTextFile(fileIdsMap, fileLocation);

  scanFileWithAtarashi(state, fileLocation, outputFile);

  std::ifstream opfile(outputFile);
  if (!opfile) {
    std::cerr << "Error opening the Atarashi JSON file.\n";
    return false;
  }
  vector<string> atarashiResults;
  string line;
  while (getline(opfile, line)) {
    atarashiResults.push_back(getScanResult(line));
  }

  bool errors = false;

#pragma omp parallel default(none) \
    shared(databaseHandler, atarashiResults, fileIdsMapReverse, state, errors)
  {
    AtarashiDatabaseHandler threadLocalDatabaseHandler(databaseHandler.spawn());
#pragma omp for
    for (size_t i = 0; i < atarashiResults.size(); ++i) {
      string result = atarashiResults[i];

      Json::CharReaderBuilder json_reader_builder;
      auto scanner = std::unique_ptr<Json::CharReader>(json_reader_builder.newCharReader());
      Json::Value atarashiValue;
      string errs;
      const bool isSuccessful = scanner->parse(
          result.c_str(),
          result.c_str() + result.length(),
          &atarashiValue,
          &errs);

      if (isSuccessful) {
        string fileName = atarashiValue["file"].asString();
        unsigned long fileId = fileIdsMapReverse[fileName];

        if (!matchFileWithLicenses(state, result, fileName, fileId, threadLocalDatabaseHandler)) {
          errors = true;
        }
      }
    }
  }

  if (unlink(outputFile.c_str()) != 0) {
    LOG_FATAL("Unable to delete file %s \n", outputFile.c_str());
  }
  return !errors;
}

/**
 * @brief Map file name with file id
 * @param pFileId          File id
 * @param fileIdsMap       Map of file id → file name
 * @param fileIdsMapReverse Map of file name → file id
 * @param databaseHandler  Database handler object
 */
void mapFileNameWithId(unsigned long pFileId,
                      unordered_map<unsigned long, string> &fileIdsMap,
                      unordered_map<string, unsigned long> &fileIdsMapReverse,
                      AtarashiDatabaseHandler &databaseHandler) {
  char *pFile = databaseHandler.getPFileNameForFileId(pFileId);
  if (!pFile) {
    LOG_FATAL("File not found %lu \n", pFileId);
    bail(8);
  }

  char *fileName = fo_RepMkPath("files", pFile);
  if (fileName) {
    fo::File file(pFileId, fileName);

    fileIdsMap[file.getId()] = file.getFileName();
    fileIdsMapReverse[file.getFileName()] = file.getId();

    free(fileName);
    free(pFile);
  } else {
    LOG_FATAL("PFile not found in repo %lu \n", pFileId);
    bail(7);
  }
}

/**
 * @brief Match file with licenses (from parsed Atarashi result)
 * @param state           State of the agent
 * @param atarashiResult  Raw JSON result string for the file
 * @param filename        File name
 * @param fileId          File ID
 * @param databaseHandler Database handler
 */
bool matchFileWithLicenses(const State& state,
                          const string& atarashiResult,
                          const string& filename,
                          unsigned long fileId,
                          AtarashiDatabaseHandler& databaseHandler)
{
  vector<LicenseMatch> matches = extractLicensesFromAtarashiResult(atarashiResult);
  for (const auto& match : matches) {
    cout << "\nLicense: " << match.getLicenseName()
        << ", Similarity: " << match.getPercentage() << "%" << endl;
  }
  return saveLicenseMatchesToDatabase(state, matches, fileId, databaseHandler);
}

/**
 * @brief Write filenames to a text file
 */
void writeFileNameToTextFile(unordered_map<unsigned long, string>& fileIdsMap,
                            string fileLocation)
{
  std::ofstream outputFile(fileLocation, std::ios::app);
  if (!outputFile.is_open()) {
    LOG_FATAL("Unable to open file");
  }

  for (auto const& x : fileIdsMap) {
    outputFile << x.second << "\n";
  }

  outputFile.close();
}

/**
 * @brief Extract JSON object from line
 */
string getScanResult(const string& line) {
  string scanResult;
  size_t startIndex = 0;
  size_t braceCount = 0;

  for (size_t i = 0; i < line.length(); ++i) {
    char c = line[i];
    if (c == '{') {
      if (braceCount == 0) {
        startIndex = i;
      }
      braceCount++;
    } else if (c == '}') {
      braceCount--;
      if (braceCount == 0) {
        scanResult = line.substr(startIndex, i - startIndex + 1);
        break;
      }
    }
  }
  return scanResult;
}

bool saveLicenseMatchesToDatabase(const State& state, const vector<LicenseMatch>& matches, unsigned long pFileId, AtarashiDatabaseHandler& databaseHandler)
{
  for (vector<LicenseMatch>::const_iterator it = matches.begin(); it != matches.end(); ++it)
  {
    const LicenseMatch& match = *it;
    databaseHandler.insertOrCacheLicenseIdForName(match.getLicenseName());
  }

  if (!databaseHandler.begin())
    return false;

  for (vector<LicenseMatch>::const_iterator it = matches.begin(); it != matches.end(); ++it)
  {
    const LicenseMatch& match = *it;

    int agentId = state.getAgentId();
    string rfShortname = match.getLicenseName();
    unsigned percent = match.getPercentage();

    unsigned long licenseId = databaseHandler.getCachedLicenseIdForName(rfShortname);

    if (licenseId == 0)
    {
      databaseHandler.rollback();
      cout << "cannot get licenseId for shortname '" + rfShortname + "'" << endl;
      return false;
    }

    if (!databaseHandler.saveLicenseMatch(agentId, pFileId, licenseId, percent))
    {
      databaseHandler.rollback();
      cout << "failing save licenseMatch" << endl;
      return false;
    };
  }

  return databaseHandler.commit();
}

bool parseCommandLine(int argc, char **argv,
                      string &agentName,
                      string &similarityMethod,
                      bool &verboseMode)
{
  po::options_description desc(AGENT_NAME ": available options");

  desc.add_options()
    ("help,h", "show help message")
    ("agent,a", po::value<string>(), "Atarashi agent to use (DLD, tfidf, Ngram, wordFrequencySimilarity.)")
    ("similarity,s", po::value<string>(), "Similarity method (CosineSim, DiceSim, ScoreSim.)")
    ("verbose,v", "Enable verbose mode")
    ("config,c", po::value<string>(), "path to the sysconfigdir")
    ("scheduler_start", "specifies, that the command was called by the scheduler")
    ("userID", po::value<int>(), "the id of the user that created the job (only in combination with --scheduler_start)")
    ("groupID", po::value<int>(), "the id of the group of the user that created the job (only in combination with --scheduler_start)")
    ("jobId", po::value<int>(), "the id of the job (only in combination with --scheduler_start)");

  po::variables_map vm;

  try
  {
    po::store(po::command_line_parser(argc, argv).options(desc).run(), vm);
    po::notify(vm);

    if (vm.count("help") > 0)
    {
      std::cout << desc << "\n";
      exit(EXIT_SUCCESS);
    }

    if (vm.count("agent")){
      agentName = vm["agent"].as<std::string>();
    }

    if (vm.count("similarity")){
      similarityMethod = vm["similarity"].as<std::string>();
    }

    verboseMode = vm.count("verbose") > 0;
  }

  catch (const boost::bad_any_cast &e)
  {
    std::cerr << "Parameter type error: " << e.what() << "\n";
    std::cout << desc << "\n";
    return false;
  }
  catch (const po::error &e)
  {
    std::cerr << "Command line parsing error: " << e.what() << "\n";
    std::cout << desc << "\n";
    return false;
  }

  return true;
}