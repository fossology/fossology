/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "regscan.hpp"

/**
 * \brief Initialize RegexConfProvider and regex based on type and identity
 * \see RegexConfProvider::maybeLoad(const std::string& identity)
 */
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

/**
 * \overload
 * \brief Initialize RegexConfProvider and regex based on type and stream
 * \see RegexConfProvider::maybeLoad(const std::string& identity,
 *                                   std::istringstream& stream)
 */
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

/**
 * \brief Scan a string using regex defined during initialization
 * \param[in]  s       String to scan
 * \param[out] results List of match results
 */
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

