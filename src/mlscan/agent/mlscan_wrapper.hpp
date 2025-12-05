/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file mlscan_wrapper.hpp
 * @brief Python wrapper for ML scanner
 */

#ifndef MLSCAN_WRAPPER_HPP
#define MLSCAN_WRAPPER_HPP

#include "mlscan.hpp"
#include <string>
#include <cstdio>
#include <memory>
#include <stdexcept>
#include <array>

using namespace std;

/**
 * @brief Execute a shell command and capture output
 * @param cmd Command to execute
 * @return Command output as string
 */
string exec(const char* cmd);

/**
 * @brief Run Python ML scanner and get results
 * @param filePath Path to file to scan
 * @param outputPath Path to write JSON output
 * @return true if successful, false otherwise
 */
bool runPythonScanner(const string& filePath, const string& outputPath);

/**
 * @brief Read JSON file and return contents
 * @param jsonPath Path to JSON file
 * @return JSON content as string
 */
string readJsonFile(const string& jsonPath);

#endif /* MLSCAN_WRAPPER_HPP */
