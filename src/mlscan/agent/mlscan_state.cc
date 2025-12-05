/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file mlscan_state.cc
 * @brief State management implementation
 */

#include "mlscan_state.hpp"

/**
 * @brief Constructor
 */
MLScanState::MLScanState(int agent_id) : agentId(agent_id), cliOptions("") {
}

/**
 * @brief Set command line options
 */
void MLScanState::setCliOptions(const string& options) {
    cliOptions = options;
}

/**
 * @brief Get agent ID
 */
int MLScanState::getAgentId() const {
    return agentId;
}

/**
 * @brief Get CLI options
 */
string MLScanState::getCliOptions() const {
    return cliOptions;
}
