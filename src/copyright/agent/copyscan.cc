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

  // Compiled once per scanner instance; optimize pre-builds DFA states so
  // repeated regex_search calls across many files pay no recompilation cost.
  const auto icaseOptimize = rx::regex_constants::icase | rx::regex_constants::optimize;

  regCopyright = rx::regex(rcp.getRegexValue("copyright","REG_COPYRIGHT"), icaseOptimize);
  regException = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION"), icaseOptimize);
  regNonBlank = rx::regex(rcp.getRegexValue("copyright","REG_NON_BLANK"), rx::regex_constants::optimize);
  regSimpleCopyright = rx::regex(rcp.getRegexValue("copyright","REG_SIMPLE_COPYRIGHT"), icaseOptimize);
  regSpdxCopyright = rx::regex(rcp.getRegexValue("copyright","REG_SPDX_COPYRIGHT"), icaseOptimize);

  // Cleanup
  regExceptionCopy = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_COPY"), icaseOptimize);
  regRemoveFileStmt = rx::regex(rcp.getRegexValue("copyright","REG_REMOVE_FILE_STATEMENT"), icaseOptimize);
  regStripLicenseTrail = rx::regex(rcp.getRegexValue("copyright","REG_STRIP_LICENSE_TRAIL"), icaseOptimize);
  regStripTrademarkTrail = rx::regex(rcp.getRegexValue("copyright","REG_STRIP_TRADEMARK_TRAIL"), icaseOptimize);
  regStripAllRightReserveTrail = rx::regex(rcp.getRegexValue("copyright","REG_ALL_RIGHT_RESERVE_TRAIL"), icaseOptimize);
  regExceptionVerbFollow = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_VERB_FOLLOW"), icaseOptimize);
  regExceptionAdjectivePrefix = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_ADJECTIVE_PREFIX"), icaseOptimize);
  regExceptionTemplate = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_TEMPLATE"), icaseOptimize);
  regExceptionPassive = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_PASSIVE"), icaseOptimize);
  regStripCopySymNonYear = rx::regex(rcp.getRegexValue("copyright","REG_STRIP_COPYSYM_NONYEAR"), icaseOptimize);
  regExceptionBinaryNoise = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_BINARY_NOISE"), icaseOptimize);
  regExceptionMeta = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_META"), icaseOptimize);
  regExceptionCharNameRun = rx::regex(rcp.getRegexValue("copyright","REG_EXCEPTION_CHARNAME_RUN"), icaseOptimize);
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

      bool isSpdx = rx::regex_search(foundPos, j, regSpdxCopyright);
      if (!isSpdx)
      {
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
      }

      string raw = string(foundPos, j);

      // SPDX-FileCopyrightText = [ ... ] array format for toml files.
      if (isSpdx) {
        static const rx::regex reArrayOpen("\\[\\s*$");
        if (rx::regex_search(raw, reArrayOpen)) {
          // Deactivate the array-header line itself
          int startPos = foundPos - begin;
          int endPos   = j - begin;
          out.push_back(match(startPos, endPos, copyrightType, false));

          // Walk subsequent lines extracting each quoted copyright element
          static const rx::regex reQuoted("\"([^\"]+)\"");
          static const rx::regex reClose("^[[:space:]]*\\]");
          string::const_iterator arrPos = j;
          bool arrayClosedCleanly = false;
          while (arrPos != end) {
            string::const_iterator lineStart = arrPos + 1;
            if (lineStart >= end) break;
            string::const_iterator lineEnd = find(lineStart, end, '\n');
            string lineStr(lineStart, lineEnd);

            if (rx::regex_search(lineStr, reClose)) {
              arrPos = lineEnd;
              arrayClosedCleanly = true;
              break;
            }

            rx::smatch qm;
            if (rx::regex_search(lineStr, qm, reQuoted)) {
              string elemRaw = "SPDX-FileCopyrightText: " + qm[1].str();
              CleanupResult er = Cleanup(elemRaw);
              int eStart = lineStart - begin;
              int eEnd   = lineEnd - begin;
              if (eEnd - eStart > 300) eEnd = eStart + 300;
              if (er.disposition == CleanupResult::Disposition::KEEP)
                out.push_back(match(eStart, eEnd, copyrightType));
              else if (er.disposition == CleanupResult::Disposition::DEACTIVATE)
                out.push_back(match(eStart, eEnd, copyrightType, false));
            }
            arrPos = lineEnd;
          }
          pos = arrayClosedCleanly ? arrPos : j;
          continue;
        }
      }

      CleanupResult result = Cleanup(raw);

      if (result.disposition == CleanupResult::Disposition::DISCARD) {
        // Definitively not a copyright
        pos = j;
        continue;
      }

      int startPos = foundPos - begin;
      int endPos   = j - begin;

      if (result.disposition == CleanupResult::Disposition::DEACTIVATE) {
        if (endPos - startPos > 300)
          endPos = startPos + 300;
        out.push_back(match(startPos, endPos, copyrightType, false));
        pos = j;
        continue;
      }

      string& cleaned = result.content;
      if (cleaned.size() > 300)
        cleaned = cleaned.substr(0, 300);

      if (!isSpdx)
        endPos = startPos + (int)cleaned.size();
      if (endPos - startPos > 300)
        endPos = startPos + 300;

      out.push_back(match(startPos, endPos, copyrightType));
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

  // regex_replace always allocates even with no match; guard with regex_search.
  if (rx::regex_search(cleaned, regStripLicenseTrail))
    cleaned = rx::regex_replace(cleaned, regStripLicenseTrail, string());
  if (rx::regex_search(cleaned, regStripTrademarkTrail))
    cleaned = rx::regex_replace(cleaned, regStripTrademarkTrail, string());
  if (rx::regex_search(cleaned, regStripAllRightReserveTrail))
    cleaned = rx::regex_replace(cleaned, regStripAllRightReserveTrail, string());
  if (rx::regex_search(cleaned, regStripCopySymNonYear))
    cleaned = rx::regex_replace(cleaned, regStripCopySymNonYear, string());

  // DEACTIVATE
  if (rx::regex_search(cleaned, regExceptionTemplate) ||
    rx::regex_search(cleaned, regExceptionBinaryNoise) ||
    rx::regex_search(cleaned, regExceptionMeta) ||
    rx::regex_search(cleaned, regExceptionCharNameRun)) {
    return {"", Disposition::DEACTIVATE};
  }

  // Limit exception checks to the first copyright context; use iterators to
  // avoid a substring copy.
  {
    static const string kCopyright("copyright");
    auto ciEq = [](unsigned char a, unsigned char b){
      return ::tolower(a) == ::tolower(b);
    };
    auto dBegin = cleaned.cbegin();
    auto dEnd   = cleaned.cend();
    auto it1 = std::search(dBegin, dEnd, kCopyright.cbegin(), kCopyright.cend(), ciEq);
    if (it1 != dEnd) {
      auto it2 = std::search(it1 + 9, dEnd, kCopyright.cbegin(), kCopyright.cend(), ciEq);
      if (it2 != dEnd)
        dEnd = it2;
    }
    if (rx::regex_search(dBegin, dEnd, regExceptionCopy) ||
      rx::regex_search(dBegin, dEnd, regExceptionVerbFollow) ||
      rx::regex_search(dBegin, dEnd, regExceptionAdjectivePrefix) ||
      rx::regex_search(dBegin, dEnd, regExceptionPassive)) {
      return {"", Disposition::DEACTIVATE};
    }
  }

  RemoveNoisePatterns(cleaned);
  TrimPunctuation(cleaned);
  NormalizeCopyright(cleaned);
  StripSuffixes(cleaned);

  if (cleaned.empty()) {
    return {"", Disposition::DISCARD};
  }

  // Discard bare keyword: "copyright"(9) / "copyrights"(10) / "copyrighted"(11).
  if (cleaned.size() >= 9 && cleaned.size() <= 11) {
    string lower = cleaned;
    transform(lower.begin(), lower.end(), lower.begin(), ::tolower);
    if (lower == "copyright" || lower == "copyrights" || lower == "copyrighted") {
      return {"", Disposition::DISCARD};
    }
  }

  return {cleaned, Disposition::KEEP};
}

void hCopyrightScanner::TrimPunctuation(string &text) const{
  static const string trimCharsAll  = " \t,\'\"-:;&@!";
  static const string trimStartOnly = ".>)]\\/";
  static const string trimEndOnly   = "<([\\/";

  size_t start = text.find_first_not_of(trimCharsAll);
  size_t end   = text.find_last_not_of(trimCharsAll);

  if (start == string::npos) {
    text.clear();
    return;
  }

  text = text.substr(start, end - start + 1);

  // Count, then erase once.
  size_t leading = 0;
  while (leading < text.size() && trimStartOnly.find(text[leading]) != string::npos)
    ++leading;
  if (leading) text.erase(0, leading);

  while (!text.empty() && trimEndOnly.find(text.back()) != string::npos)
    text.pop_back();
}

void hCopyrightScanner::RemoveNoisePatterns(string& text) const{
  static const vector<string> patterns = {
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
  static const vector<pair<string, string>> replacements = {
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
  static const vector<string> suffixes = {
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



