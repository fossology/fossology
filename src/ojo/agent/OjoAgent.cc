/*
 * Copyright (C) 2019, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
        boost::regex(SPDX_LICENSE_LIST, boost::regex_constants::icase)),
    regLicenseName(
        boost::regex(SPDX_LICENSE_NAMES, boost::regex_constants::icase)),
    regDualLicense(
        boost::regex(SPDX_DUAL_LICENSE, boost::regex_constants::icase))
{
}

/**
 * Scan a single file (when running from scheduler).
 * @param filePath        The file to be scanned.
 * @param databaseHandler Database handler to be used.
 * @return List of matches found.
 * @sa OjoAgent::scanString()
 * @sa OjoAgent::filterMatches()
 * @sa OjoAgent::findLicenseId()
 * @throws std::runtime_error() Throws runtime error if the file can not be
 * read with the file path in description.
 */
vector<ojomatch> OjoAgent::processFile(const string &filePath,
  OjosDatabaseHandler &databaseHandler)
{
  ifstream stream(filePath);
  std::stringstream sstr;
  sstr << stream.rdbuf();
  if (stream.fail())
  {
    throw std::runtime_error(filePath);
  }
  stream.close();
  const string fileContent = sstr.str();
  vector<ojomatch> licenseList;
  vector<ojomatch> licenseNames;

  scanString(fileContent, regLicenseList, licenseList, 0, false);
  for (auto m : licenseList)
  {
    scanString(m.content, regLicenseName, licenseNames, m.start, false);
    scanString(m.content, regDualLicense, licenseNames, m.start, true);
  }

  findLicenseId(licenseNames, databaseHandler);
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
  ifstream stream(filePath);
  std::stringstream sstr;
  sstr << stream.rdbuf();
  if (stream.fail())
  {
    throw std::runtime_error(filePath);
  }
  stream.close();
  const string fileContent = sstr.str();
  vector<ojomatch> licenseList;
  vector<ojomatch> licenseNames;

  scanString(fileContent, regLicenseList, licenseList, 0, false);
  for (auto m : licenseList)
  {
    scanString(m.content, regLicenseName, licenseNames, m.start, false);
    scanString(m.content, regDualLicense, licenseNames, m.start, true);
  }

  // Remove duplicate matches for CLI run
  vector<ojomatch>::iterator uniqueListIt = std::unique(licenseNames.begin(),
    licenseNames.end());
  licenseNames.resize(std::distance(licenseNames.begin(), uniqueListIt));

  return licenseNames;
}

/**
 * Scan a string based using a regex and create matches.
 * @param text        String to be scanned
 * @param reg         Regex to be used
 * @param[out] result The match list.
 * @param offset      The offset to be added for each match
 * @param isDualTest  True if testing for Dual-license, false otherwise
 */
void OjoAgent::scanString(const string &text, boost::regex reg,
    vector<ojomatch> &result, unsigned int offset, bool isDualTest)
{
  string::const_iterator end = text.end();
  string::const_iterator pos = text.begin();

  while (pos != end)
  {
    // Find next match
    boost::smatch res;
    if (boost::regex_search(pos, end, res, reg))
    {
      string content = "Dual-license";
      if (! isDualTest)
      {
        content = res[1].str();
      }
      // Found match
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
 */
void OjoAgent::findLicenseId(vector<ojomatch> &matches,
  OjosDatabaseHandler &databaseHandler)
{
  // Update license_fk
  for (size_t i = 0; i < matches.size(); ++i)
  {
    matches[i].license_fk = databaseHandler.getLicenseIdForName(
      matches[i].content);
  }
}
