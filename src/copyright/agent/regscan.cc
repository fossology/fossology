/*
 * Copyright (C) 2015, Siemens AG
 * Author: Florian Krügel
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

#include "regscan.hpp"

regexScanner::regexScanner(const string& type,
                           const string& identity,
                           int index)
  : _type(type),
    _identity(identity),
    _index(index)
{
  RegexConfProvider rcp;
  rcp.maybeLoad(_identity);
  _reg = rx::regex(rcp.getRegexValue(_identity, _type),
                   rx::regex_constants::icase);
}

regexScanner::regexScanner(const string& type,
                           std::istringstream& stream,
                           int index)
  : _type(type),
    _identity(type),
    _index(index)
{
  RegexConfProvider rcp;
  rcp.maybeLoad(_identity,stream);
  _reg = rx::regex(rcp.getRegexValue(_identity, _type),
                   rx::regex_constants::icase);
}

void regexScanner::ScanString(const string& s, list<match>& results) const
{
  // Read file into one string
  string::const_iterator end = s.end();
  string::const_iterator pos = s.begin();
  unsigned int intPos = 0;
  
  while (pos != end)
  {
    // Find next match
    rx::smatch res;
    if (rx::regex_search(pos, end, res, _reg))
    {
      // Found match
      results.push_back(match(intPos + res.position(_index),
                              intPos + res.position(_index) + res.length(_index),
                              _type));
      pos = res[0].second;
      intPos += res.position() + res.length();
    }
    else
      // No match found
      break;
  }
}

