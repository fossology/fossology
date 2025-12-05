/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file mlscan_state.hpp
 * @brief State management for ML scan agent
 */

#ifndef MLSCAN_STATE_HPP
#define MLSCAN_STATE_HPP

#include <string>

using namespace std;

/**
 * @class MLScanState
 * @brief Manages agent state including agent ID and configuration
 */
class MLScanState {
private:
    int agentId;
    string cliOptions;

public:
    /**
     * @brief Constructor
     * @param agent_id Agent ID from agent table
     */
    MLScanState(int agent_id);
    
    /**
     * @brief Set command line options
     * @param options CLI options string
     */
    void setCliOptions(const string& options);
    
    /**
     * @brief Get agent ID
     * @return Agent ID
     */
    int getAgentId() const;
    
    /**
     * @brief Get CLI options
     * @return CLI options string
     */
    string getCliOptions() const;
};

#endif /* MLSCAN_STATE_HPP */
