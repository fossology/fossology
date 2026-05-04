/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "OjoAgent.hpp"

using namespace std;

/**
 * Default constructor for OjoAgent.
 *
 * Also initializes the regex.
 */
OjoAgent::OjoAgent() :
    regLicenseList(
        boost::make_u32regex(SPDX_LICENSE_LIST, boost::regex_constants::icase)),
    regLicenseName(
        boost::make_u32regex(SPDX_LICENSE_NAMES, boost::regex_constants::icase)),
    regDualLicense(
        boost::make_u32regex(SPDX_DUAL_LICENSE, boost::regex_constants::icase))
{
}

/**
 * @brief Read a file into an icu::UnicodeString.
 *
 * Reads the file as raw bytes and interprets it as UTF-8, converting to a
 * UnicodeString so that subsequent regex operations work on Unicode code
 * points rather than raw bytes.
 * @param filePath Path to the file
 * @param out      Output UnicodeString
 * @return True on success, false on failure
 */
bool OjoAgent::readFileToUnicodeString(const string &filePath,
  icu::UnicodeString &out) const
{
  std::ifstream stream(filePath, std::ios::binary);
  if (!stream)
    return false;
  std::ostringstream sstr;
  sstr << stream.rdbuf();
  std::string content = sstr.str();
  out = icu::UnicodeString::fromUTF8(content);
  return !stream.fail();
}

/**
 * Scan a single file (when running from scheduler).
 * @param filePath        The file to be scanned.
 * @param databaseHandler Database handler to be used.
 * @param groupId         Group running the scan
 * @param userId          User running the scan
 * @return List of matches found.
 * @sa OjoAgent::scanString()
 * @sa OjoAgent::filterMatches()
 * @sa OjoAgent::findLicenseId()
 * @throws std::runtime_error() Throws runtime error if the file can not be
 * read with the file path in description.
 */
vector<ojomatch> OjoAgent::processFile(const string &filePath,
  OjosDatabaseHandler &databaseHandler, const int groupId, const int userId)
{
  icu::UnicodeString fileContent;
  if (!readFileToUnicodeString(filePath, fileContent))
  {
    throw std::runtime_error(filePath);
  }

  vector<ojomatch> licenseList;
  vector<ojomatch> licenseNames;

  scanString(fileContent, regLicenseList, licenseList, 0, false);
  for (auto m : licenseList)
  {
    icu::UnicodeString contentUnicode =
      icu::UnicodeString::fromUTF8(m.content);
    scanString(contentUnicode, regLicenseName, licenseNames, m.start, false);
    scanString(contentUnicode, regDualLicense, licenseNames, m.start, true);
  }

  findLicenseId(licenseNames, databaseHandler, groupId, userId);
  filterMatches(licenseNames);

  return licenseNames;
}

/**
 * Scan a single file (when running from CLI).
 *
 * This function can not interact with DB.
 * @param filePath File to be scanned
 * @return List of matches.
 */
vector<ojomatch> OjoAgent::processFile(const string &filePath)
{
  icu::UnicodeString fileContent;
  if (!readFileToUnicodeString(filePath, fileContent))
  {
    throw std::runtime_error(filePath);
  }

  vector<ojomatch> licenseList;
  vector<ojomatch> licenseNames;

  scanString(fileContent, regLicenseList, licenseList, 0, false);
  for (auto m : licenseList)
  {
    icu::UnicodeString contentUnicode =
      icu::UnicodeString::fromUTF8(m.content);
    scanString(contentUnicode, regLicenseName, licenseNames, m.start, false);
    scanString(contentUnicode, regDualLicense, licenseNames, m.start, true);
  }

  // Remove duplicate matches for CLI run
  vector<ojomatch>::iterator uniqueListIt = std::unique(licenseNames.begin(),
    licenseNames.end());
  licenseNames.resize(std::distance(licenseNames.begin(), uniqueListIt));

  return licenseNames;
}

/**
 * Scan a UnicodeString using a u32regex and create matches.
 *
 * All positions returned are UTF-16 code unit (UChar16) offsets, which is
 * the native unit of icu::UnicodeString. This ensures that multi-byte UTF-8
 * characters are counted correctly and that the offsets stored in the database
 * correspond to character positions, not raw byte positions.
 *
 * @param text        UnicodeString to be scanned
 * @param reg         u32regex to be used
 * @param[out] result The match list.
 * @param offset      UChar16 offset to add for each match (used for nested scans)
 * @param isDualTest  True if testing for Dual-license, false otherwise
 */
void OjoAgent::scanString(const icu::UnicodeString &text, const boost::u32regex &reg,
    vector<ojomatch> &result, unsigned int offset, bool isDualTest)
{
  const UChar* begin = text.getBuffer();
  const UChar* end   = begin + text.length();
  const UChar* pos   = begin;

  while (pos != end)
  {
    boost::u16match res;
    if (boost::u32regex_search(pos, end, res, reg))
    {
      string content;
      if (isDualTest)
      {
        content = "Dual-license";
      }
      else
      {
        // toUTF8String appends, so start with an empty string
        icu::UnicodeString matched(res[1].first,
          static_cast<int32_t>(res[1].second - res[1].first));
        matched.toUTF8String(content);
      }
      result.push_back(
          ojomatch(offset + res.position(1),
              offset + res.position(1) + res.length(1),
              res.length(1),
              content));
      pos = res[0].second;
      offset += res.position() + res.length();
    }
    else
    {
      // No match found
      break;
    }
  }
}

/**
 * Filter the matches list and remove entries with license id less than 1.
 * @param[in,out] matches List of matches to be filtered
 */
void OjoAgent::filterMatches(vector<ojomatch> &matches)
{
  // Remvoe entries with license_fk < 1
  matches.erase(
    std::remove_if(matches.begin(), matches.end(), [](ojomatch match)
    { return match.license_fk <= 0;}), matches.end());
}

/**
 * Update the license id for each match entry
 * @param[in,out] matches List of matches to be updated
 * @param databaseHandler Database handler to be used
 * @param groupId         Group running the scan
 * @param userId          User running the scan
 */
void OjoAgent::findLicenseId(vector<ojomatch> &matches,
  OjosDatabaseHandler &databaseHandler, const int groupId, const int userId)
{
  // Update license_fk
  for (size_t i = 0; i < matches.size(); ++i)
  {
    matches[i].license_fk = databaseHandler.getLicenseIdForName(
      matches[i].content, groupId, userId);
  }
}
