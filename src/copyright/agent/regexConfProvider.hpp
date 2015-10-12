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

class RegexConfProvider
{
public:
  explicit RegexConfProvider(const bool isVerbosityDebug = false);

  void maybeLoad(const std::string& identity);
  void maybeLoad(const std::string& identity,
                 std::istringstream& stream);

  const char* getRegexValue(const std::string& name,
                            const std::string& key);

private:
  static std::map<std::string,RegexMap> _regexMapMap;

  bool _isVerbosityDebug;

  bool getRegexConfStream(const std::string& identity,
                          /*out*/ std::ifstream& stream);
};

#endif /* REGEXCONFPROVIDER_HPP_ */
