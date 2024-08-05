/*
 SPDX-FileCopyrightText: © 2015,2022, Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "copyscan.hpp"
#include <unicode/schriter.h>
#include <unicode/brkiter.h>

#include <cctype>
#include <algorithm>
#include "regexConfProvider.hpp"

const string copyrightType("statement");  /**< A constant for default copyrightType as "statement" */

/**
 * \brief Get the position of the line break
 *
 * Calculate the position of the line break in the input string using CRLF, CR,
 * or LF.
 * @param input Input string to search in
 * @param begin Begin pointer of the string
 * @param end End pointer of the string
 * @param pos Position pointer to search from
 * @return Position of the line break, else -1
 */
int32_t getLineBreakPosition(const icu::UnicodeString& input, const UChar* begin, const UChar* end, const UChar* pos)
{
  int32_t linePos = input.indexOf(icu::UnicodeString(u"\r\n"), pos - begin, end - pos);
  if (linePos == -1)
  {
    linePos = input.indexOf(u'\r', pos - begin, end - pos);
    if (linePos == -1)
    {
      linePos = input.indexOf(u'\n', pos - begin, end - pos);
    }
  }
  return linePos;
}

/**
 * \brief Constructor for default hCopyrightScanner
 *
 * Initialize all regex values
 */
hCopyrightScanner::hCopyrightScanner()
{
  RegexConfProvider rcp;
  rcp.maybeLoad("copyright");

  regCopyright = rx::make_u32regex(rcp.getRegexValue("copyright","REG_COPYRIGHT"),
                                   rx::regex_constants::icase);

  regException = rx::make_u32regex(rcp.getRegexValue("copyright","REG_EXCEPTION"),
                                   rx::regex_constants::icase);

  regNonBlank = rx::make_u32regex(rcp.getRegexValue("copyright","REG_NON_BLANK"));

  regSimpleCopyright = rx::make_u32regex(rcp.getRegexValue("copyright","REG_SIMPLE_COPYRIGHT"),
                                         rx::regex_constants::icase);

  regSpdxCopyright = rx::make_u32regex(rcp.getRegexValue("copyright","REG_SPDX_COPYRIGHT"),
                                       rx::regex_constants::icase);
}

/**
 * \brief Scan a given string for copyright statements
 *
 * Given a string s, scans for copyright statements using regCopyrights.
 * Then checks for an regException match.
 * \param[in]  s   String to work on
 * \param[out] results List of matchs
 */
void hCopyrightScanner::ScanString(const icu::UnicodeString& s, list<match>& results) const
{
  auto const begin = s.getBuffer();
  auto pos = begin;
  auto const end = begin + s.length();

  while (pos != end)
  {
    // Find potential copyright statement
    rx::u16match matches;
    if (!rx::u32regex_search(pos, end, matches, regCopyright))
      // No further copyright statement found
      break;
    auto const foundPos = matches[0].first;
    auto const foundIndex = s.indexOf(*foundPos, foundPos - begin, end - foundPos);

    if (!rx::u32regex_match(foundPos, end, regException))
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
      auto const linePos = getLineBreakPosition(s, begin, end, foundPos);
      auto j = end;
      if (linePos != -1)
      {
        j = begin + linePos;
      }
      while (j != end)
      {
        auto beginOfLine = j;
        ++beginOfLine;
        auto const posEndOfLine = getLineBreakPosition(s, begin, end, beginOfLine);
        auto const endOfLine = begin + posEndOfLine;
        if (rx::u32regex_search(beginOfLine, endOfLine, regSpdxCopyright))
        {
          // Found end
          break;
        }
        if (rx::u32regex_search(beginOfLine, endOfLine, regSimpleCopyright)
          || !rx::u32regex_match(beginOfLine, endOfLine, regNonBlank))
        {
          // Found end
          break;
        }
        j = endOfLine;
      }
      auto const endIndex = s.indexOf(*j, j - begin, end - j);
      if (endIndex - foundIndex >= 301)
      {
        // Truncate
        results.emplace_back(foundIndex, foundIndex + 300, copyrightType);
      }
      else
      {
        results.emplace_back(foundIndex, endIndex, copyrightType);
      }
      pos = j;
    }
    else
    {
      // An exception: this is not a copyright statement: continue at the end of this statement
      pos = matches[0].second;
    }
  }
}
