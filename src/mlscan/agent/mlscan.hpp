/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file mlscan.hpp
 * @brief ML License Scanner agent header
 */

#ifndef MLSCAN_HPP
#define MLSCAN_HPP

#include <string>
#include <vector>
#include <libfossology.h>

extern "C" {
#include <libfossdb.h>
}

using namespace std;

/**
 * @struct License
 * @brief Structure to hold license detection result
 */
struct License {
    string license_name;
    double confidence;
    string method;
};

/**
 * @struct ScanResult
 * @brief Structure to hold complete scan result for a file
 */
struct ScanResult {
    string file_path;
    vector<License> licenses;
    bool conflict_detected;
    string message;
};

/**
 * @brief Run Python ML scanner on a file
 * @param filePath Path to file to scan
 * @return ScanResult containing detected licenses
 */
ScanResult runMLScan(const string& filePath);

/**
 * @brief Parse JSON output from Python scanner
 * @param jsonOutput JSON string from Python script
 * @return ScanResult parsed from JSON
 */
ScanResult parseMLOutput(const string& jsonOutput);

/**
 * @brief Get path to Python ML scanner script
 * @return Path to mlscan_runner.py
 */
string getMLScannerPath();

#endif /* MLSCAN_HPP */
