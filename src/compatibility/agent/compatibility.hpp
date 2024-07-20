/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef COMPATIBILITY_AGENT_COMPATIBILITY_HPP
#define COMPATIBILITY_AGENT_COMPATIBILITY_HPP

#include "CompatibilityAgent.hpp"
#include "CompatibilityUtils.hpp"

#include <fstream>
#include <iostream>
#include <sstream>
#include <tuple>

extern "C"
{
#include "libfossagent.h"
}

using namespace std;
vector<tuple<string, string, bool>> checkCompatibility(
    const set<string>& license_list,
    const unordered_map<string, string>& license_map,
    const map<tuple<string, string, string, string>, bool>& rule_list,
    map<tuple<string, string>, bool>& scan_results);

#endif // COMPATIBILITY_AGENT_COMPATIBILITY_HPP
