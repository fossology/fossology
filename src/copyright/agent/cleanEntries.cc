/*
 * Copyright (C) 2014-2015, Siemens AG
 * Author: Johannes Najjar
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
/**
 * \file cleanEntries.cc
 * \brief Clean strings
 * \todo rearrange copyright statments to try and put the holder first,
 * followed by the rest of the statement, less copyright years.
 * \todo skip "dnl "
*/
#include "cleanEntries.hpp"
#include <sstream>
#include <iterator>
using std::stringstream;
using std::ostream_iterator;

/**
 * \brief Trim space at beginning and end
 *
 * Since we already collapsed a sequence of spaces into one space, there can only be one space
 * \param sBegin String begin
 * \param sEnd   String end
 * \return string Trimmed string
 */
string cleanGeneral(string::const_iterator sBegin, string::const_iterator sEnd)
{
  stringstream ss;
  rx::regex_replace(ostream_iterator<char>(ss), sBegin, sEnd, rx::regex("[[:space:]\\x0-\\x1f]{2,}"), " ");
  string s = ss.str();
  string::size_type len = s.length();
  if (len > 1)
  {
    char cBegin = s[0];
    char cEnd = s[len - 1];
    if (cBegin == ' ' && cEnd == ' ')
      return s.substr(1, len - 2);
    else if (cBegin == ' ')
      return s.substr(1);
    else if (cEnd == ' ')
      return s.substr(0, len - 1);
  }
  // Only one character/space??? Should not be possible
  return s == " " ? "" : s;
}

/**
 * \brief Clean copyright statements from special characters
 * (comment characters in programming languages, multiple spaces etc.)
 * \param sBegin String begin
 * \param sEnd   String end
 * \return string Clean statements
 */
string cleanStatement(string::const_iterator sBegin, string::const_iterator sEnd)
{
  stringstream ss;
  rx::regex_replace(ostream_iterator<char>(ss), sBegin, sEnd, rx::regex("\n[[:space:][:punct:]]*"), " ");
  string s = ss.str();
  return cleanGeneral(s.begin(), s.end());
}

/**
 * \brief Clean the text based on type
 *
 * If match type is statement, clean as statement. Else clean as general text.
 * \param sText Text for cleaning
 * \param m     Matches to be cleaned
 * \return string Cleaned text
 */
string cleanMatch(const string& sText, const match& m)
{
  string::const_iterator it = sText.begin();
  if (m.type == "statement")
    return cleanStatement(it + m.start, it + m.end);
  else
    return cleanGeneral(it + m.start, it + m.end);
}

