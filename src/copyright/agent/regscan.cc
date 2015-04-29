/*
 * Copyright (C) 2015, Siemens AG
 * Author: Florian Krügel
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "regscan.hpp"



regexScanner::regexScanner(const string& sReg, const char* t)
  : reg(rx::regex(sReg, rx::regex_constants::icase)), type(t), index(0)
{ }

regexScanner::regexScanner(const string& sReg, const char* t, int idx)
  : reg(rx::regex(sReg, rx::regex_constants::icase)), type(t), index(idx)
{ }

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
    if (rx::regex_search(pos, end, res, reg))
    {
      // Found match
      results.push_back(match(intPos + res.position(index), intPos + res.position(index) + res.length(index), type));
      pos = res[0].second;
      intPos += res.position() + res.length();
    }
    else
      // No match found
      break;
  }
}

