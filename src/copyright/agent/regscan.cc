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
  std::string pattern;
  rcp.getRegexValue(_identity, _type).toUTF8String(pattern);
  _reg = rx::regex(pattern, rx::regex_constants::icase);
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
  std::string pattern;
  rcp.getRegexValue(_identity, _type).toUTF8String(pattern);
  _reg = rx::regex(pattern, rx::regex_constants::icase);
}

/**
 * \brief Scan a string using regex defined during initialization
 *
 * Converts the UnicodeString to UTF-8 and uses boost::regex for performance.
 * The byte offsets from regex matches are then converted to UChar16 offsets
 * so positions stored in the database are consistent with the ICU representation.
 *
 * \param[in]  s       UnicodeString to scan
 * \param[out] results List of match results (positions in UChar16 offsets)
 */
void regexScanner::ScanString(const icu::UnicodeString& s, list<match>& results) const
{
  std::string utf8;
  s.toUTF8String(utf8);

  const unsigned char* utf8Buf =
    reinterpret_cast<const unsigned char*>(utf8.c_str());

  auto pos = utf8.cbegin();
  auto const end = utf8.cend();

  while (pos != end)
  {
    rx::smatch res;
    if (rx::regex_search(pos, end, res, _reg))
    {
      // Compute byte offsets relative to start of utf8 string
      size_t byteMatchStart = static_cast<size_t>(
        res.position(_index) + std::distance(utf8.cbegin(), pos));
      size_t byteMatchEnd = byteMatchStart +
        static_cast<size_t>(res.length(_index));

      // Convert byte offsets to UChar16 offsets
      int ucharStart = static_cast<int>(
        fo_utf8ByteLenToUChar16Len(utf8Buf, byteMatchStart));
      int ucharEnd = static_cast<int>(
        fo_utf8ByteLenToUChar16Len(utf8Buf, byteMatchEnd));

      results.push_back(match(ucharStart, ucharEnd, _type));
      pos = res[0].second;
    }
    else
      break;
  }
}
