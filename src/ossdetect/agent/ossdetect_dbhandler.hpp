/*
 * SPDX-FileCopyrightText: © Fossology contributors
 * SPDX-License-Identifier: GPL-2.0-only
 */

#ifndef OSSDETECT_DBHANDLER_HPP
#define OSSDETECT_DBHANDLER_HPP

#include <string>
#include <vector>
#include <memory>
#include "libfossology.h"

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
 * 
 * Manages database operations including creating tables, storing dependencies,
 * and recording similarity matches.
 */
class OssDetectDatabaseHandler {
public:
    OssDetectDatabaseHandler(DbManager& dbManager);
    ~OssDetectDatabaseHandler();
    
    /**
     * Create necessary database tables if they don't exist
     */
    bool createTables();
    
    /**
     * Store a detected dependency in the database
     * 
     * @param uploadId ID of the upload
     * @param pfileId ID of the metadata file
     * @param dependency Dependency information to store
     * @return true on success
     */
    bool storeDependency(long uploadId, long pfileId, const Dependency& dependency);
    
    /**
     * Store a similarity match result
     * 
     * @param uploadId ID of the upload
     * @param pfileId ID of the metadata file
     * @param dependencyName Name of the dependency
     * @param match Match information to store
     * @return true on success
     */
    bool storeSimilarityMatch(long uploadId, long pfileId, 
                             const std::string& dependencyName,
                             const SimilarityMatch& match);
    
    /**
     * Check if a file has already been analyzed
     * 
     * @param agentId ID of the agent
     * @param pfileId ID of the file
     * @return true if already analyzed
     */
    bool isFileAnalyzed(long agentId, long pfileId);
    
    /**
     * Mark file as analyzed
     * 
     * @param agentId ID of the agent
     * @param pfileId ID of the file
     * @return true on success
     */
    bool markFileAnalyzed(long agentId, long pfileId);
    
private:
    DbManager& dbManager;
    
    /**
     * Create the dependencies table
     */
    bool createDependenciesTable();
    
    /**
     * Create the similarity matches table
     */
    bool createMatchesTable();
    
    /**
     * Create the agent results table
     */
    bool createAgentResultsTable();
};

} // namespace ossdetect

#endif // OSSDETECT_DBHANDLER_HPP
