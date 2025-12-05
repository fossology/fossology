/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file mlscan.cc
 * @brief Main entry point for ML License Scanner agent
 * 
 * Integrates with FOSSology scheduler to process uploads and scan files
 * for license detection using machine learning models.
 */

#include "mlscan.hpp"
#include "mlscan_wrapper.hpp"
#include "mlscan_dbhandler.hpp"
#include "mlscan_state.hpp"
#include <iostream>
#include <fstream>
#include <cstdlib>
#include <ctime>
#include <json/json.h>

extern "C" {
#include <libfossology.h>
#include <libfossdb.h>
}

/**
 * @def return_sched(retval)
 * Send disconnect to scheduler with retval and return
 */
#define return_sched(retval) \
  do {\
    fo_scheduler_disconnect((retval));\
    return (retval);\
  } while(0)

/**
 * @brief Parse JSON output from Python scanner
 */
ScanResult parseMLOutput(const string& jsonOutput) {
    ScanResult result;
    Json::Value root;
    Json::Reader reader;
    
    if (!reader.parse(jsonOutput, root)) {
        cerr << "Failed to parse JSON output" << endl;
        return result;
    }
    
    result.file_path = root["file"].asString();
    result.conflict_detected = root["conflict_detected"].asBool();
    result.message = root["message"].asString();
    
    const Json::Value licenses = root["licenses"];
    for (unsigned int i = 0; i < licenses.size(); i++) {
        License lic;
        lic.license_name = licenses[i]["license_name"].asString();
        lic.confidence = licenses[i]["confidence"].asDouble();
        lic.method = licenses[i]["method"].asString();
        result.licenses.push_back(lic);
    }
    
    return result;
}

/**
 * @brief Run ML scan on a file
 */
ScanResult runMLScan(const string& filePath) {
    // Create temporary file for output
    char tmpfile[] = "/tmp/mlscan_XXXXXX";
    int fd = mkstemp(tmpfile);
    if (fd == -1) {
        cerr << "Failed to create temporary file" << endl;
        return ScanResult();
    }
    close(fd);
    
    string outputPath = string(tmpfile);
    
    // Run Python scanner
    bool success = runPythonScanner(filePath, outputPath);
    
    if (!success) {
        cerr << "Python scanner failed for: " << filePath << endl;
        unlink(tmpfile);
        return ScanResult();
    }
    
    // Read and parse JSON output
    string jsonOutput = readJsonFile(outputPath);
    ScanResult result = parseMLOutput(jsonOutput);
    
    // Clean up
    unlink(tmpfile);
    
    return result;
}

/**
 * @brief Process a single upload
 */
bool processUpload(int uploadId, MLScanState& state, MLScanDatabaseHandler& dbHandler, PGconn* dbConn) {
    // Get list of files in upload
    stringstream query;
    query << "SELECT pfile_pk, pfile_sha1 FROM uploadtree "
          << "INNER JOIN pfile ON uploadtree.pfile_fk = pfile.pfile_pk "
          << "WHERE upload_fk = " << uploadId << ";";
    
    PGresult* result = PQexec(dbConn, query.str().c_str());
    
    if (PQresultStatus(result) != PGRES_TUPLES_OK) {
        cerr << "Failed to get files for upload: " << PQerrorMessage(dbConn) << endl;
        PQclear(result);
        return false;
    }
    
    int numFiles = PQntuples(result);
    cout << "Processing " << numFiles << " files in upload " << uploadId << endl;
    
    // Process each file
    for (int i = 0; i < numFiles; i++) {
        int pfileId = atoi(PQgetvalue(result, i, 0));
        string pfileSha1 = PQgetvalue(result, i, 1);
        
        // Check if already scanned
        if (dbHandler.isAlreadyScanned(pfileId)) {
            continue;
        }
        
        // Get file path from repository
        char* repPath = fo_RepMkPath("files", PQgetvalue(result, i, 1));
        if (!repPath) {
            cerr << "Failed to get repository path for pfile " << pfileId << endl;
            continue;
        }
        
        // Run ML scan
        ScanResult scanResult = runMLScan(string(repPath));
        free(repPath);
        
        // Store results in database
        if (!dbHandler.storeScanResult(pfileId, scanResult)) {
            cerr << "Failed to store scan result for pfile " << pfileId << endl;
            PQclear(result);
            return false;
        }
        
        // Send heartbeat every 10 files
        if (i % 10 == 0) {
            fo_scheduler_heart(1);
        }
    }
    
    PQclear(result);
    return true;
}

