/*
 SPDX-FileCopyrightText: © 2014-2018,2022, Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file copyrightUtils.cc
 * \brief Utilities used by copyright and ecc agent
 */

#include "copyrightUtils.hpp"
#include <boost/program_options.hpp>

#include <iostream>
#include <sstream>

using namespace std;

/**
 * \brief Get agent id, exit if agent id is incorrect
 * \param[in]  dbConn Database connection object
 * \return ID of the agent
 */
int queryAgentId(PGconn* dbConn)
{
  char* COMMIT_HASH = fo_sysconfig(AGENT_NAME, "COMMIT_HASH");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char* agentRevision;
  if (!asprintf(&agentRevision, "%s.%s", VERSION, COMMIT_HASH))
  {
    exit(-1);
  };

  int agentId = fo_GetAgentKey(dbConn,
    AGENT_NAME, 0, agentRevision, AGENT_DESC);
  free(agentRevision);

  if (agentId > 0)
  {
    return agentId;
  }
  else
  {
    exit(1);
  }
}

/**
 * \brief Call C function fo_WriteARS() and translate the arguments
 * \see fo_WriteARS()
 */
int writeARS(int agentId, int arsId, int uploadId, int success, const fo::DbManager& dbManager)
{
  return fo_WriteARS(dbManager.getConnection(), arsId, uploadId, agentId, AGENT_ARS, NULL, success);
}

/**
 * \brief Disconnect with scheduler returning an error code and exit
 * \param exitval Error code
 */
