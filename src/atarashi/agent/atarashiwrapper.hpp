/*
 * Copyright (C) 2019-2020, Siemens AG
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
 */

#ifndef ATARASHI_AGENT_ATARASHI_WRAPPER_HPP
#define ATARASHI_AGENT_ATARASHI_WRAPPER_HPP

#include <fstream>
#include <iostream>
#include <map>
#include <string>
#include <vector>

#include <jsoncpp/json/json.h>
#include <boost/tokenizer.hpp>

#include "files.hpp"
#include "licensematch.hpp"
#include "state.hpp"
#include "utils.hpp"

using namespace std;

bool scanFileWithAtarashi(const State& state, const std::string& filePath, const std::string& outputFile);
vector<LicenseMatch> extractLicensesFromAtarashiResult(const string& jsonContent);
#endif // ATARASHI_AGENT_ATARASHI_WRAPPER_HPP
