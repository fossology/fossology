/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
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
  CPPUNIT_TEST (badNameTest);
  CPPUNIT_TEST (regTestSpecialEnd);

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
    // REUSE-IgnoreStart
    std::string content = "SPDX-License-Identifier: " + gplLicense + " AND "
        + lgplLicense;
    // REUSE-IgnoreStart
    boost::regex listRegex(SPDX_LICENSE_LIST, boost::regex_constants::icase);
    boost::regex nameRegex(SPDX_LICENSE_NAMES, boost::regex_constants::icase);

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
    CPPUNIT_ASSERT_EQUAL(expectedNos, actualNos);
    // Check if the result contains the expected string
    CPPUNIT_ASSERT(
      std::find(licensesFound.begin(), licensesFound.end(), gplLicense)
        != licensesFound.end());
    CPPUNIT_ASSERT(
      std::find(licensesFound.begin(), licensesFound.end(), lgplLicense)
        != licensesFound.end());
  };

  /**
   * \brief Test regex on a string with bad identifier
   *
   * \test
   * -# Create a test SPDX identifier string with bad license identifier
   * -# Load the regex patterns
   * -# Run the regex on the string
   * -# Check the actual number of matches against expected result
   * -# Check the actual findings matches the expected licenses
   */
  void badNameTest (void) {

    const std::string gplLicense = "GPL-2.0";
    const std::string badLicense = "AB";
    // REUSE-IgnoreStart
    std::string content = "SPDX-License-Identifier: " + gplLicense + " AND "
        + badLicense;
    // REUSE-IgnoreStart
    boost::regex listRegex(SPDX_LICENSE_LIST, boost::regex_constants::icase);
    boost::regex nameRegex(SPDX_LICENSE_NAMES, boost::regex_constants::icase);

    std::string::const_iterator begin = content.begin();
    std::string::const_iterator end = content.end();
    boost::match_results<std::string::const_iterator> what;

    string licenseList;
    boost::regex_search(begin, end, what, listRegex);
    licenseList = what[1].str();

    // Check if only correct license is found
    CPPUNIT_ASSERT_EQUAL(gplLicense, licenseList);

    // Find the actual license in the list
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

    size_t expectedNos = 1;
    size_t actualNos = licensesFound.size();
    // Check if only 1 license is found
    CPPUNIT_ASSERT_EQUAL(expectedNos, actualNos);
    // Check if the result contains the expected string
    CPPUNIT_ASSERT(
      std::find(licensesFound.begin(), licensesFound.end(), gplLicense)
        != licensesFound.end());
    // Check if the result does not contain the bad string
    CPPUNIT_ASSERT(
      std::find(licensesFound.begin(), licensesFound.end(), badLicense)
        == licensesFound.end());
  };

  /**
   * \brief Test regex on a special test string
   *
   * \test
   * -# Create a test SPDX identifier string with special characters at end
   * -# Load the regex patterns
   * -# Run the regex on the string
   * -# Check the actual number of matches against expected result
   * -# Check the actual findings matches the expected licenses
   */
  void regTestSpecialEnd (void) {

    const std::string gplLicense = "GPL-2.0-only";
    const std::string lgplLicense = "LGPL-2.1-or-later";
    const std::string mitLicense = "MIT";
    const std::string mplLicense = "MPL-1.1+";
    // REUSE-IgnoreStart
    std::string content = "SPDX-License-Identifier: (" + gplLicense + " AND "
        + lgplLicense + ") OR " + mplLicense + " AND " + mitLicense + ".";
    // REUSE-IgnoreStart
    boost::regex listRegex(SPDX_LICENSE_LIST, boost::regex_constants::icase);
    boost::regex nameRegex(SPDX_LICENSE_NAMES, boost::regex_constants::icase);

    std::string::const_iterator begin = content.begin();
    std::string::const_iterator end = content.end();
    boost::match_results<std::string::const_iterator> what;

    string licenseList;
    boost::regex_search(begin, end, what, listRegex);
    licenseList = what[1].str();

    // Check if the correct license list is found
    CPPUNIT_ASSERT_EQUAL("(" + gplLicense + " AND " + lgplLicense + ") OR " +
      mplLicense + " AND " + mitLicense + ".", licenseList);

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

    size_t expectedNos = 4;
    size_t actualNos = licensesFound.size();
    // Check if 4 licenses are found
    CPPUNIT_ASSERT_EQUAL(expectedNos, actualNos);
    // Check if the result contains the expected string
    CPPUNIT_ASSERT(
      std::find(licensesFound.begin(), licensesFound.end(), gplLicense)
        != licensesFound.end());
    CPPUNIT_ASSERT(
      std::find(licensesFound.begin(), licensesFound.end(), lgplLicense)
        != licensesFound.end());
    CPPUNIT_ASSERT(
      std::find(licensesFound.begin(), licensesFound.end(), mitLicense)
        != licensesFound.end());
    CPPUNIT_ASSERT(
      std::find(licensesFound.begin(), licensesFound.end(), mplLicense)
        != licensesFound.end());
  };

};

CPPUNIT_TEST_SUITE_REGISTRATION( regexTest );
