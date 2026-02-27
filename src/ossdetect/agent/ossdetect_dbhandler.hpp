/*
 * SPDX-FileCopyrightText: © Fossology contributors
 * SPDX-License-Identifier: GPL-2.0-only
 */

#ifndef OSSDETECT_DBHANDLER_HPP
#define OSSDETECT_DBHANDLER_HPP

#include <string>
#include <vector>
#include <memory>

#include "libfossAgentDatabaseHandler.hpp"
#include "libfossUtils.hpp"
#include "libfossdbmanagerclass.hpp"

extern "C" {
#include "libfossology.h"
}

using namespace std;
using namespace fo;

namespace ossdetect {

/**
 * Structure to hold dependency information extracted from metadata files
 */
struct Dependency {
    std::string name;
    std::string version;
    std::string scope;
    int sourceLine;
    
    Dependency(const std::string& n, const std::string& v, 
               const std::string& s, int line)
        : name(n), version(v), scope(s), sourceLine(line) {}
};

/**
 * Structure to hold similarity match results
 */
struct SimilarityMatch {
    std::string componentName;
    std::string componentVersion;
    float score;
    std::string matchType;
    
    SimilarityMatch(const std::string& name, const std::string& version,
                   float s, const std::string& type)
        : componentName(name), componentVersion(version), score(s), matchType(type) {}
};

/**
 * Database handler for OSS detection agent
 */
class OssDetectDatabaseHandler : public fo::AgentDatabaseHandler {
public:
    OssDetectDatabaseHandler(fo::DbManager dbManager);
    OssDetectDatabaseHandler(OssDetectDatabaseHandler&& other) 
        : fo::AgentDatabaseHandler(std::move(other)) {}
    
    bool createTables() const;
    bool storeDependency(long uploadId, long pfileId, const Dependency& dependency) const;
    bool storeSimilarityMatch(long uploadId, long pfileId, 
                              const std::string& dependencyName,
                              const SimilarityMatch& match) const;
    
private:
    bool createDependenciesTable() const;
    bool createMatchesTable() const;
    bool createAgentResultsTable() const;
};

} // namespace ossdetect

#endif // OSSDETECT_DBHANDLER_HPP
