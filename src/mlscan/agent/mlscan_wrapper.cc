/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file mlscan_wrapper.cc
 * @brief Python wrapper implementation for ML scanner
 */

#include "mlscan_wrapper.hpp"
#include <iostream>
#include <fstream>
#include <sstream>
#include <cstdlib>
#include <unistd.h>

/**
 * @brief Execute a shell command and capture output
 */
string exec(const char* cmd) {
    array<char, 128> buffer;
    string result;
    unique_ptr<FILE, decltype(&pclose)> pipe(popen(cmd, "r"), pclose);
    
    if (!pipe) {
        throw runtime_error("popen() failed!");
    }
    
    while (fgets(buffer.data(), buffer.size(), pipe.get()) != nullptr) {
        result += buffer.data();
    }
    
    return result;
}

/**
 * @brief Get path to Python ML scanner script
 */
string getMLScannerPath() {
    // Get the directory where mlscan binary is located
    char result[PATH_MAX];
    ssize_t count = readlink("/proc/self/exe", result, PATH_MAX);
    string exePath;
    
    if (count != -1) {
        result[count] = '\0';
        exePath = string(result);
    } else {
        // Fallback: assume we're in the agent directory
        exePath = "./mlscan";
    }
    
    // Get directory path
    size_t pos = exePath.find_last_of("/");
    string dirPath = exePath.substr(0, pos);
    
    // Construct path to Python script
    return dirPath + "/ml/mlscan_runner.py";
}

/**
 * @brief Run Python ML scanner and get results
 */
bool runPythonScanner(const string& filePath, const string& outputPath) {
    string scriptPath = getMLScannerPath();
    
    // Build command
    stringstream cmd;
    cmd << "python3 " << scriptPath << " \"" << filePath << "\" \"" << outputPath << "\"";
    
    // Execute command
    int result = system(cmd.str().c_str());
    
    return (result == 0);
}

/**
 * @brief Read JSON file and return contents
 */
string readJsonFile(const string& jsonPath) {
    ifstream file(jsonPath);
    if (!file.is_open()) {
        throw runtime_error("Failed to open JSON file: " + jsonPath);
    }
    
    stringstream buffer;
    buffer << file.rdbuf();
    return buffer.str();
}
