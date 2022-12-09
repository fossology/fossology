/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Maximilian Huber

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef REGEXCONFPARSER_HPP_
#define REGEXCONFPARSER_HPP_

#include <map>
#include <sstream>
#include <fstream>

extern "C" {
#include "libfossology.h"
}

/**
 * \typedef
 * Key value pair regex name in key and pattern in value
 */
typedef std::map<std::string, std::string> RegexMap;

RegexMap readConfStreamToMap(std::istringstream& stream,
                               const bool isVerbosityDebug = false);

RegexMap readConfStreamToMap(std::ifstream& stream,
                               const bool isVerbosityDebug = false);

void addRegexToMap(/*in and out*/ RegexMap& oldMap,
                    const std::string& regexDesc,
                    const bool isVerbosityDebug = false);

std::string replaceTokens(/*in*/ RegexMap& dict,
                          const std::string& constInput);

#endif /* REGEXCONFPARSER_HPP_ */
