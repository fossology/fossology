/*
 * SPDX-FileCopyrightText: © Fossology contributors
 * SPDX-License-Identifier: GPL-2.0-only
 */

#include "ossdetect_dbhandler.hpp"
#include <sstream>
#include <iostream>

namespace ossdetect {

OssDetectDatabaseHandler::OssDetectDatabaseHandler(fo::DbManager dbManager) 
    : fo::AgentDatabaseHandler(dbManager) {
}

bool OssDetectDatabaseHandler::createTables() const {
    return createDependenciesTable() && 
           createMatchesTable() && 
           createAgentResultsTable();
}

bool OssDetectDatabaseHandler::createDependenciesTable() const {
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
    
    if (!getDbManager().queryPrintf(createQuery)) {
        return false;
    }
    
    const char* indexQuery = 
        "CREATE INDEX IF NOT EXISTS ossdetect_dependency_pfile_idx "
        "ON ossdetect_dependency(pfile_fk)";
    getDbManager().queryPrintf(indexQuery);
    
    return true;
}

bool OssDetectDatabaseHandler::createMatchesTable() const {
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
    
    if (!getDbManager().queryPrintf(createQuery)) {
        return false;
    }
    
    const char* indexQuery = 
        "CREATE INDEX IF NOT EXISTS ossdetect_match_pfile_idx "
        "ON ossdetect_match(pfile_fk)";
    getDbManager().queryPrintf(indexQuery);
    
    return true;
}

bool OssDetectDatabaseHandler::createAgentResultsTable() const {
    const char* createQuery = 
        "CREATE TABLE IF NOT EXISTS ossdetect_ars ("
        "ars_pk SERIAL PRIMARY KEY,"
        "agent_fk INTEGER NOT NULL,"
        "upload_fk INTEGER NOT NULL,"
        "ars_success BOOLEAN NOT NULL DEFAULT FALSE,"
        "ars_starttime TIMESTAMP DEFAULT NOW(),"
        "ars_endtime TIMESTAMP"
        ")";
    
    return getDbManager().queryPrintf(createQuery);
}

bool OssDetectDatabaseHandler::storeDependency(long uploadId, long pfileId, 
                                                const Dependency& dependency) const {
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
    
    return getDbManager().queryPrintf(query);
}

bool OssDetectDatabaseHandler::storeSimilarityMatch(long uploadId, long pfileId,
                                                     const std::string& dependencyName,
                                                     const SimilarityMatch& match) const {
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
    
    return getDbManager().queryPrintf(query);
}

} // namespace ossdetect
