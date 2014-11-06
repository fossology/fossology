/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini, Johannes Najjar
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "copyrightUtils.hpp"
#include <boost/program_options.hpp>

#include <iostream>

using namespace std;

void queryAgentId(int& agent, PGconn* dbConn)
{
  char* SVN_REV = fo_sysconfig(AGENT_NAME, "SVN_REV");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char* agentRevision;
  if (!asprintf(&agentRevision, "%s.%s", VERSION, SVN_REV))
  {
    exit(-1);
  };

  int agentId = fo_GetAgentKey(dbConn,
    AGENT_NAME, 0, agentRevision, AGENT_DESC);
  free(agentRevision);

  if (agentId > 0)
  {
    agent = agentId;
  }
  else
  {
    exit(1);
  }
}

int writeARS(CopyrightState& state, int arsId, int uploadId, int success, const fo::DbManager& dbManager)
{
  return fo_WriteARS(dbManager.getConnection(), arsId, uploadId, state.getAgentId(), AGENT_ARS, NULL, success);
}

void bail(int exitval)
{
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

bool parseCliOptions(int argc, char const* const* const argv, CliOptions& dest, std::vector<std::string>& fileNames)
{
  unsigned type;

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
          boost::program_options::value<string>(),
          "user defined Regex to search"
        )
        (
          "regexId",
          boost::program_options::value<unsigned>()
            ->default_value(0),
          "subexpression Id for user defined Regex"
        )
        (
          "files",
          boost::program_options::value< vector<string> >(),
          "files to scan"
        );

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
      return false;
    }

    if (vm.count("files"))
    {
      fileNames = vm["files"].as<std::vector<string> >();
    }

    int verbosity = vm.count("verbosity");

    if (vm.count("regex"))
    {
      std::string regex = vm["regex"].as<std::string>();

      unsigned regexId = vm["regexId"].as<unsigned>();

      dest = CliOptions(verbosity, type, regex, regexId);
    }
    else
    {
      dest = CliOptions(verbosity, type);
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

CopyrightState getState(fo::DbManager dbManager, const CliOptions& cliOptions)
{
  int agentID;
  queryAgentId(agentID, dbManager.getConnection());

  return CopyrightState(agentID, cliOptions);
}

void fillMatchers(CopyrightState& state)
{
  const CliOptions& cliOptions = state.getCliOptions();

  if (cliOptions.hasExtraRegex()) {
    state.addMatcher(RegexMatcher("cli", cliOptions.getExtraRegex(), cliOptions.getExtraRegexId()));
  }

#ifdef IDENTITY_COPYRIGHT
  unsigned types = cliOptions.getOptType();

  if (types & 1<<0)
    state.addMatcher(RegexMatcher(regCopyright::getType(), regCopyright::getRegex()));

  if (types & 1<<1)
    state.addMatcher(RegexMatcher(regURL::getType(), regURL::getRegex()));

  if (types & 1<<2)
    state.addMatcher(RegexMatcher(regEmail::getType(), regEmail::getRegex(), 1)); // TODO move 1 to getRegexId
#endif

#ifdef IDENTITY_IP
  state.addMatcher(RegexMatcher(regIp::getType(), regIp::getRegex()));
#endif

#ifdef IDENTITY_ECC
  state.addMatcher(RegexMatcher(regEcc::getType(), regEcc::getRegex()));
#endif

  if (cliOptions.getVerbosity() >= CliOptions::verbosityLevels::DEBUG)
  {
    const vector<RegexMatcher>& matchers = state.getRegexMatchers();

    for (auto it = matchers.begin(); it != matchers.end(); ++it) {
      std::cout << *it << std::endl;
    }
  }
}

vector<CopyrightMatch> matchStringToRegexes(const string& content, vector<RegexMatcher> matchers)
{
  vector<CopyrightMatch> result;

  typedef std::vector<RegexMatcher>::const_iterator rgm;
  for (rgm item = matchers.begin(); item != matchers.end(); ++item)
  {
    vector<CopyrightMatch> newMatch = item->match(content);
    result.insert(result.end(), newMatch.begin(), newMatch.end());
  }

  return result;
}

bool saveToDatabase(const vector<CopyrightMatch>& matches, unsigned long pFileId, int agentId, const CopyrightDatabaseHandler& copyrightDatabaseHandler)
{
  if (!copyrightDatabaseHandler.begin())
  {
    return false;
  }

  size_t count = 0;
  typedef vector<CopyrightMatch>::const_iterator cpm;
  for (cpm it = matches.begin(); it != matches.end(); ++it)
  {
    const CopyrightMatch& match = *it;

    DatabaseEntry entry;
    entry.agent_fk = agentId;
    entry.content = match.getContent();
    entry.copy_endbyte = match.getStart() + match.getLength();
    entry.copy_startbyte = match.getStart();
    entry.pfile_fk = pFileId;
    entry.type = match.getType();

    if (normalizeDatabaseEntry(entry))
    {
      ++count;
      if (!copyrightDatabaseHandler.insertInDatabase(entry))
      {
        copyrightDatabaseHandler.rollback();
        return false;
      };
    }
  }

  if (count == 0)
  {
    copyrightDatabaseHandler.insertNoResultInDatabase(agentId, pFileId);
  }

  return copyrightDatabaseHandler.commit();
};

vector<CopyrightMatch> findAllMatches(const fo::File& file, vector<RegexMatcher> const regexMatchers)
{
  if (!file.isReadable())
  {
    cout << "File not readable: " << file.getFileName() << endl;
    bail(9);
  }

  string fileContent = file.getContent(0);

  normalizeContent(fileContent);

  return matchStringToRegexes(fileContent, regexMatchers);
}

//TODO normalize the content
void normalizeContent(string& content)
{
  for (std::string::iterator it = content.begin(); it != content.end(); ++it)
  {
    char& charachter = *it;

    if (charachter == '*')
      charachter = ' ';
  }
}

void matchFileWithLicenses(const fo::File& file, CopyrightState const& state, CopyrightDatabaseHandler& databaseHandler)
{
  vector<CopyrightMatch> matches = findAllMatches(file, state.getRegexMatchers());
  saveToDatabase(matches, file.getId(), state.getAgentId(), databaseHandler);
}

void matchPFileWithLicenses(CopyrightState const& state, unsigned long pFileId, CopyrightDatabaseHandler& databaseHandler)
{
  char* pFile = databaseHandler.getPFileNameForFileId(pFileId);

  if (!pFile)
  {
    cout << "File not found " << pFileId << endl;
    bail(8);
  }

  char* fileName = NULL;
  {
#pragma omp critical (repo_mk_path)
    fileName = fo_RepMkPath("files", pFile);
  }
  if (fileName)
  {
    fo::File file(pFileId, fileName);

    matchFileWithLicenses(file, state, databaseHandler);

    free(fileName);
    free(pFile);
  }
  else
  {
    cout << "PFile not found in repo " << pFileId << endl;
    bail(7);
  }
}

bool processUploadId(const CopyrightState& state, int uploadId, CopyrightDatabaseHandler& databaseHandler)
{
  vector<unsigned long> fileIds = databaseHandler.queryFileIdsForUpload(state.getAgentId(), uploadId);

#pragma omp parallel
  {
    CopyrightDatabaseHandler threadLocalDatabaseHandler(databaseHandler.spawn());

    size_t pFileCount = fileIds.size();
#pragma omp for
    for (size_t it = 0; it < pFileCount; ++it)
    {
      unsigned long pFileId = fileIds[it];

      if (pFileId <= 0)
      {
        continue;
      }

      matchPFileWithLicenses(state, pFileId, threadLocalDatabaseHandler);

      fo_scheduler_heart(1);
    }
  }

  return true;
}
