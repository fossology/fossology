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

#define DEFAULT_DEBUG_VERBOSITY false
class RegexConfProvider
{
public:
  static RegexConfProvider* _instance;
  static RegexConfProvider* instance (const bool isVerbosityDebug = DEFAULT_DEBUG_VERBOSITY)
  {
    static RegexConfProviderGuard g;
    if (!_instance)
      _instance = new RegexConfProvider (isVerbosityDebug);
    return _instance;
  }
  static void resetInstance()
  {
    if(RegexConfProvider::_instance)
      delete RegexConfProvider::_instance;
    RegexConfProvider::_instance = 0;
  }

  const char* getRegexValue(const std::string name,
                      const std::string key);

  RegexDict getRegexDict(const std::string identity);

  void maybeLoad(const char* identity);
  void maybeLoad(const std::string identity,
                 std::istringstream& stream);

private:
  RegexConfProvider(const bool isVerbosityDebug);
  ~RegexConfProvider () { }
  class RegexConfProviderGuard
  {
  public:
    ~RegexConfProviderGuard() {
      if ( NULL != RegexConfProvider::_instance ) {
        delete RegexConfProvider::_instance;
        RegexConfProvider::_instance = NULL;
      }
    }
  };

  std::map<std::string,RegexDict> glblRegexMap;

  bool glblIsVerbosityDebug;

  bool getRegexConfStream(const std::string& identity,
                          /*out*/ std::ifstream& stream);
};

#endif /* REGEXCONFPROVIDER_HPP_ */
