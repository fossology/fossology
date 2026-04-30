/*
 SPDX-FileCopyrightText: © 2015,2022, Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "copyscan.hpp"
#include <cctype>
#include <algorithm>
#include "regexConfProvider.hpp"

extern "C" {
#include "libfossology.h"
}

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
  regSpdxCopyright = rx::regex(rcp.getRegexValue("copyright","REG_SPDX_COPYRIGHT"),
                     rx::regex_constants::icase);

  // Cleanup
  regExceptionCopy = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_COPY"),
                     rx::regex_constants::icase);
  regRemoveFileStmt = rx::regex(rcp.getRegexValue("copyright","REG_REMOVE_FILE_STATEMENT"),
                      rx::regex_constants::icase);
  regStripLicenseTrail = rx::regex(rcp.getRegexValue("copyright", "REG_STRIP_LICENSE_TRAIL"),
                         rx::regex_constants::icase);
  regStripTrademarkTrail = rx::regex(rcp.getRegexValue("copyright", "REG_STRIP_TRADEMARK_TRAIL"),
                           rx::regex_constants::icase);
  regStripAllRightReserveTrail = rx::regex(rcp.getRegexValue("copyright", "REG_ALL_RIGHT_RESERVE_TRAIL"),
                                 rx::regex_constants::icase);
  regExceptionVerbFollow = rx::regex(rcp.getRegexValue("copyright", "REG_EXCEPTION_VERB_FOLLOW"),
                           rx::regex_constants::icase);
  regExceptionAdjectivePrefix = rx::regex(rcp.getRegexValue("copyright", "REG_EXCEPTION_ADJECTIVE_PREFIX"),
                                rx::regex_constants::icase);
  regExceptionTemplate = rx::regex(rcp.getRegexValue("copyright", "REG_EXCEPTION_TEMPLATE"),
                         rx::regex_constants::icase);
  regExceptionPassive = rx::regex(rcp.getRegexValue("copyright", "REG_EXCEPTION_PASSIVE"),
                        rx::regex_constants::icase);
  regStripCopySymNonYear = rx::regex(rcp.getRegexValue("copyright", "REG_STRIP_COPYSYM_NONYEAR"),
                           rx::regex_constants::icase);
  regExceptionBinaryNoise = rx::regex(rcp.getRegexValue("copyright", "REG_EXCEPTION_BINARY_NOISE"),
                            rx::regex_constants::icase);
  regExceptionMeta = rx::regex(rcp.getRegexValue("copyright", "REG_EXCEPTION_META"),
                     rx::regex_constants::icase);
  regExceptionCharNameRun = rx::regex(rcp.getRegexValue("copyright", "REG_EXCEPTION_CHARNAME_RUN"),
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
        if (rx::regex_search(beginOfLine, endOfLine, regSpdxCopyright)){
          // Found end
          break;
        }
        if (rx::regex_search(beginOfLine, endOfLine, regSimpleCopyright)
          || !rx::regex_match(beginOfLine, endOfLine, regNonBlank))
        {
          // Found end
          break;
        }
        j = endOfLine;
      }
      string raw = string(foundPos, j);
      CleanupResult result = Cleanup(raw);

      if (result.disposition == CleanupResult::Disposition::DISCARD) {
        // Definitively not a copyright
        pos = j;
        continue;
      }

      if (result.disposition == CleanupResult::Disposition::DEACTIVATE) {
        int startPos = foundPos - begin;
        int endPos = j - begin;
        if (endPos - startPos > 300)
          endPos = startPos + 300;
        out.push_back(match(startPos, endPos, copyrightType, false));
        pos = j;
        continue;
      }

      string& cleaned = result.content;
      if (cleaned.size() > 300)
        cleaned = cleaned.substr(0, 300);

      out.push_back(match(foundPos - begin, (foundPos - begin) + cleaned.size(), copyrightType));
      pos = j;
    }
    else
    {
      // An exception: this is not a copyright statement: continue at the end of this statement
      pos = results[0].second;
    }
  }
}

CleanupResult hCopyrightScanner::Cleanup(const string &raw) const {
  using Disposition = CleanupResult::Disposition;

  if (rx::regex_match(raw, regRemoveFileStmt)) {
    return {"", Disposition::DEACTIVATE};
  }

  string cleaned = raw;
  cleaned = rx::regex_replace(cleaned, regStripLicenseTrail, "");
  cleaned = rx::regex_replace(cleaned, regStripTrademarkTrail, "");
  cleaned = rx::regex_replace(cleaned, regStripAllRightReserveTrail, "");
  cleaned = rx::regex_replace(cleaned, regStripCopySymNonYear, "");

  // DEACTIVATE
  if (rx::regex_search(cleaned, regExceptionTemplate) ||
    rx::regex_search(cleaned, regExceptionBinaryNoise) ||
    rx::regex_search(cleaned, regExceptionMeta) ||
    rx::regex_search(cleaned, regExceptionCharNameRun)) {
    return {"", Disposition::DEACTIVATE};
  }

  // DEACTIVATE: Limit scope to the first copyright context only.
  string deactivateScope = cleaned;
  {
    string lower = cleaned;
    transform(lower.begin(), lower.end(), lower.begin(), ::tolower);
    size_t first = lower.find("copyright");
    if (first != string::npos) {
      size_t second = lower.find("copyright", first + 9);
      if (second != string::npos) {
        deactivateScope = cleaned.substr(0, second);
      }
    }
  }

  if (rx::regex_search(deactivateScope, regExceptionCopy) ||
    rx::regex_search(deactivateScope, regExceptionVerbFollow) ||
    rx::regex_search(deactivateScope, regExceptionAdjectivePrefix) ||
    rx::regex_search(deactivateScope, regExceptionPassive)) {
    return {"", Disposition::DEACTIVATE};
  }

  RemoveNoisePatterns(cleaned);
  TrimPunctuation(cleaned);
  NormalizeCopyright(cleaned);
  StripSuffixes(cleaned);

  if (cleaned.empty()) {
    return {"", Disposition::DISCARD};
  }

  return {cleaned, Disposition::KEEP};
}

void hCopyrightScanner::TrimPunctuation(string &text) const{
  const string trimCharsAll = ",\'\"-:;&@!";
  const string trimStartOnly = ".>)]\\/";
  const string trimEndOnly = "<([\\/";

  size_t start = text.find_first_not_of(trimCharsAll);
  size_t end = text.find_last_not_of(trimCharsAll);

  if (start == string::npos) {
    text.clear();
    return;
  }

  text = text.substr(start, end - start + 1);

  while (!text.empty() && trimStartOnly.find(text.front()) != string::npos) {
    text.erase(0, 1);
  }

  while (!text.empty() && trimEndOnly.find(text.back()) != string::npos) {
    text.pop_back();
  }
}

void hCopyrightScanner::RemoveNoisePatterns(string& text) const{
  const vector<string> patterns = {
    "<p>", "<a href", "date-of-software", "date-of-document",
    " $ ", " ? ", "</a>", "( )", "()"
  };

  for (const auto& word : patterns) {
    size_t pos;
    while ((pos = text.find(word)) != string::npos) {
      text.replace(pos, word.length(), " ");
    }
  }
}

void hCopyrightScanner::NormalizeCopyright(string& text) const {
  const vector<pair<string, string>> replacements = {
    {"SPDX-FileCopyrightText", "Copyright"},
    {"AssemblyCopyright", "Copyright"},
    {"AppCopyright", "Copyright"},
    {"JCOPYRIGHT", "Copyright"},
    {"COPYRIGHT Copyright", "Copyright"},
    {"Copyright Copyright", "Copyright"},
    {"Copyright copyright", "Copyright"},
    {"copyright copyright", "Copyright"},
    {"copyright Copyright", "Copyright"},
    {"copyright\"Copyright", "Copyright"},
    {"copyright\" Copyright", "Copyright"}
  };

  for (const auto& pair : replacements) {
    const string& from = pair.first;
    const string& to = pair.second;

    size_t pos;
    while ((pos = text.find(from)) != string::npos) {
      text.replace(pos, from.length(), to);
    }
  }
}

void hCopyrightScanner::StripSuffixes(string& text) const{
  const vector<string> suffixes = {
    "copyright", ",", "year", "parts", "0", "1", "author", "all", "some", "and"
  };

  for (const auto& suffix : suffixes) {
    if (text.length() > suffix.length() + 1 &&
        text.size() >= suffix.size() &&
        text.compare(text.size() - suffix.size(), suffix.size(), suffix) == 0)
    {
      text.erase(text.size() - suffix.size());
      break;
    }
  }
}



