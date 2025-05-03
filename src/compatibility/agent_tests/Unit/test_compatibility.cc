/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file test_compatibility.cc
 * \brief Test the compatibility logs
 */
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "CompatibilityUtils.hpp"

using namespace std;

/**
 * \class TestCompatibility
 * \brief Test compatibility functions
 */
class TestCompatibility : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(TestCompatibility);
  CPPUNIT_TEST(test_license_compatible);
  CPPUNIT_TEST_SUITE_END();
protected:
  void test_license_compatible();
} ;

/**
 * \brief Test are_licenses_compatible()
 * \test
 * -# Load test license compatiblity rules
 * -# Check if two license types mentioned directly works for true
 * -# Check if two license types mentioned directly works for false
 * -# Check if two license names mentioned directly takes precidence even if
 *    their types are also mentioned.
 * -# Check if license name and type check works.
 * -# Check if license name and type check works in reverse order..
 * -# Check if default rule works.
 * \see mainLicenseToSet
 */
void TestCompatibility::test_license_compatible()
{
  const auto test_rule_list = initialize_rule_list(
      TESTDATADIR "/comp-rules-test.yaml");

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Permissive licenses should be compatible",
    true, are_licenses_compatible("MIT", "Permissive", "BSD-3-Clause",
      "Permissive", test_rule_list));

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Strong copyleft and weak copyleft licenses "
    "should not be compatible", false,
    are_licenses_compatible("Sleepycat", "Strong Copyleft", "Apache-2.0",
      "Weak Copyleft", test_rule_list));

  CPPUNIT_ASSERT_EQUAL_MESSAGE("GPL and Apache should not be compatible "
    "explicitly even with wrong types", false,
    are_licenses_compatible("GPL-2.0-only", "Permissive", "Apache-2.0",
      "Permissive", test_rule_list));

  CPPUNIT_ASSERT_EQUAL_MESSAGE("GPL and MIT should be compatible explicitly",
    true, are_licenses_compatible("GPL-2.0-only", "Strong Copyleft", "MIT",
      "Permissive", test_rule_list));

  CPPUNIT_ASSERT_EQUAL_MESSAGE("Sleepycat and MIT should not be compatible",
    false, are_licenses_compatible("Sleepycat", "Strong Copyleft", "MIT",
      "Permissive", test_rule_list));

  CPPUNIT_ASSERT_EQUAL_MESSAGE("License name and type in reverse order should "
    "be compatible", true,
    are_licenses_compatible("BSD-3-Clause", "Permissive", "GPL-2.0-only",
      "Do Not Care", test_rule_list));

  CPPUNIT_ASSERT_EQUAL_MESSAGE("default rule should be false",
    false, are_licenses_compatible("Custom license", "My Type",
      "What license?", "Another type", test_rule_list));
}

CPPUNIT_TEST_SUITE_REGISTRATION( TestCompatibility );
