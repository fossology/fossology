/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "regexMatcher.hpp"

RegexMatcher::RegexMatcher(const char* type, const char* pattern) : Matcher(type)
{
  matchingRegex = rx::regex(pattern, rx::regex_constants::icase);
}

std::vector <CopyrightMatch> RegexMatcher::match(const std::string content) const {

  std::vector <CopyrightMatch> results;

  std::string::const_iterator begin = content.begin();
  std::string::const_iterator end = content.end();
  boost::match_results<std::string::const_iterator> what;

  std::string::const_iterator begin2 =begin;
  while (regex_search(begin, end, what,matchingRegex)) {
    results.push_back(CopyrightMatch(what, getType()));
    begin = what[0].second;
  }

  return results;
}

RegexMatcher::~RegexMatcher(){};
