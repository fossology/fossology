/*********************************************************************
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*********************************************************************/
/**
 * \file test_regex.cc
 * \brief Test for regex accuracy
 */
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>
#include <boost/regex.hpp>

#include "ojoregex.hpp"

using namespace std;

/**
 * \class regexTest
 * \brief Test fixture to test regex accuracy
 */
class regexTest : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (regexTest);
  CPPUNIT_TEST (regTest);

  CPPUNIT_TEST_SUITE_END ();

protected:
  /**
   * \brief Test regex on a test string
   *
   * \test
   * -# Create a test SPDX identifier string
   * -# Load the regex patterns
   * -# Run the regex on the string
   * -# Check the actual number of matches against expected result
   * -# Check the actual findings matches the expected licenses
   */
  void regTest (void) {

    const std::string gplLicense = "GPL-2.0";
    const std::string lgplLicense = "LGPL-2.1+";
    std::string content = "SPDX-License-Identifier: " + gplLicense + " AND " + lgplLicense;
    boost::regex listRegex (SPDX_LICENSE_LIST, boost::regex_constants::icase);
    boost::regex nameRegex (SPDX_LICENSE_NAMES, boost::regex_constants::icase);

    std::string::const_iterator begin = content.begin();
    std::string::const_iterator end = content.end();
    boost::match_results<std::string::const_iterator> what;

    string licenseList;
    boost::regex_search(begin, end, what, listRegex);
    licenseList = what[1].str();

    // Check if the correct license list is found
    CPPUNIT_ASSERT_EQUAL(gplLicense + " AND " + lgplLicense, licenseList);

    // Find the actual licenses in the list
    begin = licenseList.begin();
    end = licenseList.end();
    list<string> licensesFound;

    while (begin != end)
    {
      boost::smatch res;
      if (boost::regex_search(begin, end, res, nameRegex))
      {
        licensesFound.push_back(res[1].str());
        begin = res[0].second;
      }
      else
      {
        break;
      }
    }

    size_t expectedNos = 2;
    size_t actualNos = licensesFound.size();
    // Check if 2 licenses are found
    CPPUNIT_ASSERT_EQUAL (expectedNos, actualNos);
    // Check if the result contains the expected string
    CPPUNIT_ASSERT(std::find(licensesFound.begin(), licensesFound.end(), gplLicense) != licensesFound.end());
    CPPUNIT_ASSERT(std::find(licensesFound.begin(), licensesFound.end(), lgplLicense) != licensesFound.end());
  };

};

CPPUNIT_TEST_SUITE_REGISTRATION( regexTest );
