/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini, Johannes Najjar
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef COPYRIGHTUTILS_HPP_
#define COPYRIGHTUTILS_HPP_

#include "identity.hpp"

#define AGENT_NAME IDENTITY ///< the name of the agent, used to get agent key
#define AGENT_DESC IDENTITY " agent" ///< what program this is
#define AGENT_ARS  IDENTITY "_ars"

// exclude unsupported compilers from json output
#if defined(__clang__)
  #if (__clang_major__ * 10000 + __clang_minor__ * 100 + __clang_patchlevel__) < 30400
    #define DISABLE_JSON
  #endif
#elif defined(__GNUC__)
  #if (__GNUC__ * 10000 + __GNUC_MINOR__ * 100 + __GNUC_PATCHLEVEL__) < 40900
    #define DISABLE_JSON
  #endif
#endif

#include <string>
#include <vector>
#include <list>

#include "scanners.hpp"
#include "regscan.hpp"
#include "copyscan.hpp"

//#include "regexMatcher.hpp"
#include "copyrightState.hpp"
//#include "files.hpp"
#include "database.hpp"
#include "cleanEntries.hpp"

extern "C" {
#include "libfossology.h"
}

int queryAgentId(PGconn* dbConn);

void bail(int exitval);

int writeARS(int agentId, int arsId, int uploadId, int success, const fo::DbManager& dbManager);

bool parseCliOptions(int argc, char** argv, CliOptions& dest, std::vector<std::string>& fileNames);

CopyrightState getState(CliOptions&& cliOptions);

scanner* makeRegexScanner(const std::string& regexDesc, const std::string& defaultType);
/*
std::vector<CopyrightMatch> matchStringToRegexes(const std::string& content, std::vector<RegexMatcher> matchers);
*/
void normalizeContent(std::string& content);

bool processUploadId(const CopyrightState& state, int agentId, int uploadId, CopyrightDatabaseHandler& handler);


#endif /* COPYRIGHTUTILS_HPP_ */

