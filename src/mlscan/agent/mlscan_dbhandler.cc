/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file mlscan_dbhandler.cc
 * @brief Database handler implementation for ML scan results
 */

#include "mlscan_dbhandler.hpp"
#include <iostream>
#include <sstream>

/**
 * @brief Constructor
 */
MLScanDatabaseHandler::MLScanDatabaseHandler(PGconn* connection, int agent_id) 
    : dbConn(connection), agentId(agent_id) {
}

/**
 * @brief Create necessary database tables
 */
bool MLScanDatabaseHandler::createTables() {
    const char* createTableSQL = 
        "CREATE TABLE IF NOT EXISTS mlscan_license ("
        "  ml_pk SERIAL PRIMARY KEY,"
        "  pfile_fk INTEGER NOT NULL REFERENCES pfile(pfile_pk) ON DELETE CASCADE,"
        "  rf_fk INTEGER REFERENCES license_ref(rf_pk) ON DELETE CASCADE,"
        "  agent_fk INTEGER NOT NULL REFERENCES agent(agent_pk) ON DELETE CASCADE,"
        "  confidence REAL NOT NULL,"
        "  detection_method VARCHAR(50),"
        "  UNIQUE(pfile_fk, rf_fk, agent_fk)"
        ");"
        "CREATE INDEX IF NOT EXISTS mlscan_license_pfile_idx ON mlscan_license(pfile_fk);"
        "CREATE INDEX IF NOT EXISTS mlscan_license_agent_idx ON mlscan_license(agent_fk);";
    
    PGresult* result = PQexec(dbConn, createTableSQL);
    
    if (PQresultStatus(result) != PGRES_COMMAND_OK) {
        cerr << "Failed to create tables: " << PQerrorMessage(dbConn) << endl;
        PQclear(result);
        return false;
    }
    
    PQclear(result);
    return true;
}

/**
 * @brief Get license reference ID by SPDX identifier
 */
int MLScanDatabaseHandler::getLicenseRefId(const string& licenseName) {
    stringstream query;
    query << "SELECT rf_pk FROM license_ref WHERE rf_shortname = '" 
          << licenseName << "' LIMIT 1;";
    
    PGresult* result = PQexec(dbConn, query.str().c_str());
    
    if (PQresultStatus(result) != PGRES_TUPLES_OK || PQntuples(result) == 0) {
        PQclear(result);
        return -1;
    }
    
    int rfId = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
    return rfId;
}

/**
 * @brief Check if file has already been scanned
 */
bool MLScanDatabaseHandler::isAlreadyScanned(int pfileId) {
    stringstream query;
    query << "SELECT COUNT(*) FROM mlscan_license WHERE pfile_fk = " 
          << pfileId << " AND agent_fk = " << agentId << ";";
    
    PGresult* result = PQexec(dbConn, query.str().c_str());
    
    if (PQresultStatus(result) != PGRES_TUPLES_OK) {
        PQclear(result);
        return false;
    }
    
    int count = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
    
    return count > 0;
}

/**
 * @brief Store ML scan result in database
 */
bool MLScanDatabaseHandler::storeScanResult(int pfileId, const ScanResult& result) {
    // Check if already scanned
    if (isAlreadyScanned(pfileId)) {
        return true;
    }
    
    // Store each detected license
    for (const auto& license : result.licenses) {
        // Get license reference ID
        int rfId = getLicenseRefId(license.license_name);
        
        if (rfId == -1) {
            cerr << "Warning: License not found in database: " 
                 << license.license_name << endl;
            continue;
        }
        
        // Insert into mlscan_license table
        stringstream insertQuery;
        insertQuery << "INSERT INTO mlscan_license "
                   << "(pfile_fk, rf_fk, agent_fk, confidence, detection_method) "
                   << "VALUES (" << pfileId << ", " << rfId << ", " << agentId 
                   << ", " << license.confidence << ", '" << license.method << "') "
                   << "ON CONFLICT (pfile_fk, rf_fk, agent_fk) DO NOTHING;";
        
        PGresult* insertResult = PQexec(dbConn, insertQuery.str().c_str());
        
        if (PQresultStatus(insertResult) != PGRES_COMMAND_OK) {
            cerr << "Failed to insert license: " << PQerrorMessage(dbConn) << endl;
            PQclear(insertResult);
            return false;
        }
        
        PQclear(insertResult);
    }
    
    return true;
}
