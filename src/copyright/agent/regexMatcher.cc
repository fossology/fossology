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

RegexMatcher::RegexMatcher(const std::string type, const std::string pattern, int regexIndex) :
  Matcher(type), regexIndex(regexIndex), matchingRegex(rx::regex(pattern, rx::regex_constants::icase))
{
}

std::vector<CopyrightMatch> RegexMatcher::match(const std::string content) const
{
  std::vector<CopyrightMatch> results;

  std::string::const_iterator begin = content.begin();
  std::string::const_iterator end = content.end();
  rx::match_results <std::string::const_iterator> what;

  while (rx::regex_search(begin, end, what, matchingRegex))
  {
    CopyrightMatch newMatch(
      what.str(regexIndex),
      getType(),
      what.position(regexIndex) + (begin - content.begin()),
      what.length(regexIndex)
    );

    if ((results.size()==0) || !(newMatch <= (results[results.size()-1])))
    {
      results.push_back(newMatch);
    }

    begin = what[0].first;
    ++begin;
  }

  return results;
}

RegexMatcher::~RegexMatcher()
{
};

std::ostream& operator<<(std::ostream& os, const RegexMatcher& matcher)
{
  return (os << "type: " << matcher.getType() << " regex: " << matcher.matchingRegex << " capturingGroup: " << matcher.regexIndex);
}