void bail(int exitval)
{
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

/**
 * \brief Parse the options sent by CLI to CliOptions object
 * \param[in]  argc
 * \param[in]  argv
 * \param[out] dest      The parsed CliOptions object
 * \param[out] fileNames List of files to be scanned
 * \param[out] directoryToScan Directory to be scanned
 * \return True if success, false otherwise
 * \todo Change and add help based on IDENTITY
 */
bool parseCliOptions(int argc, char** argv, CliOptions& dest,
  std::vector<std::string>& fileNames, std::string& directoryToScan)
{
  unsigned type = 0;

  boost::program_options::options_description desc(IDENTITY ": recognized options");
  desc.add_options()
        ("help,h", "shows help")
        (
          "type,T",
          boost::program_options::value<unsigned>(&type)
            ->default_value(ALL_TYPES),
          "type of regex to try"
        ) // TODO change and add help based on IDENTITY
        (
          "verbose,v", "increase verbosity"
        )
        (
          "regex",
          boost::program_options::value<vector<string> >(),
          "user defined Regex to search: [{name=cli}@@][{matchingGroup=0}@@]{regex} e.g. 'linux@@1@@(linus) torvalds'"
        )
        (
          "files",
          boost::program_options::value< vector<string> >(),
          "files to scan"
        )
        (
          "json,J", "output JSON"
        )
        (
          "ignoreFilesWithMimeType,I", "ignoreFilesWithMimeType"
        )
        (
          "config,c", boost::program_options::value<string>(), "path to the sysconfigdir"
        )
        (
          "scheduler_start", "specifies, that the command was called by the scheduler"
        )
        (
          "userID", boost::program_options::value<int>(), "the id of the user that created the job (only in combination with --scheduler_start)"
        )
        (
          "groupID", boost::program_options::value<int>(), "the id of the group of the user that created the job (only in combination with --scheduler_start)"
        )
        (
          "jobId", boost::program_options::value<int>(), "the id of the job (only in combination with --scheduler_start)"
        )
        (
          "directory,d", boost::program_options::value<string>(), "directory to scan (recursive)"
        )
    ;

  boost::program_options::positional_options_description p;
  p.add("files", -1);

  boost::program_options::variables_map vm;

  try
  {
    boost::program_options::store(
      boost::program_options::command_line_parser(argc, argv).options(desc).positional(p).run(), vm);

    type = vm["type"].as<unsigned>();

    if ((vm.count("help") > 0) || (type > ALL_TYPES))
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
    bool ignoreFilesWithMimeType = vm.count("ignoreFilesWithMimeType") > 0 ? true : false;

    dest = CliOptions(verbosity, type, json, ignoreFilesWithMimeType);

    if (vm.count("regex"))
    {
      const std::vector<std::string>& userRegexesFmts = vm["regex"].as<vector<std::string> >();
      for (auto it = userRegexesFmts.begin(); it != userRegexesFmts.end(); ++it) {
        scanner* sc = makeRegexScanner(*it, "cli");
        if (!(sc))
        {
          cout << "cannot parse regex format : " << *it << endl;
          return false;
        }
        else
        {
          dest.addScanner(sc);
        }
      }
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
  catch (boost::bad_any_cast&) {
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
 * \brief Add default scanners to the agent state
 * \param state State to which scanners are being added
 */
static void addDefaultScanners(CopyrightState& state)
{
  unsigned types = state.getCliOptions().getOptType();
#ifdef IDENTITY_COPYRIGHT
  if (types & 1<<0)
    //state.addMatcher(RegexMatcher(regCopyright::getType(), regCopyright::getRegex()));
    state.addScanner(new hCopyrightScanner());

  if (types & 1<<1)
    state.addScanner(new regexScanner("url", "copyright"));

  if (types & 1<<2)
    state.addScanner(new regexScanner("email", "copyright", 1));

  if (types & 1<<3)
    state.addScanner(new regexScanner("author", "copyright"));
#endif

#ifdef IDENTITY_IPRA
  if (types & 1<<0)
    state.addScanner(new regexScanner("ipra", "ipra"));
#endif

#ifdef IDENTITY_ECC
  if (types & 1<<0)
    state.addScanner(new regexScanner("ecc", "ecc"));
#endif

#ifdef IDENTITY_KW
  if (types & 1<<0)
    state.addScanner(new regexScanner("keyword", "keyword"));
#endif
}

/**
 * \brief Make a boost regex scanner object based on regex desc and type
 * \param regexDesc   Regex format for scanner
 * \param defaultType Type of scanner
 * \return scanner object or NULL in case of error
 */
scanner* makeRegexScanner(const std::string& regexDesc, const std::string& defaultType) {
  #define RGX_FMT_SEPARATOR "@@"
  auto fmtRegex = rx::make_u32regex(
    "(?:([[:alpha:]]+)" RGX_FMT_SEPARATOR ")?(?:([[:digit:]]+)" RGX_FMT_SEPARATOR ")?(.*)",
    rx::regex_constants::icase
  );

  rx::match_results<std::string::const_iterator> match;
  if (rx::u32regex_match(regexDesc.begin(), regexDesc.end(), match, fmtRegex))
  {
    std::string const type(match.length(1) > 0 ? match.str(1) : defaultType);

    int regId = match.length(2) > 0 ? std::stoi(std::string(match.str(2))) : 0;

    if (match.length(3) == 0)
      return nullptr;

    std::string streamContent = type + "=" + match.str(3);

    std::wistringstream stream;
    stream.str(std::wstring(streamContent.begin(), streamContent.end()));
    return new regexScanner(type, stream, regId);
  }
  return nullptr;
}

/**
 * \brief Create a new state for the current agent based on CliOptions.
 *
 * Called during instantiation of agent.
 * \param cliOptions CliOptions passed to the agent
 * \return New CopyrightState object for the agent
 */
CopyrightState getState(CliOptions&& cliOptions)
{
  CopyrightState state(std::move(cliOptions));
  addDefaultScanners(state);

  return state;
}

/**
 * \brief Save findings to the database if agent was called by scheduler
 * \param s                        Statement found
 * \param matches                  List of regex matches for highlight
 * \param pFileId                  Id of pfile on which the statement was found
 * \param agentId                  Id of agent who discovered the statements
 * \param copyrightDatabaseHandler Database handler object
 * \return True of successful insertion, false otherwise
 */
bool saveToDatabase(const icu::UnicodeString& s, const list<match>& matches,
  unsigned long pFileId, int agentId,
  const CopyrightDatabaseHandler& copyrightDatabaseHandler)
{
  if (!copyrightDatabaseHandler.begin())
  {
    return false;
  }

  size_t count = 0;
  for (auto matche : matches)
  {

    DatabaseEntry entry;
    entry.agent_fk = agentId;
    entry.content = cleanMatch(s, matche);
    entry.copy_endbyte = matche.end;
    entry.copy_startbyte = matche.start;
    entry.pfile_fk = pFileId;
    entry.type = matche.type;

    if (!entry.content.isEmpty())
    {
      ++count;
      if (!copyrightDatabaseHandler.insertInDatabase(entry))
      {
        copyrightDatabaseHandler.rollback();
        return false;
      };
    }
  }

  return copyrightDatabaseHandler.commit();
}

/**
 * \brief Scan a given file with all available scanners and save findings to database
 * \param sContent        Content of file
 * \param pFileId         id of the pfile sent for scan
 * \param state           State of the agent
 * \param agentId         Agent id
 * \param databaseHandler Database handler used by agent
 */
void matchFileWithLicenses(const icu::UnicodeString& sContent,
  unsigned long pFileId, CopyrightState const& state, int agentId,
  CopyrightDatabaseHandler& databaseHandler)
{
  list<match> l;
  const list<unptr::shared_ptr<scanner>>& scanners = state.getScanners();
  for (const auto & scanner : scanners)
  {
    scanner->ScanString(sContent, l);
  }
  saveToDatabase(sContent, l, pFileId, agentId, databaseHandler);
}

/**
 * \brief Get the file contents, scan for statements and save findings to database
 *
 * Reads the file contents of the pFileId and send it for scanning to matchFileWithLicenses().
 *
 * If the pfile is not found for pFileId, bails with error code 8.
 *
 * If the pfile is not found in repository, bails with error code 7.
 * \param state           State of the agent
 * \param agentId         Agent id
 * \param pFileId         pFile to be scanned
 * \param databaseHandler Database handler used by agent
 */
void matchPFileWithLicenses(CopyrightState const& state, int agentId, unsigned long pFileId, CopyrightDatabaseHandler& databaseHandler)
{
  char* pFile = databaseHandler.getPFileNameForFileId(pFileId);

  if (!pFile)
  {
    cout << "File not found " << pFileId << endl;
    bail(8);
  }

  char* fileName = nullptr;
  {
#pragma omp critical (repo_mk_path)
    fileName = fo_RepMkPath("files", pFile);
  }
  if (fileName)
  {
    icu::UnicodeString s;
    ReadFileToString(fileName, s);

    matchFileWithLicenses(s, pFileId, state, agentId, databaseHandler);

    free(fileName);
    free(pFile);
  }
  else
  {
    cout << "PFile not found in repo " << pFileId << endl;
    bail(7);
  }
}

/**
 * \brief Process a given upload id, scan from statements and add to database
 *
 * The agent runs in parallel with the help of omp.
 * A new thread is created for every pfile.
 * \param state           State of the agent
 * \param agentId         Agent id
 * \param uploadId        Upload id to be processed
 * \param databaseHandler Database handler object
 * \param ignoreFilesWithMimeType To ignore files with particular mimetype
 * \return True when upload is processed
 */
bool processUploadId(const CopyrightState& state, int agentId, int uploadId, CopyrightDatabaseHandler& databaseHandler, bool ignoreFilesWithMimeType)
{
  vector<unsigned long> fileIds = databaseHandler.queryFileIdsForUpload(agentId, uploadId, ignoreFilesWithMimeType);

#pragma omp parallel num_threads(THREADS)
  {
    CopyrightDatabaseHandler threadLocalDatabaseHandler(databaseHandler.spawn());

    size_t pFileCount = fileIds.size();
#pragma omp for
    for (size_t it = 0; it < pFileCount; ++it)
    {
      unsigned long pFileId = fileIds[it];

      if (pFileId == 0)
      {
        continue;
      }

      matchPFileWithLicenses(state, agentId, pFileId, threadLocalDatabaseHandler);

      fo_scheduler_heart(1);
    }
  }

  return true;
}

/**
 * Read a single file and run all scanners on it based of CopyrightState.
 * @param state    Copyright state
 * @param fileName Location of the file to be scanned
 * @return A pair of file scanned and list of matches found.
 */
pair<icu::UnicodeString, list<match>> processSingleFile(const CopyrightState& state,
  const string fileName)
{
  const list<unptr::shared_ptr<scanner>>& scanners = state.getScanners();
  list<match> matchList;

  // Read file into one string
  icu::UnicodeString s;
  if (!ReadFileToString(fileName, s))
  {
    // File error
    s = u"";
  }
  else
  {
    for (const auto & scanner : scanners)
    {
      scanner->ScanString(s, matchList);
    }
  }
  return make_pair(s, matchList);
}

/**
 * Append a new result from scanner to main output json object
 * @param fileName   File which was scanned
 * @param resultPair The result pair from scanSingleFile()
 * @param printComma Set true to print comma. Will be set true after first
 *                   data is printed
 */
void appendToJson(const std::string& fileName,
    const std::pair<icu::UnicodeString, list<match>>& resultPair, bool &printComma)
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

  if (resultPair.first.length() == 0)
  {
    result["file"] = fileName;
    result["results"] = "Unable to read file";
  }
  else
  {
    list<match> resultList = resultPair.second;
    Json::Value results;
    for (auto m : resultList)
    {
      Json::Value j;
      std::string utf8Content;
      cleanMatch(resultPair.first, m).toUTF8String(utf8Content);
      j["start"] = m.start;
      j["end"] = m.end;
      j["type"] = m.type;
      j["content"] = utf8Content;
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
void printResultToStdout(const std::string& fileName,
    const std::pair<icu::UnicodeString, list<match>>& resultPair)
{
  if (resultPair.first.length() == 0)
  {
    cout << fileName << " :: Unable to read file" << endl;
    return;
  }
  stringstream ss;
  ss << fileName << " ::" << endl;
  // Output matches
  list<match> resultList = resultPair.second;
  for (auto & m : resultList)
  {
    std::string utf8Content;
    cleanMatch(resultPair.first, m).toUTF8String(utf8Content);
    ss << "\t[" << m.start << ':' << m.end << ':' << m.type << "] '"
       << utf8Content
       << "'" << endl;
  }
  // Thread-Safety: output all matches (collected in ss) at once to cout
  cout << ss.str();
}
