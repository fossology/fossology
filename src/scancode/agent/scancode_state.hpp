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

#ifndef SCANCODE_AGENT_STATE_HPP
#define SCANCODE_AGENT_STATE_HPP

#include "libfossdbmanagerclass.hpp"
#include "scancode_dbhandler.hpp"

using namespace std;

/**
 * class to get agent Id and CliOptions
 */
class State {
public:
  State(int agentId);
  void setCliOptions(string cliOptions);
  int getAgentId() const;
  string getCliOptions() const;

private:
  /**
   * agent Id is agent_pk in the agent table
   */
  int agentId;

  /**
   * cliOptions command line options for scancode toolkit
   */
  string cliOptions;
};

#endif // SCANCODE_AGENT_STATE_HPP