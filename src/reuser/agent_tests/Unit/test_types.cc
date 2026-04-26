/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @file
 * @brief Unit tests for ReuserDatabaseHandler data structures and types.
 *
 * Tests the value types (ReuserTypes, ReuserState) independently of
 * any database connection.
 */

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "ReuserState.hpp"
#include "ReuserTypes.hpp"

class ReuserTypesTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ReuserTypesTest);
  CPPUNIT_TEST(testReuserStateMutation);
  CPPUNIT_TEST(testReuseModeFlags);
  CPPUNIT_TEST(testItemTreeBoundsDefaults);
  CPPUNIT_TEST_SUITE_END();

protected:
  /**
   * @brief ReuserState stores and mutates the agent id correctly.
   *
   * Verifies that getAgentId returns the value passed to the constructor
   * and that setAgentId updates it.
   */
  void testReuserStateMutation()
  {
    ReuserState state(42);
    CPPUNIT_ASSERT_EQUAL(42, state.getAgentId());
    state.setAgentId(99);
    CPPUNIT_ASSERT_EQUAL(99, state.getAgentId());
  }

  /**
   * @brief Reuse mode flag constants are distinct and combine correctly.
   *
   * Guards against accidental bit-overlap between REUSE_ENHANCED, REUSE_MAIN,
   * REUSE_CONF and REUSE_COPYRIGHT, and verifies that OR-combining all flags
   * sets every individual bit.
   */
  void testReuseModeFlags()
  {
    // Flags must be distinct powers-of-two (no overlap).
    CPPUNIT_ASSERT((REUSE_ENHANCED  & REUSE_MAIN)      == 0);
    CPPUNIT_ASSERT((REUSE_ENHANCED  & REUSE_CONF)      == 0);
    CPPUNIT_ASSERT((REUSE_ENHANCED  & REUSE_COPYRIGHT) == 0);
    CPPUNIT_ASSERT((REUSE_MAIN      & REUSE_CONF)      == 0);
    CPPUNIT_ASSERT((REUSE_MAIN      & REUSE_COPYRIGHT) == 0);
    CPPUNIT_ASSERT((REUSE_CONF      & REUSE_COPYRIGHT) == 0);

    // Combined mode works as expected.
    int combined = REUSE_ENHANCED | REUSE_MAIN | REUSE_CONF | REUSE_COPYRIGHT;
    CPPUNIT_ASSERT((combined & REUSE_ENHANCED)  != 0);
    CPPUNIT_ASSERT((combined & REUSE_MAIN)      != 0);
    CPPUNIT_ASSERT((combined & REUSE_CONF)      != 0);
    CPPUNIT_ASSERT((combined & REUSE_COPYRIGHT) != 0);
  }

  /**
   * @brief ItemTreeBounds is zero-initialised when value-initialised.
   *
   * Ensures that a default-constructed ItemTreeBounds has all numeric
   * members set to 0 so that callers can safely detect an uninitialised
   * struct.
   */
  void testItemTreeBoundsDefaults()
  {
    ItemTreeBounds b{};
    CPPUNIT_ASSERT_EQUAL(0, b.uploadtree_pk);
    CPPUNIT_ASSERT_EQUAL(0, b.lft);
    CPPUNIT_ASSERT_EQUAL(0, b.rgt);
    CPPUNIT_ASSERT_EQUAL(0, b.upload_fk);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ReuserTypesTest);
