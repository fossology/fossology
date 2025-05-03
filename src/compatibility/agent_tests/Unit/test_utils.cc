/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file test_utils.cc
 * \brief Test the utility functions
 */
#include "CompatibilityUtils.hpp"

using namespace std;

ostream& operator<<(ostream& out, const std::set<std::string>& s)
{
  bool first = true;
  out << "<";
  for (auto& a: s)
  {
    if (!first)
    {
      out << ", " << a;
    }
    else
    {
      first = false;
      out << a;
    }
  }
  out << ">";
  return out;
}

ostream& operator<<(ostream& out,
                    const std::tuple<
                      std::string, std::string, std::string, std::string>& t)
{
  out << "<" << std::get<0>(t) << ", " << std::get<1>(t) << ", "
    << std::get<2>(t) << ", " << std::get<3>(t) << ">";
  return out;
}

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

/**
 * \class TestUtility
 * \brief Test helper/utility functions
 */
class TestUtility : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(TestUtility);
  CPPUNIT_TEST(testLicensesToSet);
  CPPUNIT_TEST(testRuleLoader);
  CPPUNIT_TEST_SUITE_END();
protected:
  void testLicensesToSet();
  void testRuleLoader();
} ;

/**
 * \brief Test utility function mainLicenseToSet
 * \test
 * -# Create sample license string concatenated with " AND "
 * -# Create expected set with unique licenses from the string
 * -# Compare expected set with output from mainLicenseToSet()
 * \see mainLicenseToSet
 */
void TestUtility::testLicensesToSet()
{
  const std::string licenseNormalCase = "GPL-2.0-only AND LGPL-2.1-or-later";
  const std::set<std::string> licenseSetNormalCase = {"GPL-2.0-only", "LGPL-2.1-or-later"};

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Set should contain 2 licenses",
    licenseSetNormalCase, mainLicenseToSet(licenseNormalCase));

  const std::string licenseDuplicate = "GPL-2.0-only AND GPL-2.0-only";
  const std::set<std::string> licenseSetDuplicate = {"GPL-2.0-only"};

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Set should not contain duplicates",
    licenseSetDuplicate, mainLicenseToSet(licenseDuplicate));

  const std::string licenseInvalidEnd = "GPL-2.0-only AND GPL-2.0-only AND ";
  const std::set<std::string> licenseSetInvalidEnd = {"GPL-2.0-only"};

  CPPUNIT_ASSERT_EQUAL_MESSAGE("There should be no empty string in set",
    licenseSetInvalidEnd, mainLicenseToSet(licenseInvalidEnd));

  const std::string licenseEmpty = "";
  const std::set<std::string> licenseSetEmpty = {};

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Empty licenseList should return empty set",
    licenseSetEmpty, mainLicenseToSet(licenseEmpty));
}

void TestUtility::testRuleLoader()
{
  std::string const test_yaml_loc = TESTDATADIR "/comp-rules-test.yaml";
  auto const rule_list = initialize_rule_list(test_yaml_loc);

  const std::tuple<string, string, string, string> known_gpl_tuple =
    make_tuple("GPL-2.0-only", "", "LGPL-2.1-or-later", "");

  auto result_check = rule_list.find(known_gpl_tuple);

  CPPUNIT_ASSERT_MESSAGE("Unable to find tuple with name from YAML",
    result_check != rule_list.end());

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Should find a known name tuple from YAML",
    known_gpl_tuple, result_check->first);
  CPPUNIT_ASSERT_EQUAL_MESSAGE("Invalid compatibility for name tuple from YAML",
    true, result_check->second);

  const std::tuple<string, string, string, string> known_type_tuple =
    make_tuple("", "Strong Copyleft", "", "Weak Copyleft");

  result_check = rule_list.find(known_type_tuple);

  CPPUNIT_ASSERT_MESSAGE("Unable to find tuple with type from YAML",
    result_check != rule_list.end());

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Should find a known type tuple from YAML",
    known_type_tuple, result_check->first);
  CPPUNIT_ASSERT_EQUAL_MESSAGE("Invalid compatibility for type tuple from YAML",
    false, result_check->second);

  const std::tuple<string, string, string, string> default_tuple =
    make_tuple("~", "~", "~", "~");

  result_check = rule_list.find(default_tuple);

  CPPUNIT_ASSERT_MESSAGE("Unable to find default rule from YAML",
    result_check != rule_list.end());

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Should find the default tuple from YAML",
    default_tuple, result_check->first);
  CPPUNIT_ASSERT_EQUAL_MESSAGE("Invalid compatibility for default tuple from YAML",
    false, result_check->second);
}

CPPUNIT_TEST_SUITE_REGISTRATION( TestUtility );
