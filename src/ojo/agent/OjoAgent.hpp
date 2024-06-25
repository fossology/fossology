/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * OjoAgent - the regex runner
 */
#ifndef SRC_OJO_AGENT_OJOAGENT_HPP_
#define SRC_OJO_AGENT_OJOAGENT_HPP_

#include <boost/regex.hpp>
#include <fstream>
#include <sstream>
#include <jsoncpp/json/json.h>

#include "OjosDatabaseHandler.hpp"
#include "licenseExpression.hpp"
#include "ojomatch.hpp"
#include "ojoregex.hpp"

using Json::Value;
using Json::StreamWriterBuilder;
using Json::String;

/**
 * @class OjoAgent
 * The OjoAgent class with various functions to scan a file.
 */
class OjoAgent
{
  public:
    OjoAgent();
    std::vector<ojomatch> processFile(const std::string &filePath,
      OjosDatabaseHandler &databaseHandler, const int groupId,
      const int userId);
    std::vector<ojomatch> processFile(const std::string &filePath);
  private:
    /**
     * @var boost::regex regLicenseList
     * Regex to find the list of licenses
     * @var boost::regex regLicenseName
     * Regex to find the license names from the license lists
     * @var boost::regex regDualLicense
     * Regex to find dual license strings
     */
    const boost::regex regLicenseList, regLicenseName, regDualLicense;
    void scanString(const std::string &text, boost::regex reg,
        std::vector<ojomatch> &result, unsigned int offset, bool isDualTest);
    void filterMatches(std::vector<ojomatch> &matches);
    void findLicenseId(std::vector<ojomatch> &matches,
      OjosDatabaseHandler &databaseHandler, const int groupId,
      const int userId);
    void updateLicenseIdsinExpression(Value &ast, OjosDatabaseHandler &databaseHandler, const int groupId, const int userId);
};

#endif /* SRC_OJO_AGENT_OJOAGENT_HPP_ */
