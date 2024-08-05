/*
 SPDX-FileCopyrightText: © 2014,2022, Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef COPYRIGHTUTILS_HPP_
#define COPYRIGHTUTILS_HPP_

#include "identity.hpp"

#define AGENT_NAME IDENTITY ///< the name of the agent, used to get agent key
#define AGENT_DESC IDENTITY " agent" ///< what program this is
#define AGENT_ARS  IDENTITY "_ars"

#include <string>
#include <vector>
#include <list>
#include <json/json.h>

#include "scanners.hpp"
#include "regscan.hpp"
#include "copyscan.hpp"

#include "copyrightState.hpp"
#include "database.hpp"
#include "cleanEntries.hpp"
#define THREADS 2

extern "C" {
#include "libfossology.h"
}

int queryAgentId(PGconn* dbConn);

void bail(int exitval);

int writeARS(int agentId, int arsId, int uploadId, int success, const fo::DbManager& dbManager);

bool parseCliOptions(int argc, char** argv, CliOptions& dest,
    std::vector<std::string>& fileNames, std::string& directoryToScan);

CopyrightState getState(CliOptions&& cliOptions);

scanner* makeRegexScanner(const std::string& regexDesc, const std::string& defaultType);
/*
std::vector<CopyrightMatch> matchStringToRegexes(const std::string& content, std::vector<RegexMatcher> matchers);
*/
void normalizeContent(std::string& content);

bool processUploadId(const CopyrightState& state, int agentId, int uploadId, CopyrightDatabaseHandler& handler, bool ignoreFilesWithMimeType);

std::pair<icu::UnicodeString, std::list<match>> processSingleFile(const CopyrightState& state,
  const std::string fileName);

void appendToJson(const std::string& fileName,
    const std::pair<icu::UnicodeString, list<match>>& resultPair, bool &printComma);

void printResultToStdout(const std::string& fileName,
    const std::pair<icu::UnicodeString, list<match>>& resultPair);

#endif /* COPYRIGHTUTILS_HPP_ */

