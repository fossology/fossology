/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scancode_state.hpp"

/**
 * @brief   constructor for state class
 * @param   agentId agent_pk in the agent table
 */
State::State(int agentId) : agentId(agentId) {}

////// Getters //////
/**
 * @brief   getter function for agent Id
 * @return integer  agentId(agent_pk in the agent table)
 */
int State::getAgentId() const { return agentId; };

/**
 * @brief   getter function for cliOptions
 * @return  string  cliOptions  command line options for scancode toolkit
 */
string State::getCliOptions() const { return cliOptions; };

////// Setters //////
/**
 * @brief   setter for command line interface options
 * @param   cliOptions  command line options for scancode toolkit 
 */
void State::setCliOptions(string cliOptions){
    this->cliOptions = cliOptions;
}