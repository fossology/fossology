/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @file
 * @brief Unit tests for processUploadId (ReuserUtils.cc).
 *
 * Exercises the reuse dispatch logic without touching the database.
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
  if (!db.processBulkReuser(uploadId, groupId, userId))
  {
    return false;
  }

  auto reusedUploads = db.getReusedUploads(uploadId, groupId);
  for (const auto& triple : reusedUploads)
  {
    ItemTreeBounds bounds;
    if (!db.getParentItemBounds(triple.reusedUploadId, bounds))
      continue;

    if (!db.processUploadReuse(uploadId, triple.reusedUploadId,
          groupId, triple.reusedGroupId, userId))
      return false;

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
  CPPUNIT_TEST(testNoReusedUploadsSucceeds);
  CPPUNIT_TEST(testMissingParentBoundsSkipsUpload);
  CPPUNIT_TEST(testProcessUploadReuseFailurePropagates);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testStandardReuseDispatch()
  {
    MockReuserDatabaseHandler handler;

    bool standardCalled = false;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{10, 1, 0}}; };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 10, 1, 100}; return true; };

    handler.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
    { standardCalled = true; return true; };

    bool ok = runProcessUpload(handler, 1, 1, 1);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT(standardCalled);
  }

  void testNoReusedUploadsSucceeds()
  {
    MockReuserDatabaseHandler handler;
    bool ok = runProcessUpload(handler, 1, 1, 1);
    CPPUNIT_ASSERT(ok);
  }

  void testMissingParentBoundsSkipsUpload()
  {
    MockReuserDatabaseHandler handler;

    int processCalledCount = 0;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{10, 1, 0}, {20, 1, 0}}; };

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
    CPPUNIT_ASSERT_EQUAL(1, processCalledCount);
  }

  void testProcessUploadReuseFailurePropagates()
  {
    MockReuserDatabaseHandler handler;

    handler.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{10, 1, 0}}; };

    handler.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 10, 1, 100}; return true; };

    handler.onProcessUploadReuse =
      [](int, int, int, int, int) -> bool
    { return false; };

    bool ok = runProcessUpload(handler, 1, 1, 1);

    CPPUNIT_ASSERT(!ok);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ReuserWorkerTest);
