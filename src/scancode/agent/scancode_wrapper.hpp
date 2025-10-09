/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

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

void scanFileWithScancode(const State& state, string fileLocation, string outputFile,int parallelParams[5]);
map<string, vector<Match>> extractDataFromScancodeResult( const string& scancodeResult, const string& filename);

#endif // SCANCODE_AGENT_SCANCODE_WRAPPER_HPP
