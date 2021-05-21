/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef COMPATIBILITY_AGENT_COMPATIBILITY_HPP
#define COMPATIBILITY_AGENT_COMPATIBILITY_HPP

#include "CompatibilityAgent.hpp"
#include "CompatibilityUtils.hpp"
#include "yaml-cpp/yaml.h"

#include "json/json.h"
#include <fstream>
#include <iostream>
#include <sstream>
#include <tuple>

extern "C"
{
#include "libfossagent.h"
}

using namespace std;
vector<tuple<string, string, string>> checkCompatibility(
    vector<string> myVec, const string& lic_types, const string& rule);

#endif // COMPATIBILITY_AGENT_OJOS_HPP
