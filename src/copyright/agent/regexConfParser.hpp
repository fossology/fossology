/*
 * Copyright (C) 2015, Siemens AG
 * Author: Maximilian Huber
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef REGEXCONFPARSER_HPP_
#define REGEXCONFPARSER_HPP_

#include <map>
#include <sstream>
#include <fstream>

extern "C" {
#include "libfossology.h"
}

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
