/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
bool isLicenseCollection(string ninkaLicenseName,vector<LicenseMatch>& matches);

#endif // NINKA_AGENT_NINKA_WRAPPER_HPP
