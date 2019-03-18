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

#include "regex.hpp"


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
   * -# Create a test string
   * -# Create a regex pattern
   * -# Run the regex on the string
   * -# Check the actual number of matches against expected result
   */
  void regTest (void) {

    std::string content = "This is copy of a copyright statement similar to copyleft found in copying";
    rx::regex matchingRegex ("copy");

    std::string::const_iterator begin = content.begin();
    std::string::const_iterator end = content.end();
    rx::match_results<std::string::const_iterator> what;

    int nfound =0;
    while (rx::regex_search(begin, end, what,matchingRegex)) {
      nfound++;
      begin = what[0].second;
    }

    CPPUNIT_ASSERT_EQUAL (4, nfound);
  };



};

CPPUNIT_TEST_SUITE_REGISTRATION( regexTest );