/**
 * @brief Write ARS (Agent Run Status) record
 */
int writeARS(MLScanState& state, int arsId, int uploadId, int success, PGconn* dbConn) {
    if (arsId == 0) {
        // Create new ARS record
        stringstream insertQuery;
        insertQuery << "INSERT INTO mlscan_ars (upload_fk, agent_fk, ars_success) "
                   << "VALUES (" << uploadId << ", " << state.getAgentId() << ", FALSE) "
                   << "RETURNING ars_pk;";
        
        PGresult* result = PQexec(dbConn, insertQuery.str().c_str());
        
        if (PQresultStatus(result) != PGRES_TUPLES_OK || PQntuples(result) == 0) {
            cerr << "Failed to create ARS record: " << PQerrorMessage(dbConn) << endl;
            PQclear(result);
            return -1;
        }
        
        int newArsId = atoi(PQgetvalue(result, 0, 0));
        PQclear(result);
        return newArsId;
    } else {
        // Update existing ARS record
        stringstream updateQuery;
        updateQuery << "UPDATE mlscan_ars SET ars_success = " << (success ? "TRUE" : "FALSE")
                   << ", ars_endtime = NOW() WHERE ars_pk = " << arsId << ";";
        
        PGresult* result = PQexec(dbConn, updateQuery.str().c_str());
        
        if (PQresultStatus(result) != PGRES_COMMAND_OK) {
            cerr << "Failed to update ARS record: " << PQerrorMessage(dbConn) << endl;
            PQclear(result);
            return -1;
        }
        
        PQclear(result);
        return arsId;
    }
}

/**
 * @brief Main function
 */
int main(int argc, char** argv) {
    PGconn* dbConn = NULL;
    
    // Connect to scheduler and database
    fo_scheduler_connect(&argc, argv, &dbConn);
    
    if (!dbConn) {
        LOG_FATAL("Failed to connect to database");
        return_sched(1);
    }
    
    // Get agent ID
    int agentId = fo_GetAgentKey(dbConn, "mlscan", 0, "1.0.0", "ML License Scanner");
    if (agentId <= 0) {
        LOG_FATAL("Failed to get agent ID");
        return_sched(1);
    }
    
    // Initialize state and database handler
    MLScanState state(agentId);
    MLScanDatabaseHandler dbHandler(dbConn, agentId);
    
    // Create database tables
    if (!dbHandler.createTables()) {
        LOG_FATAL("Failed to create database tables");
        return_sched(9);
    }
    
    cout << "ML License Scanner starting (agent ID: " << agentId << ")" << endl;
    
    // Main scheduler loop
    while (fo_scheduler_next() != NULL) {
        int uploadId = atoi(fo_scheduler_current());
        
        if (uploadId == 0) {
            continue;
        }
        
        cout << "Processing upload: " << uploadId << endl;
        
        // Create ARS record
        int arsId = writeARS(state, 0, uploadId, 0, dbConn);
        if (arsId <= 0) {
            LOG_ERROR("Failed to create ARS record for upload %d", uploadId);
            continue;
        }
        
        // Process upload
        bool success = processUpload(uploadId, state, dbHandler, dbConn);
        
        // Update ARS record
        writeARS(state, arsId, uploadId, success ? 1 : 0, dbConn);
        
        // Send heartbeat
        fo_scheduler_heart(0);
        
        if (success) {
            cout << "Successfully processed upload: " << uploadId << endl;
        } else {
            LOG_ERROR("Failed to process upload %d", uploadId);
        }
    }
    
    // Final heartbeat
    fo_scheduler_heart(0);
    
    // Disconnect from scheduler
    fo_scheduler_disconnect(0);
    
    cout << "ML License Scanner completed" << endl;
    
    return 0;
}
