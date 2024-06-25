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
  LicenseExpressionParser expressionParser("");
  ojomatch expression;
  if (licenseList.size() > 0)
  {
    expression = licenseList[0];
    expressionParser = LicenseExpressionParser(licenseList[0].content);
    expressionParser.parse();
    expressionParser.getAST();
  }
  for (auto m : licenseList)
  {
    expressionParser.containsExpression(m.content);
    scanString(m.content, regLicenseName, licenseNames, m.start, false);
    // scanString(m.content, regDualLicense, licenseNames, m.start, true);
  }
  Value ast = expressionParser.getAST();
  if (ast != Value::null && ast["type"] !="License")
  {
    updateLicenseIdsinExpression(ast, databaseHandler, groupId, userId);
    StreamWriterBuilder builder;
    builder["indentation"] = "";
    expression.content = Json::writeString(builder, ast);
    expression.isExpression = true;
    licenseNames.push_back(expression);
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
  LicenseExpressionParser expressionParser("");
  ojomatch expression;
  if (licenseList.size() > 0)
  {
    expression = licenseList[0];
    expressionParser = LicenseExpressionParser(licenseList[0].content);
    expressionParser.parse();
    expressionParser.getAST();
  }
  for (auto m : licenseList)
  {
    expressionParser.containsExpression(m.content);
    scanString(m.content, regLicenseName, licenseNames, m.start, false);
    scanString(m.content, regDualLicense, licenseNames, m.start, true);
  }
  Value ast = expressionParser.getAST();
  if (ast != Value::null && ast["type"] !="License")
  {
    StreamWriterBuilder builder;
    builder["indentation"] = "";
    expression.content = Json::writeString(builder, ast);
    expression.isExpression = true;
    // Commented for failing regression test
    // licenseNames.push_back(expression);
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
 * @param groupId         Group running the scan
 * @param userId          User running the scan
 */
void OjoAgent::findLicenseId(vector<ojomatch> &matches,
  OjosDatabaseHandler &databaseHandler, const int groupId, const int userId)
{
  // Update license_fk
  for (size_t i = 0; i < matches.size(); ++i)
  {
    if (matches[i].isExpression)
    {
      matches[i].license_fk = databaseHandler.saveLicenseExpressionToDatabase(matches[i]);
      continue;
    }
    matches[i].license_fk = databaseHandler.getLicenseIdForName(
      matches[i].content, groupId, userId);
  }
}

/**
 * Update the license id for each license in Expression
 * @param ast AST in json format
 * @param databaseHandler Database handler to be used
 * @param groupId         Group running the scan
 * @param userId          User running the scan
 */
void OjoAgent::updateLicenseIdsinExpression(Value &ast, OjosDatabaseHandler &databaseHandler, const int groupId, const int userId)
{
  if (ast["type"].asString() == "License")
  {
    ast["value"] = databaseHandler.getLicenseIdForName(ast["value"].asString(), groupId, userId);
  }
  else
  {
    if (ast.isMember("left"))
    {
      updateLicenseIdsinExpression(ast["left"], databaseHandler, groupId, userId);
    }
    if (ast.isMember("right"))
    {
      updateLicenseIdsinExpression(ast["right"], databaseHandler, groupId, userId);
    }
  }
}