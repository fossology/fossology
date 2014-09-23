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
  matchingRegex = std::regex(pattern); //, std::regex_constants::icase
}

CopyrightMatch* RegexMatcher::match(const std::string content) const {
  std::cout << content << std::endl;
  std::smatch sm;

  if(std::regex_match(content.cbegin(), content.cend(), sm, matchingRegex) ) {
    std::cout << "string object with " << sm.size() << " matches\n";
    for (unsigned matchI = 0; matchI < sm.size(); ++matchI) {
      std::cout << "match [" << matchI << "] = " << sm[matchI] << std::endl;
    }
    return new CopyrightMatch(sm, getType()); }
  else return NULL;
}

RegexMatcher::~RegexMatcher(){};
