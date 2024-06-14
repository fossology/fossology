/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Maximilian Huber

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef REGEXCONFPROVIDER_HPP_
#define REGEXCONFPROVIDER_HPP_

#include <string>
#include <vector>
#include <map>
#include <sstream>
#include <fstream>
#include <iostream>

#include "regexConfParser.hpp"

std::string getRegexConfFile(const std::string& identity);

/**
 * \class RegexConfProvider
 * \brief Provide regex using conf file
 */
class RegexConfProvider
{
public:
  explicit RegexConfProvider(const bool isVerbosityDebug = false);

  void maybeLoad(const std::string& identity);
  void maybeLoad(const std::string& identity,
                 std::wistringstream& stream);

  const icu::UnicodeString getRegexValue(const std::string& name,
                                         const std::string& key);

private:
  static std::map<std::string,RegexMap> _regexMapMap;

  bool _isVerbosityDebug;      /**< True to print debug messages */

  bool getRegexConfStream(const std::string& identity,
                          /*out*/ std::wifstream& stream);
};

#endif /* REGEXCONFPROVIDER_HPP_ */
