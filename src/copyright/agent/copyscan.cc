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

extern "C" {
#include "libfossology.h"
}

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

  // getRegexValue returns icu::UnicodeString; convert to UTF-8 for rx::regex
  auto toUtf8 = [&](const std::string& key) {
    std::string out;
    rcp.getRegexValue("copyright", key).toUTF8String(out);
    return out;
  };

  // Detection regexes: operate on UChar* buffer, produce UChar16 offsets
  regCopyright = rx::make_u32regex(rcp.getRegexValue("copyright","REG_COPYRIGHT"),
                                   rx::regex_constants::icase);

  regException = rx::make_u32regex(rcp.getRegexValue("copyright","REG_EXCEPTION"),
                                   rx::regex_constants::icase);

  regNonBlank = rx::make_u32regex(rcp.getRegexValue("copyright","REG_NON_BLANK"));

  regSimpleCopyright = rx::make_u32regex(rcp.getRegexValue("copyright","REG_SIMPLE_COPYRIGHT"),
                                         rx::regex_constants::icase);

  regSpdxCopyright = rx::make_u32regex(rcp.getRegexValue("copyright","REG_SPDX_COPYRIGHT"),
                                       rx::regex_constants::icase);

  // Cleanup
  regExceptionCopy = rx::regex(toUtf8("REG_EXCEPTION_COPY"),
                     rx::regex_constants::icase);
  regRemoveFileStmt = rx::regex(toUtf8("REG_REMOVE_FILE_STATEMENT"),
                      rx::regex_constants::icase);
  regStripLicenseTrail = rx::regex(toUtf8("REG_STRIP_LICENSE_TRAIL"),
                         rx::regex_constants::icase);
  regStripTrademarkTrail = rx::regex(toUtf8("REG_STRIP_TRADEMARK_TRAIL"),
                           rx::regex_constants::icase);
  regStripAllRightReserveTrail = rx::regex(toUtf8("REG_ALL_RIGHT_RESERVE_TRAIL"),
                                 rx::regex_constants::icase);
  regExceptionVerbFollow = rx::regex(toUtf8("REG_EXCEPTION_VERB_FOLLOW"),
                           rx::regex_constants::icase);
  regExceptionAdjectivePrefix = rx::regex(toUtf8("REG_EXCEPTION_ADJECTIVE_PREFIX"),
                                rx::regex_constants::icase);
  regExceptionTemplate = rx::regex(toUtf8("REG_EXCEPTION_TEMPLATE"),
                         rx::regex_constants::icase);
  regExceptionPassive = rx::regex(toUtf8("REG_EXCEPTION_PASSIVE"),
                        rx::regex_constants::icase);
  regStripCopySymNonYear = rx::regex(toUtf8("REG_STRIP_COPYSYM_NONYEAR"),
                           rx::regex_constants::icase);
  regExceptionBinaryNoise = rx::regex(toUtf8("REG_EXCEPTION_BINARY_NOISE"),
                            rx::regex_constants::icase);
  regExceptionMeta = rx::regex(toUtf8("REG_EXCEPTION_META"),
                     rx::regex_constants::icase);
  regExceptionCharNameRun = rx::regex(toUtf8("REG_EXCEPTION_CHARNAME_RUN"),
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

  // u32regex_search is implemented with recursive descent and will overflow
  // the stack on very large inputs.  Search in bounded chunks to keep the
  // recursive depth manageable.
  static const int CHUNK_SIZE = 8192;
  static const int CHUNK_OVERLAP = 256;

  while (pos != end)
  {
    // Find potential copyright statement within a bounded chunk
    auto const searchEnd = (end - pos > CHUNK_SIZE) ? pos + CHUNK_SIZE : end;
    rx::u16match matches;
    if (!rx::u32regex_search(pos, searchEnd, matches, regCopyright))
    {
      // No match in this chunk; advance past it (keeping overlap for boundary matches)
      if (searchEnd == end)
        break;
      pos = searchEnd - CHUNK_OVERLAP;
      continue;
    }
    auto const foundPos = matches[0].first;

    // Limit the exception check to the first 256 UChar16 units from the
    // match position.  Exception phrases ("copyright licenses", etc.) are
    // short; running u32regex_match over the entire remaining file can
    // exceed boost's regex complexity limit on large inputs.
    auto const exEnd = (end - foundPos > 256) ? foundPos + 256 : end;
    if (!rx::u32regex_match(foundPos, exEnd, regException))
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
        auto const endOfLine = (posEndOfLine != -1) ? begin + posEndOfLine : end;

        // Cap the range to avoid stack overflow in u32regex_match/search
        // on very long "lines" (e.g., minified files with no newlines).
        // A line longer than 4096 UChar16 units is certainly non-blank.
        static const int MAX_LINE_CHECK = 4096;
        auto const checkEnd = (endOfLine - beginOfLine > MAX_LINE_CHECK)
                                ? beginOfLine + MAX_LINE_CHECK : endOfLine;

        if (rx::u32regex_search(beginOfLine, checkEnd, regSpdxCopyright))
        {
          // Found end
          break;
        }
        if (rx::u32regex_search(beginOfLine, checkEnd, regSimpleCopyright)
          || !rx::u32regex_match(beginOfLine, checkEnd, regNonBlank))
        {
          // Found end
          break;
        }
        j = endOfLine;
      }
      icu::UnicodeString sub;
      s.extractBetween(foundPos - begin, j - begin, sub);
      string raw;
      sub.toUTF8String(raw);
      CleanupResult result = Cleanup(raw);

      if (result.disposition == CleanupResult::Disposition::DISCARD) {
        // Definitively not a copyright
        pos = j;
        continue;
      }

      if (result.disposition == CleanupResult::Disposition::DEACTIVATE) {
        // deactivated copyright section
        results.push_back(match(foundPos - begin, j - begin, copyrightType, false));
        pos = j;
        continue;
      }

      string& cleaned = result.content;
      if (cleaned.size() > 300)
        cleaned = cleaned.substr(0, 300);

      icu::UnicodeString cleanedU = icu::UnicodeString::fromUTF8(cleaned);
      results.push_back(match(foundPos - begin, (foundPos - begin) + cleanedU.length(), copyrightType));
      pos = j;
    }
    else
    {
      // An exception: this is not a copyright statement: continue at the end of this statement
      pos = matches[0].second;
    }
  }
}

CleanupResult hCopyrightScanner::Cleanup(const string &raw) const {
  using Disposition = CleanupResult::Disposition;

  if (rx::regex_match(raw, regRemoveFileStmt)) {
    return {"", Disposition::DISCARD};
  }

  string cleaned = raw;
  cleaned = rx::regex_replace(cleaned, regStripLicenseTrail, "");
  cleaned = rx::regex_replace(cleaned, regStripTrademarkTrail, "");
  cleaned = rx::regex_replace(cleaned, regStripAllRightReserveTrail, "");
  cleaned = rx::regex_replace(cleaned, regStripCopySymNonYear, "");

  // DISCARD
  if (rx::regex_search(cleaned, regExceptionTemplate) ||
    rx::regex_search(cleaned, regExceptionBinaryNoise) ||
    rx::regex_search(cleaned, regExceptionMeta) ||
    rx::regex_search(cleaned, regExceptionCharNameRun)) {
    return {"", Disposition::DISCARD};
  }

  // DEACTIVATE
  if (rx::regex_search(cleaned, regExceptionCopy) ||
    rx::regex_search(cleaned, regExceptionVerbFollow) ||
    rx::regex_search(cleaned, regExceptionAdjectivePrefix) ||
    rx::regex_search(cleaned, regExceptionPassive)) {
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
