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
/**
 * @file
 * OjoAgent - the regex runner
 */
#ifndef SRC_OJO_AGENT_OJOAGENT_HPP_
#define SRC_OJO_AGENT_OJOAGENT_HPP_

#include <boost/regex.hpp>
#include <fstream>

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
};

#endif /* SRC_OJO_AGENT_OJOAGENT_HPP_ */
