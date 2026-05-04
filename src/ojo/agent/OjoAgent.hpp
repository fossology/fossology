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

#include <boost/regex/icu.hpp>
#include <fstream>
#include <sstream>
#include <unicode/unistr.h>

#include "OjosDatabaseHandler.hpp"
#include "ojomatch.hpp"
#include "ojoregex.hpp"

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
     * @var boost::u32regex regLicenseList
     * Regex to find the list of licenses
     * @var boost::u32regex regLicenseName
     * Regex to find the license names from the license lists
     * @var boost::u32regex regDualLicense
     * Regex to find dual license strings
     */
    const boost::u32regex regLicenseList, regLicenseName, regDualLicense;
    bool readFileToUnicodeString(const std::string &filePath,
      icu::UnicodeString &out) const;
    void scanString(const icu::UnicodeString &text, const boost::u32regex &reg,
        std::vector<ojomatch> &result, unsigned int offset, bool isDualTest);
    void filterMatches(std::vector<ojomatch> &matches);
    void findLicenseId(std::vector<ojomatch> &matches,
      OjosDatabaseHandler &databaseHandler, const int groupId,
      const int userId);
};

#endif /* SRC_OJO_AGENT_OJOAGENT_HPP_ */
