/*
 * SPDX-FileCopyrightText: © Fossology contributors
 * SPDX-License-Identifier: GPL-2.0-only
 */

/**
 * @file ossdetect.cc
 * @brief Main agent for automatic OSS component detection
 * 
 * This agent analyzes package metadata files (pom.xml, package.json, etc.)
 * to extract dependency information and match against known OSS components.
 */

#include "ossdetect_dbhandler.hpp"
#include <json/json.h>
#include <fstream>
#include <sstream>
#include <cstdlib>
#include <unistd.h>
#include <sys/wait.h>

extern "C" {
#include "libfossagent.h"
}

using namespace ossdetect;

/**
 * Check if a file is a supported metadata file based on its name
 */
bool isMetadataFile(const std::string& filename) {
    const std::string supportedFiles[] = {
        "pom.xml",
        "package.json",
        "requirements.txt",
        "go.mod",
        "Gemfile",
        "Cargo.toml"
    };
    
    for (const auto& supported : supportedFiles) {
        if (filename == supported) {
            return true;
        }
    }
    
    return false;
}

/**
 * Execute Python parser script and capture output
 */
std::string executePythonParser(const std::string& scriptPath, const std::string& filePath) {
    // Create temporary file for output
    char tempPath[] = "/tmp/ossdetect_XXXXXX";
    int tempFd = mkstemp(tempPath);
    if (tempFd == -1) {
        std::cerr << "Failed to create temporary file" << std::endl;
        return "";
    }
    close(tempFd);
    
    // Build command
    std::ostringstream cmd;
    cmd << "python3 " << scriptPath << " \"" << filePath << "\" -o " << tempPath;
    
    // Execute command
    int result = system(cmd.str().c_str());
    
    if (result != 0) {
        std::cerr << "Python parser failed with code " << result << std::endl;
        unlink(tempPath);
        return "";
    }
    
    // Read output
    std::ifstream outputFile(tempPath);
    std::ostringstream output;
    output << outputFile.rdbuf();
    outputFile.close();
    
    // Clean up
    unlink(tempPath);
    
    return output.str();
}

/**
 * Parse JSON output from Python parser
 */
bool parseParserOutput(const std::string& jsonOutput, 
                       std::vector<Dependency>& dependencies) {
    Json::Value root;
    Json::Reader reader;
    
    if (!reader.parse(jsonOutput, root)) {
        std::cerr << "Failed to parse JSON output: " 
                  << reader.getFormattedErrorMessages() << std::endl;
        return false;
    }
    
    // Check for parsing errors
    if (root.isMember("error") && !root["error"].isNull()) {
        std::cerr << "Parser error: " << root["error"].asString() << std::endl;
        return false;
    }
    
    // Extract dependencies
    if (root.isMember("dependencies") && root["dependencies"].isArray()) {
        const Json::Value& depsArray = root["dependencies"];
        
        for (const auto& depJson : depsArray) {
            std::string name = depJson.get("name", "").asString();
            std::string version = depJson.get("version", "unspecified").asString();
            std::string scope = depJson.get("scope", "runtime").asString();
            int line = depJson.get("line", 0).asInt();
            
            if (!name.empty()) {
                dependencies.emplace_back(name, version, scope, line);
            }
        }
    }
    
    return true;
}

/**
 * Process a single metadata file
 */
bool processMetadataFile(const std::string& filePath, long uploadId, long pfileId,
                        const std::string& parserScript, OssDetectDatabaseHandler& dbHandler) {
    
    std::cout << "Processing metadata file: " << filePath << std::endl;
    
    // Check if already analyzed
    if (dbHandler.isFileAnalyzed(0, pfileId)) {
        std::cout << "File already analyzed, skipping" << std::endl;
        return true;
    }
    
    // Execute parser
    std::string jsonOutput = executePythonParser(parserScript, filePath);
    
    if (jsonOutput.empty()) {
        std::cerr << "No output from parser" << std::endl;
        return false;
    }
    
    // Parse output
    std::vector<Dependency> dependencies;
    if (!parseParserOutput(jsonOutput, dependencies)) {
        return false;
    }
    
    std::cout << "Found " << dependencies.size() << " dependencies" << std::endl;
    
    // Store dependencies in database
    for (const auto& dep : dependencies) {
        if (!dbHandler.storeDependency(uploadId, pfileId, dep)) {
            std::cerr << "Failed to store dependency: " << dep.name << std::endl;
            // Continue processing other dependencies
        }
    }
    
    // Mark as analyzed
    dbHandler.markFileAnalyzed(0, pfileId);
    
    return true;
}

/**
 * Main entry point
 */
int main(int argc, char** argv) {
    // Initialize database manager
    fo::DbManager dbManager(&argc, argv);
    
    // Create database handler
    OssDetectDatabaseHandler databaseHandler(dbManager);
    
    // Create tables if needed
    if (!databaseHandler.createTables()) {
        std::cerr << "Failed to create database tables" << std::endl;
        fo_scheduler_disconnect(1);
        return 1;
    }
    
    // Determine path to parser script
    // In production, this would be installed in a known location
    std::string scriptDir = std::string(argv[0]);
    size_t lastSlash = scriptDir.find_last_of('/');
    if (lastSlash != std::string::npos) {
        scriptDir = scriptDir.substr(0, lastSlash);
    } else {
        scriptDir = ".";
    }
    
    std::string parserScript = scriptDir + "/metadata_parser.py";
    
    // Get agent ID
    int agentId = fo_scheduler_agentId();
    
    // Process each upload from the scheduler
    while (fo_scheduler_next() != NULL) {
        int uploadId = atoi(fo_scheduler_current());
        
        if (uploadId <= 0) {
            continue;
        }
        
        // Process all files in the upload
        std::vector<unsigned long> fileIds = databaseHandler.queryFileIdsForUpload(uploadId, false);
        
        for (unsigned long pfileId : fileIds) {
            // Get file info from database
            char* uploadTreeTableName = nullptr;
            long uploadTreeId = fo_GetOneUploadTree(uploadId, pfileId, &uploadTreeTableName);
            
            if (uploadTreeId <= 0) {
                continue;
            }
            
            // Get file path
            char* filePath = fo_RepMkPath("files", fo_GetPfileName(pfileId));
            if (!filePath) {
                continue;
            }
            
            // Check if file has analysis data (process all metadata files)
            std::string filePathStr(filePath);
            size_t lastSlash = filePathStr.find_last_of('/');
            std::string filename = (lastSlash != std::string::npos) 
                                  ? filePathStr.substr(lastSlash + 1) 
                                  : filePathStr;
            
            if (isMetadataFile(filename)) {
                processMetadataFile(filePath, uploadId, pfileId, parserScript, databaseHandler);
            }
            
            free(filePath);
            fo_scheduler_heart(1);
        }
    }
    
    fo_scheduler_heart(0);
    fo_scheduler_disconnect(0);
    return 0;
}
