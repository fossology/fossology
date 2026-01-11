/*
 * SPDX-FileCopyrightText: © Fossology contributors
 * SPDX-License-Identifier: GPL-2.0-only
 */

#include "ossdetect_dbhandler.hpp"
#include <sstream>
#include <iostream>

namespace ossdetect {

OssDetectDatabaseHandler::OssDetectDatabaseHandler(DbManager& dbMgr)
    : dbManager(dbMgr) {
}

OssDetectDatabaseHandler::~OssDetectDatabaseHandler() {
}

bool OssDetectDatabaseHandler::createTables() {
    // Create all required tables
    if (!createDependenciesTable()) {
        std::cerr << "Failed to create dependencies table" << std::endl;
        return false;
    }
    
    if (!createMatchesTable()) {
        std::cerr << "Failed to create matches table" << std::endl;
        return false;
    }
    
    if (!createAgentResultsTable()) {
        std::cerr << "Failed to create agent results table" << std::endl;
        return false;
    }
    
    return true;
}

bool OssDetectDatabaseHandler::createDependenciesTable() {
    // Simple table creation - in production this would check if exists first
    const char* createQuery = 
        "CREATE TABLE IF NOT EXISTS ossdetect_dependency ("
        "od_pk SERIAL PRIMARY KEY,"
        "upload_fk INTEGER NOT NULL,"
        "pfile_fk INTEGER NOT NULL,"
        "dependency_name TEXT NOT NULL,"
        "dependency_version TEXT,"
        "dependency_scope TEXT,"
        "source_line INTEGER"
        ")";
    
    if (!dbManager.queryPrintf(createQuery)) {
        return false;
    }
    
    // Create index for faster lookups
    const char* indexQuery = 
        "CREATE INDEX IF NOT EXISTS ossdetect_dependency_pfile_idx "
        "ON ossdetect_dependency(pfile_fk)";
    dbManager.queryPrintf(indexQuery);
    
    return true;
}

bool OssDetectDatabaseHandler::createMatchesTable() {
    const char* createQuery = 
        "CREATE TABLE IF NOT EXISTS ossdetect_match ("
        "om_pk SERIAL PRIMARY KEY,"
        "upload_fk INTEGER NOT NULL,"
        "pfile_fk INTEGER NOT NULL,"
        "dependency_name TEXT NOT NULL,"
        "component_name TEXT NOT NULL,"
        "component_version TEXT,"
        "similarity_score REAL NOT NULL,"
        "match_type TEXT"
        ")";
    
    if (!dbManager.queryPrintf(createQuery)) {
        return false;
    }
    
    const char* indexQuery = 
        "CREATE INDEX IF NOT EXISTS ossdetect_match_pfile_idx "
        "ON ossdetect_match(pfile_fk)";
    dbManager.queryPrintf(indexQuery);
    
    return true;
}

bool OssDetectDatabaseHandler::createAgentResultsTable() {
    const char* createQuery = 
        "CREATE TABLE IF NOT EXISTS ossdetect_ars ("
        "ars_pk SERIAL PRIMARY KEY,"
        "agent_fk INTEGER NOT NULL,"
        "upload_fk INTEGER NOT NULL,"
        "ars_success BOOLEAN NOT NULL DEFAULT FALSE,"
        "ars_starttime TIMESTAMP DEFAULT NOW(),"
        "ars_endtime TIMESTAMP"
        ")";
    
    return dbManager.queryPrintf(createQuery);
}

bool OssDetectDatabaseHandler::storeDependency(long uploadId, long pfileId, 
                                               const Dependency& dependency) {
    // Use direct query for simplicity - in production would use prepared statements
    char query[4096];
    snprintf(query, sizeof(query),
        "INSERT INTO ossdetect_dependency "
        "(upload_fk, pfile_fk, dependency_name, dependency_version, dependency_scope, source_line) "
        "VALUES (%ld, %ld, '%s', '%s', '%s', %d)",
        uploadId, pfileId, 
        dependency.name.c_str(), 
        dependency.version.c_str(),
        dependency.scope.c_str(), 
        dependency.sourceLine);
    
    return dbManager.queryPrintf(query);
}

bool OssDetectDatabaseHandler::storeSimilarityMatch(long uploadId, long pfileId,
                                                    const std::string& dependencyName,
                                                    const SimilarityMatch& match) {
    char query[4096];
    snprintf(query, sizeof(query),
        "INSERT INTO ossdetect_match "
        "(upload_fk, pfile_fk, dependency_name, component_name, component_version, similarity_score, match_type) "
        "VALUES (%ld, %ld, '%s', '%s', '%s', %.2f, '%s')",
        uploadId, pfileId,
        dependencyName.c_str(),
        match.componentName.c_str(),
        match.componentVersion.c_str(),
        match.score,
        match.matchType.c_str());
    
    return dbManager.queryPrintf(query);
}

bool OssDetectDatabaseHandler::isFileAnalyzed(long agentId, long pfileId) {
    // Check if dependencies already exist for this file
    char query[512];
    snprintf(query, sizeof(query),
        "SELECT COUNT(*) FROM ossdetect_dependency WHERE pfile_fk = %ld",
        pfileId);
    
    // For now, just return false to allow re-analysis
    // In production, this would properly check the database
    return false;
}

bool OssDetectDatabaseHandler::markFileAnalyzed(long agentId, long pfileId) {
    // In a full implementation, this would update the ars table
    // For now, we'll just return true as the existence of dependencies
    // in the DB serves as our marker
    return true;
}

} // namespace ossdetect
