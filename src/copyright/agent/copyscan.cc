/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "copyscan.hpp"
#include <cctype>
#include <algorithm>
#include "regexConfProvider.hpp"

const string copyrightType("statement");  /**< A constant for default copyrightType as "statement" */

/**
 * \brief Constructor for default hCopyrightScanner
 *
 * Initialize all regex values
 */
hCopyrightScanner::hCopyrightScanner()
{
  RegexConfProvider rcp;
  rcp.maybeLoad("copyright");

  regCopyright = rx::regex(rcp.getRegexValue("copyright","REG_COPYRIGHT"),
                        rx::regex_constants::icase);

  regException = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION"),
               rx::regex_constants::icase);
  regNonBlank = rx::regex(rcp.getRegexValue("copyright","REG_NON_BLANK"));

  regSimpleCopyright = rx::regex(rcp.getRegexValue("copyright","REG_SIMPLE_COPYRIGHT"),
                     rx::regex_constants::icase);
}

/**
 * \brief Scan a given string for copyright statements
 *
 * Given a string s, scans for copyright statements using regCopyrights.
 * Then checks for an regException match.
 * \param[in]  s   String to work on
 * \param[out] out List of matchs
 */
void hCopyrightScanner::ScanString(const string& s, list<match>& out) const
{

  string::const_iterator begin = s.begin();
  string::const_iterator pos = begin;
  string::const_iterator end = s.end();
  while (pos != end)
  {
    // Find potential copyright statement
    rx::smatch results;
    if (!rx::regex_search(pos, end, results, regCopyright))
      // No further copyright statement found
      break;
    string::const_iterator foundPos = results[0].first;

    if (!rx::regex_match(foundPos, end, regException))
    {
      /**
       * Not an exception, this means that at foundPos there is a copyright statement.
       * Try to find the proper beginning and end before adding it to the out list.
       *
       * Copyright statements should extend over the following lines until
       * a blank line or a line with a new copyright statement is found.
       * A blank line may consist of
       *   - spaces and punctuation
       *   - no word of two letters, no two consecutive digits
      */
      string::const_iterator j = find(foundPos, end, '\n');
      while (j != end)
      {
        string::const_iterator beginOfLine = j;
        ++beginOfLine;
        string::const_iterator endOfLine = find(beginOfLine, end, '\n');
        if (rx::regex_search(beginOfLine, endOfLine, regSimpleCopyright)
          || !rx::regex_match(beginOfLine, endOfLine, regNonBlank))
        {
          // Found end
          break;
        }
        j = endOfLine;
      }
      if (j - foundPos >= 999)
        // Truncate
        out.push_back(match(foundPos - begin, (foundPos - begin) + 998, copyrightType));
      else
      {
        out.push_back(match(foundPos - begin, j - begin, copyrightType));
      }
      pos = j;
    }
    else
    {
      // An exception: this is not a copyright statement: continue at the end of this statement
      pos = results[0].second;
    }
  }
}

