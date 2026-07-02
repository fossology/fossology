/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "EnhancedReuserState.hpp"
#include "EnhancedReuserTypes.hpp"

class EnhancedReuserTypesTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(EnhancedReuserTypesTest);
  CPPUNIT_TEST(testReuserStateMutation);
  CPPUNIT_TEST(testReuseEnhancedFlag);
  CPPUNIT_TEST(testItemTreeBoundsDefaults);
  CPPUNIT_TEST(testReuseTripleDefaults);
  CPPUNIT_TEST(testCommentBlockDefaults);
  CPPUNIT_TEST(testNirjasOutputDefaults);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testReuserStateMutation()
  {
    EnhancedReuserState state(42);
    CPPUNIT_ASSERT_EQUAL(42, state.getAgentId());
    state.setAgentId(99);
    CPPUNIT_ASSERT_EQUAL(99, state.getAgentId());
  }

  void testReuseEnhancedFlag()
  {
    CPPUNIT_ASSERT_EQUAL(2, REUSE_ENHANCED);
    CPPUNIT_ASSERT((REUSE_ENHANCED & 2) != 0);
    CPPUNIT_ASSERT((REUSE_ENHANCED & 1) == 0);
    CPPUNIT_ASSERT((REUSE_ENHANCED & 4) == 0);
  }

  void testItemTreeBoundsDefaults()
  {
    ItemTreeBounds b{};
    CPPUNIT_ASSERT_EQUAL(0, b.uploadtree_pk);
    CPPUNIT_ASSERT_EQUAL(0, b.lft);
    CPPUNIT_ASSERT_EQUAL(0, b.rgt);
    CPPUNIT_ASSERT_EQUAL(0, b.upload_fk);
    CPPUNIT_ASSERT(b.uploadTreeTableName.empty());
  }

  void testReuseTripleDefaults()
  {
    ReuseTriple t{};
    CPPUNIT_ASSERT_EQUAL(0, t.reusedUploadId);
    CPPUNIT_ASSERT_EQUAL(0, t.reusedGroupId);
    CPPUNIT_ASSERT_EQUAL(0, t.reuseMode);
  }

  void testCommentBlockDefaults()
  {
    CommentBlock cb{};
    CPPUNIT_ASSERT_EQUAL(0, cb.startLine);
    CPPUNIT_ASSERT_EQUAL(0, cb.endLine);
    CPPUNIT_ASSERT(cb.text.empty());
  }

  void testNirjasOutputDefaults()
  {
    NirjasOutput no{};
    CPPUNIT_ASSERT(no.filename.empty());
    CPPUNIT_ASSERT(no.lang.empty());
    CPPUNIT_ASSERT_EQUAL(0, no.totalLines);
    CPPUNIT_ASSERT_EQUAL(0, no.totalLinesOfComments);
    CPPUNIT_ASSERT_EQUAL(0, no.blankLines);
    CPPUNIT_ASSERT_EQUAL(0, no.sloc);
    CPPUNIT_ASSERT(no.singleLineComments.empty());
    CPPUNIT_ASSERT(no.contSingleLineComments.empty());
    CPPUNIT_ASSERT(no.multiLineComments.empty());
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(EnhancedReuserTypesTest);
