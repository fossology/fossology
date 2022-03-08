/*****************************************************************************
 * SPDX-License-Identifier: GPL-2.0
 * SPDX-FileCopyrightText: 2021 Sarita Singh <saritasingh.0425@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ****************************************************************************/

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