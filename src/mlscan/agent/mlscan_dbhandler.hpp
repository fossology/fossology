/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file mlscan_dbhandler.hpp
 * @brief Database handler for ML scan results
 */

#ifndef MLSCAN_DBHANDLER_HPP
#define MLSCAN_DBHANDLER_HPP

#include "mlscan.hpp"
#include <libfossdb.h>
#include <string>
#include <vector>

using namespace std;

/**
 * @class MLScanDatabaseHandler
 * @brief Handles database operations for ML scan results
 */
class MLScanDatabaseHandler {
private:
    PGconn* dbConn;
    int agentId;

public:
    /**
     * @brief Constructor
     * @param connection PostgreSQL connection
     * @param agent_id Agent ID from agent table
     */
    MLScanDatabaseHandler(PGconn* connection, int agent_id);
    
    /**
     * @brief Create necessary database tables
     * @return true if successful
     */
    bool createTables();
    
    /**
     * @brief Store ML scan result in database
     * @param pfileId File ID from pfile table
     * @param result Scan result to store
     * @return true if successful
     */
    bool storeScanResult(int pfileId, const ScanResult& result);
    
    /**
     * @brief Get license reference ID by SPDX identifier
     * @param licenseName SPDX license identifier
     * @return License reference ID, or -1 if not found
     */
    int getLicenseRefId(const string& licenseName);
    
    /**
     * @brief Check if file has already been scanned
     * @param pfileId File ID to check
     * @return true if already scanned
     */
    bool isAlreadyScanned(int pfileId);
};

#endif /* MLSCAN_DBHANDLER_HPP */
