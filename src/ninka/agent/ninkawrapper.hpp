/*
 * Copyright (C) 2014, Siemens AG
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef NINKA_AGENT_NINKA_WRAPPER_HPP
#define NINKA_AGENT_NINKA_WRAPPER_HPP

#define AGENT_NAME "ninka"
#define AGENT_DESC "ninka agent"
#define AGENT_ARS  "ninka_ars"

#include <string>
#include <vector>
#include "files.hpp"
#include "licensematch.hpp"
#include "state.hpp"

using namespace std;

string scanFileWithNinka(const State& state, const fo::File& file);
vector<string> extractLicensesFromNinkaResult(string ninkaResult);
string extractLicensePartFromNinkaResult(string ninkaResult);
vector<string> splitLicensePart(string licensePart);
vector<LicenseMatch> createMatches(vector<string> ninkaLicenseNames);
string mapLicenseFromNinkaToFossology(string name);

#endif // NINKA_AGENT_NINKA_WRAPPER_HPP
