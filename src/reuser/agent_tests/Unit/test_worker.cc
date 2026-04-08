/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @file
 * @brief Unit tests for processUploadId (ReuserUtils.cc).
 *
 * Exercises the reuse dispatch logic (which REUSE_* path is called,
 * whether bail-out on error works) without touching the database.
 */

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "MockReuserDatabaseHandler.hpp"
#include "ReuserState.hpp"
#include "ReuserTypes.hpp"

/**
 * @brief Minimal replica of processUploadId for unit-testing.
 *
 * The real processUploadId calls fo_scheduler_groupID() /
 * fo_scheduler_userID() which need a running scheduler.  This helper
 * accepts the ids directly so tests remain self-contained.
 */
static bool runProcessUpload(ReuserDatabaseHandler& db, int uploadId,
  int groupId, int userId)
{
  auto reusedUploads = db.getReusedUploads(uploadId, groupId);
  for (const auto& triple : reusedUploads)
  {
    ItemTreeBounds bounds;
    if (!db.getParentItemBounds(triple.reusedUploadId, bounds))
      continue;

    if (triple.reuseMode & REUSE_ENHANCED)
    {
      if (!db.processEnhancedUploadReuse(uploadId, triple.reusedUploadId,
            groupId, triple.reusedGroupId, userId))
        return false;
    }
    else
    {
      if (!db.processUploadReuse(uploadId, triple.reusedUploadId,
            groupId, triple.reusedGroupId, userId))
        return false;
    }

    if (triple.reuseMode & REUSE_MAIN)
      db.reuseMainLicense(uploadId, groupId, triple.reusedUploadId,
        triple.reusedGroupId);

    if (triple.reuseMode & REUSE_CONF)
      db.reuseConfSettings(uploadId, triple.reusedUploadId);

    if (triple.reuseMode & REUSE_COPYRIGHT)
      db.reuseCopyrights(uploadId, triple.reusedUploadId, userId);
  }
  return true;
}

class ReuserWorkerTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ReuserWorkerTest);
  CPPUNIT_TEST(testStandardReuseDispatch);
  CPPUNIT_TEST(testEnhancedReuseDispatch);
  CPPUNIT_TEST(testAllFlagsDispatch);
  CPPUNIT_TEST(testNoReusedUploadsSucceeds);
  CPPUNIT_TEST(testMissingParentBoundsSkipsUpload);
  CPPUNIT_TEST(testProcessUploadReuseFailurePropagates);
  CPPUNIT_TEST_SUITE_END();

protected:
  /**
   * @brief reuseMode == 0 routes to processUploadReuse (standard path).
   *
   * When none of the reuse-mode bits are set, processUploadReuse must be
   * called and processEnhancedUploadReuse must not be called.
   */
  void testStandardReuseDispatch()
  {
    MockReuserDatabaseHandler handler;

    bool standardCalled = false;
    bool enhancedCalled = false;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{10, 1, 0 /* no flags → standard */}}; };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 10, 1, 100}; return true; };

    handler.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
    { standardCalled = true; return true; };

    handler.onProcessEnhancedUploadReuse =
      [&](int, int, int, int, int) -> bool
    { enhancedCalled = true; return true; };

    bool ok = runProcessUpload(handler, 1, 1, 1);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT(standardCalled);
    CPPUNIT_ASSERT(!enhancedCalled);
  }

  /**
   * @brief REUSE_ENHANCED bit routes to processEnhancedUploadReuse.
   *
   * When the REUSE_ENHANCED bit is set, processEnhancedUploadReuse must be
   * called and processUploadReuse must not be called.
   */
  void testEnhancedReuseDispatch()
  {
    MockReuserDatabaseHandler handler;

    bool standardCalled = false;
    bool enhancedCalled = false;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{10, 1, REUSE_ENHANCED}}; };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 10, 1, 100}; return true; };

    handler.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
    { standardCalled = true; return true; };

    handler.onProcessEnhancedUploadReuse =
      [&](int, int, int, int, int) -> bool
    { enhancedCalled = true; return true; };

    bool ok = runProcessUpload(handler, 1, 1, 1);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT(!standardCalled);
    CPPUNIT_ASSERT(enhancedCalled);
  }

  /**
   * @brief All optional flag bits together cause all optional methods to be called.
   *
   * When reuseMode has REUSE_ENHANCED | REUSE_MAIN | REUSE_CONF | REUSE_COPYRIGHT
   * set, reuseMainLicense, reuseConfSettings and reuseCopyrights must all be
   * invoked in addition to processEnhancedUploadReuse.
   */
  void testAllFlagsDispatch()
  {
    MockReuserDatabaseHandler handler;

    bool mainCalled      = false;
    bool confCalled      = false;
    bool copyrightCalled = false;

    int allFlags = REUSE_ENHANCED | REUSE_MAIN | REUSE_CONF | REUSE_COPYRIGHT;
    handler.onGetReusedUploads = [allFlags](int, int) -> std::vector<ReuseTriple>
    { return {{10, 1, allFlags}}; };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 10, 1, 100}; return true; };

    handler.onReuseMainLicense =
      [&](int, int, int, int) -> bool
    { mainCalled = true; return true; };

    handler.onReuseConfSettings =
      [&](int, int) -> bool
    { confCalled = true; return true; };

    handler.onReuseCopyrights =
      [&](int, int, int) -> bool
    { copyrightCalled = true; return true; };

    bool ok = runProcessUpload(handler, 1, 1, 1);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT(mainCalled);
    CPPUNIT_ASSERT(confCalled);
    CPPUNIT_ASSERT(copyrightCalled);
  }

  /**
   * @brief An empty reuse list results in immediate success without any processing.
   *
   * When getReusedUploads returns an empty vector, the function must return
   * true without calling any of the process* methods.
   */
  void testNoReusedUploadsSucceeds()
  {
    MockReuserDatabaseHandler handler;
    // Default: onGetReusedUploads not set → returns empty vector.
    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
  }

  /**
   * @brief A reuse link whose parent bounds cannot be determined is skipped.
   *
   * When getParentItemBounds returns false for a given reused upload id,
   * that link must be silently skipped and processing must continue with
   * the next link.  The overall result must still be true.
   */
  void testMissingParentBoundsSkipsUpload()
  {
    MockReuserDatabaseHandler handler;

    int processCalledCount = 0;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{10, 1, 0}, {20, 1, 0}}; };

    // First upload: bounds not found → skip.
    // Second upload: bounds found → process.
    handler.onGetParentItemBounds =
      [](int uploadId, ItemTreeBounds& out) -> bool
    {
      if (uploadId == 10) return false;
      out = {1, "uploadtree", uploadId, 1, 10};
      return true;
    };

    handler.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
    { ++processCalledCount; return true; };

    bool ok = runProcessUpload(handler, 1, 1, 1);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_EQUAL(1, processCalledCount); // only second upload processed
  }

  /**
   * @brief A false return from processUploadReuse propagates to the caller.
   *
   * When processUploadReuse signals failure, the worker must immediately
   * return false without processing further reuse links.
   */
  void testProcessUploadReuseFailurePropagates()
  {
    MockReuserDatabaseHandler handler;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{10, 1, 0}}; };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 10, 1, 100}; return true; };

    handler.onProcessUploadReuse =
      [](int, int, int, int, int) -> bool
    { return false; }; // simulate DB error

    bool ok = runProcessUpload(handler, 1, 1, 1);

    CPPUNIT_ASSERT(!ok);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ReuserWorkerTest);
