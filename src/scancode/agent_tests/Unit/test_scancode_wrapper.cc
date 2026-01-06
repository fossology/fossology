/*
 SPDX-FileCopyrightText: © 2026 Nakshatra Sharma <nakshatrasharma2002@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

/**
 * Placeholder tests for scancode wrapper
 * TODO: Implement actual tests once wrapper interface is stable
 */
class ScancodeWrapperTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ScancodeWrapperTest);
  CPPUNIT_TEST(testBasicSetup);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testBasicSetup()
  {
    // Just checking that test framework works
    CPPUNIT_ASSERT(true);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ScancodeWrapperTest);

