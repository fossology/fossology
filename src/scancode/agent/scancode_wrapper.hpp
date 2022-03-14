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

#ifndef SCANCODE_AGENT_SCANCODE_WRAPPER_HPP
#define SCANCODE_AGENT_SCANCODE_WRAPPER_HPP

#define AGENT_NAME "scancode"
#define AGENT_DESC "scancode agent"
#define AGENT_ARS  "scancode_ars"

#include <fstream>
#include <iostream>
#include <map>
#include <string>
#include <vector>

#include <boost/tokenizer.hpp>
#include <jsoncpp/json/json.h>

#include "files.hpp"
#include "match.hpp"
#include "scancode_state.hpp"
#include "scancode_utils.hpp"

using namespace std;

string scanFileWithScancode(const State& state, const fo::File& file);
string scanFileWithScancode(string filename);
map<string, vector<Match>> extractDataFromScancodeResult( const string& scancodeResult, const string& filename);

#endif // SCANCODE_AGENT_SCANCODE_WRAPPER_HPP
