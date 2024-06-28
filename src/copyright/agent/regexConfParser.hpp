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
#include <unicode/unistr.h>

extern "C" {
#include "libfossology.h"
}

/**
 * \typedef
 * Key value pair regex name in key and pattern in value
 */
typedef std::map<std::string, icu::UnicodeString> RegexMap;

RegexMap readConfStreamToMap(std::wistringstream& stream,
                             const bool isVerbosityDebug = false);

RegexMap readConfStreamToMap(std::wifstream& stream,
                             const bool isVerbosityDebug = false);

void addRegexToMap(/*in and out*/ RegexMap& oldMap,
                    const std::wstring& regexDesc,
                    const bool isVerbosityDebug = false);

icu::UnicodeString replaceTokens(/*in*/ RegexMap& dict,
                                 const std::wstring& constInput);

#endif /* REGEXCONFPARSER_HPP_ */
