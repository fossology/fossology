/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include <set>
#include <tuple>

#include "MockEnhancedReuserDatabaseHandler.hpp"
#include "EnhancedReuserState.hpp"
#include "EnhancedReuserTypes.hpp"

/**
 * @brief Minimal replica of processUploadId (EnhancedReuserUtils.cc), mirroring
 *        its duplicate-triple dedup, for unit-testing.
 *
 * The real processUploadId calls fo_scheduler_groupID() / fo_scheduler_userID()
 * which need a running scheduler.  This helper accepts the ids directly so
 * tests remain self-contained.
 */
static bool runProcessUpload(EnhancedReuserDatabaseHandler& db, int uploadId,
  int groupId, int userId)
{
  auto reusedUploads = db.getReusedUploads(uploadId, groupId);

  std::set<std::tuple<int, int, int>> seen;

  for (const auto& triple : reusedUploads)
  {
    if (!(triple.reuseMode & REUSE_ENHANCED))
      continue;

    auto key = std::make_tuple(triple.reusedUploadId, triple.reusedGroupId,
                               triple.reuseMode);
    if (!seen.insert(key).second)
      continue; // duplicate reuse triple, already processed

    ItemTreeBounds boundsReused;
    if (!db.getParentItemBounds(triple.reusedUploadId, boundsReused))
      continue;

    if (!db.processEnhancedUploadReuse(
          uploadId, triple.reusedUploadId,
          groupId, triple.reusedGroupId, userId))
      return false;
  }
  return true;
}

class EnhancedReuserWorkerTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(EnhancedReuserWorkerTest);
  CPPUNIT_TEST(testEnhancedTripleTriggersProcess);
  CPPUNIT_TEST(testNonEnhancedTripleIsSkipped);
  CPPUNIT_TEST(testMultipleTriplesAllEnhanced);
  CPPUNIT_TEST(testMultipleTriplesMixedFlags);
  CPPUNIT_TEST(testNoReusedUploadsSucceeds);
  CPPUNIT_TEST(testMissingParentBoundsSkips);
  CPPUNIT_TEST(testProcessFailurePropagates);
  CPPUNIT_TEST(testDuplicateTripleProcessedOnce);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testEnhancedTripleTriggersProcess()
  {
    MockEnhancedReuserDatabaseHandler handler;
    bool enhancedCalled = false;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    {
      return {{10, 1, REUSE_ENHANCED}};
    };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    {
      out = {1, "uploadtree_a", 10, 1, 100};
      return true;
    };

    handler.onProcessEnhancedUploadReuse =
      [&](int, int, int, int, int) -> bool
    {
      enhancedCalled = true;
      return true;
    };

    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT(enhancedCalled);
  }

  void testNonEnhancedTripleIsSkipped()
  {
    MockEnhancedReuserDatabaseHandler handler;
    bool enhancedCalled = false;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    {
      return {{10, 1, 0}};
    };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    {
      out = {1, "uploadtree_a", 10, 1, 100};
      return true;
    };

    handler.onProcessEnhancedUploadReuse =
      [&](int, int, int, int, int) -> bool
    {
      enhancedCalled = true;
      return true;
    };

    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT(!enhancedCalled);
  }

  void testMultipleTriplesAllEnhanced()
  {
    MockEnhancedReuserDatabaseHandler handler;
    std::vector<int> calledWithReused;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    {
      return {{10, 1, REUSE_ENHANCED}, {20, 2, REUSE_ENHANCED}};
    };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    {
      out = {1, "uploadtree_a", 0, 1, 100};
      return true;
    };

    handler.onProcessEnhancedUploadReuse =
      [&](int, int reused, int, int, int) -> bool
    {
      calledWithReused.push_back(reused);
      return true;
    };

    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_EQUAL(2u, static_cast<unsigned>(calledWithReused.size()));
    CPPUNIT_ASSERT_EQUAL(10, calledWithReused[0]);
    CPPUNIT_ASSERT_EQUAL(20, calledWithReused[1]);
  }

  void testMultipleTriplesMixedFlags()
  {
    MockEnhancedReuserDatabaseHandler handler;
    std::vector<int> calledWithReused;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    {
      return {{10, 1, REUSE_ENHANCED},
              {20, 2, 0},
              {30, 3, REUSE_ENHANCED | 4}};
    };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    {
      out = {1, "uploadtree_a", 0, 1, 100};
      return true;
    };

    handler.onProcessEnhancedUploadReuse =
      [&](int, int reused, int, int, int) -> bool
    {
      calledWithReused.push_back(reused);
      return true;
    };

    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_EQUAL(2u, static_cast<unsigned>(calledWithReused.size()));
    CPPUNIT_ASSERT_EQUAL(10, calledWithReused[0]);
    CPPUNIT_ASSERT_EQUAL(30, calledWithReused[1]);
  }

  void testNoReusedUploadsSucceeds()
  {
    MockEnhancedReuserDatabaseHandler handler;
    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
  }

  void testMissingParentBoundsSkips()
  {
    MockEnhancedReuserDatabaseHandler handler;
    int processCallCount = 0;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    {
      return {{10, 1, REUSE_ENHANCED}, {20, 1, REUSE_ENHANCED}};
    };

    handler.onGetParentItemBounds =
      [](int uploadId, ItemTreeBounds& out) -> bool
    {
      if (uploadId == 10) return false;
      out = {1, "uploadtree", uploadId, 1, 10};
      return true;
    };

    handler.onProcessEnhancedUploadReuse =
      [&](int, int, int, int, int) -> bool
    {
      ++processCallCount;
      return true;
    };

    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_EQUAL(1, processCallCount);
  }

  void testProcessFailurePropagates()
  {
    MockEnhancedReuserDatabaseHandler handler;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    {
      return {{10, 1, REUSE_ENHANCED}};
    };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    {
      out = {1, "uploadtree_a", 10, 1, 100};
      return true;
    };

    handler.onProcessEnhancedUploadReuse =
      [](int, int, int, int, int) -> bool
    {
      return false;
    };

    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(!ok);
  }

  void testDuplicateTripleProcessedOnce()
  {
    MockEnhancedReuserDatabaseHandler handler;
    int processCallCount = 0;

    // Same (reusedUploadId, reusedGroupId, reuseMode) triple reported twice,
    // e.g. from a re-scheduled job inserting the upload_reuse row again.
    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    {
      return {{10, 1, REUSE_ENHANCED}, {10, 1, REUSE_ENHANCED}};
    };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    {
      out = {1, "uploadtree_a", 10, 1, 100};
      return true;
    };

    handler.onProcessEnhancedUploadReuse =
      [&](int, int, int, int, int) -> bool
    {
      ++processCallCount;
      return true;
    };

    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_EQUAL(1, processCallCount);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(EnhancedReuserWorkerTest);
