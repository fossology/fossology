/*
 * Copyright (C) 2019-2020, Siemens AG
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

#include <boost/format.hpp>
#include <boost/assign/list_of.hpp>
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>
#include "atarashiwrapper.hpp"

using namespace std;
using namespace boost;
using namespace boost::assign;

namespace CPPUNIT_NS
{
  template <>
  struct assertion_traits<LicenseMatch>
  {
    static bool equal(const LicenseMatch& m1, const LicenseMatch& m2)
    {
      return m1.getLicenseName() == m2.getLicenseName() && m1.getPercentage() == m2.getPercentage();
    }

    static string toString(const LicenseMatch& m)
    {
      boost::format format("LicenseMatch(licenseName=\"%s\", percentage=\"%u\")");
      return str(format % m.getLicenseName() % m.getPercentage());
    }
  };
};

class AtarashiWrapperTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(AtarashiWrapperTest);
  // CPPUNIT_TEST(test_extractLicensesFromAtarashiResult);
  // CPPUNIT_TEST(test_extractLicensePartFromAtarashiResult);
  // CPPUNIT_TEST(test_splitLicensePart);
  // CPPUNIT_TEST(test_createMatches);
  // CPPUNIT_TEST(test_mapLicenseFromAtarashiToFossology);
  CPPUNIT_TEST_SUITE_END();

public:
  void test_extractLicensesFromAtarashiResult()
  {
    string atarashiResult("filename;UNKNOWN,LGPLv3+;more;fields\n");

    vector<LicenseMatch> licenses = extractLicensesFromAtarashiResult(atarashiResult);

    CPPUNIT_ASSERT_EQUAL(2L, (long) licenses.size());
    // CPPUNIT_ASSERT_EQUAL(string("UNKNOWN"), licenses[0]);
    // CPPUNIT_ASSERT_EQUAL(string("LGPLv3+"), licenses[1]);
  }

  // void test_extractLicensePartFromAtarashiResult()
  // {
  //   string licensePart;

  //   // valid output - single line w/ additional fields
  //   licensePart = extractLicensePartFromAtarashiResult("filename;license1,license2;more;fields\n");
  //   CPPUNIT_ASSERT_EQUAL(string("license1,license2"), licensePart);

  //   // valid output - single line w/o additional fields
  //   licensePart = extractLicensePartFromAtarashiResult("filename;NONE\n");
  //   CPPUNIT_ASSERT_EQUAL(string("NONE"), licensePart);

  //   // invalid output - no output at all
  //   licensePart = extractLicensePartFromAtarashiResult("");
  //   CPPUNIT_ASSERT_EQUAL(string(""), licensePart);

  //   // invalid output - only the filename
  //   licensePart = extractLicensePartFromAtarashiResult("filename;\n");
  //   CPPUNIT_ASSERT_EQUAL(string(""), licensePart);

  //   // invalid output - no license
  //   licensePart = extractLicensePartFromAtarashiResult("filename;;more;fields\n");
  //   CPPUNIT_ASSERT_EQUAL(string(""), licensePart);

  //   // invalid output - multiple lines
  //   licensePart = extractLicensePartFromAtarashiResult("filename;license;more;fields\nanother line\n");
  //   CPPUNIT_ASSERT_EQUAL(string("license"), licensePart);
  // }

  // void test_splitLicensePart()
  // {
  //   vector<string> licenses;

  //   // no license
  //   licenses = splitLicensePart("");
  //   CPPUNIT_ASSERT_EQUAL(0L, (long) licenses.size());

  //   // single license
  //   licenses = splitLicensePart("NONE");
  //   CPPUNIT_ASSERT_EQUAL(1L, (long) licenses.size());
  //   CPPUNIT_ASSERT_EQUAL(string("NONE"), licenses[0]);

  //   // multiple licenses
  //   licenses = splitLicensePart("LGPLv3+,Apachev1.0");
  //   CPPUNIT_ASSERT_EQUAL(2L, (long) licenses.size());
  //   CPPUNIT_ASSERT_EQUAL(string("LGPLv3+"), licenses[0]);
  //   CPPUNIT_ASSERT_EQUAL(string("Apachev1.0"), licenses[1]);
  // }

  // void test_createMatches()
  // {
  //   vector<LicenseMatch> matches;

  //   // special case: NONE should have a percentage of 0
  //   matches = createMatches(list_of("NONE"));
  //   CPPUNIT_ASSERT_EQUAL(1L, (long) matches.size());
  //   CPPUNIT_ASSERT_EQUAL(LicenseMatch("No_license_found", 0), matches[0]);

  //   // special case: UNKNOWN should have a percentage of 0
  //   matches = createMatches(list_of("UNKNOWN"));
  //   CPPUNIT_ASSERT_EQUAL(1L, (long) matches.size());
  //   CPPUNIT_ASSERT_EQUAL(LicenseMatch("UnclassifiedLicense", 0), matches[0]);

  //   // normal case: a known license should have a percentage of 100
  //   matches = createMatches(list_of("LGPLv3+")("Apachev1.0"));
  //   CPPUNIT_ASSERT_EQUAL(2L, (long) matches.size());
  //   CPPUNIT_ASSERT_EQUAL(LicenseMatch("LGPL-3.0+", 100), matches[0]);
  //   CPPUNIT_ASSERT_EQUAL(LicenseMatch("Apache-1.0", 100), matches[1]);
  // }

  // void test_mapLicenseFromAtarashiToFossology()
  // {
  //   // mapping: special cases
  //   CPPUNIT_ASSERT_EQUAL(string("No_license_found"), mapLicenseFromAtarashiToFossology(string("NONE")));
  //   CPPUNIT_ASSERT_EQUAL(string("UnclassifiedLicense"), mapLicenseFromAtarashiToFossology(string("UNKNOWN")));

  //   // mapping: input = output
  //   CPPUNIT_ASSERT_EQUAL(string(""), mapLicenseFromAtarashiToFossology(string("")));
  //   CPPUNIT_ASSERT_EQUAL(string("something"), mapLicenseFromAtarashiToFossology(string("something")));
  // };
};

CPPUNIT_TEST_SUITE_REGISTRATION(AtarashiWrapperTest);
