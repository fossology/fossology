/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @file
 * @brief Scenario tests for processUploadId (ReuserUtils.cc).
 */

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "MockReuserDatabaseHandler.hpp"
#include "ReuserState.hpp"
#include "ReuserTypes.hpp"

static bool runProcess(ReuserDatabaseHandler& db, int uploadId,
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

class ReuserScenarioTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ReuserScenarioTest);

  CPPUNIT_TEST(testNoReuseLinkSucceedsWithoutProcessing);
  CPPUNIT_TEST(testReuseExistsButSourceHasNoClearings);
  CPPUNIT_TEST(testCorrectArgumentsForwardedToProcessUploadReuse);

  CPPUNIT_TEST(testMultipleReuseLinksAllProcessed);

  CPPUNIT_TEST(testReuseMainLicenseFlagOnly);
  CPPUNIT_TEST(testReuseConfFlagOnly);
  CPPUNIT_TEST(testReuseCopyrightFlagOnly);
  CPPUNIT_TEST(testOptionalFlagsNotCalledWithoutBits);

  CPPUNIT_TEST_SUITE_END();

protected:

  void testNoReuseLinkSucceedsWithoutProcessing()
  {
    MockReuserDatabaseHandler db;

    bool processUploadCalled = false;
    db.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
      { processUploadCalled = true; return true; };

    bool ok = runProcess(db, 1, 3, 2);

    CPPUNIT_ASSERT_MESSAGE("should succeed with no reuse links", ok);
    CPPUNIT_ASSERT_MESSAGE("processUploadReuse must not be called",
      !processUploadCalled);
  }

  void testReuseExistsButSourceHasNoClearings()
  {
    MockReuserDatabaseHandler db;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{42, 3, 0}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {100, "uploadtree_a", 42, 1, 100}; return true; };

    int processCalls = 0;
    db.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
      { ++processCalls; return true; };

    bool ok = runProcess(db, 3, 3, 2);

    CPPUNIT_ASSERT_MESSAGE("should succeed even if source has no decisions", ok);
    CPPUNIT_ASSERT_EQUAL_MESSAGE(
      "processUploadReuse must be called exactly once", 1, processCalls);
  }

  void testCorrectArgumentsForwardedToProcessUploadReuse()
  {
    MockReuserDatabaseHandler db;

    const int uploadId      = 3;
    const int reusedUpload  = 2;
    const int groupId       = 3;
    const int reusedGroupId = 3;
    const int userId        = 2;

    db.onGetReusedUploads =
      [&](int uid, int gid) -> std::vector<ReuseTriple>
      {
        CPPUNIT_ASSERT_EQUAL(uploadId, uid);
        CPPUNIT_ASSERT_EQUAL(groupId,  gid);
        return {{reusedUpload, reusedGroupId, 0}};
      };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 2, 1, 100}; return true; };

    int capturedUpload      = -1;
    int capturedReused      = -1;
    int capturedGroup       = -1;
    int capturedReusedGroup = -1;
    int capturedUser        = -1;

    db.onProcessUploadReuse =
      [&](int u, int r, int g, int rg, int usr) -> bool
      {
        capturedUpload      = u;
        capturedReused      = r;
        capturedGroup       = g;
        capturedReusedGroup = rg;
        capturedUser        = usr;
        return true;
      };

    bool ok = runProcess(db, uploadId, groupId, userId);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_EQUAL(uploadId,      capturedUpload);
    CPPUNIT_ASSERT_EQUAL(reusedUpload,  capturedReused);
    CPPUNIT_ASSERT_EQUAL(groupId,       capturedGroup);
    CPPUNIT_ASSERT_EQUAL(reusedGroupId, capturedReusedGroup);
    CPPUNIT_ASSERT_EQUAL(userId,        capturedUser);
  }

  void testMultipleReuseLinksAllProcessed()
  {
    MockReuserDatabaseHandler db;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{10, 3, 0}, {20, 5, 0}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 0, 1, 100}; return true; };

    std::vector<int> calledWithReused;
    db.onProcessUploadReuse =
      [&](int, int reused, int, int, int) -> bool
      { calledWithReused.push_back(reused); return true; };

    bool ok = runProcess(db, 1, 3, 2);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_EQUAL(2u, static_cast<unsigned>(calledWithReused.size()));
    CPPUNIT_ASSERT_EQUAL(10, calledWithReused[0]);
    CPPUNIT_ASSERT_EQUAL(20, calledWithReused[1]);
  }

  void testReuseMainLicenseFlagOnly()
  {
    MockReuserDatabaseHandler db;

    bool mainCalled      = false;
    bool confCalled      = false;
    bool copyrightCalled = false;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{2, 3, REUSE_MAIN}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 2, 1, 100}; return true; };

    db.onProcessUploadReuse =
      [](int, int, int, int, int) -> bool { return true; };

    db.onReuseMainLicense =
      [&](int, int, int, int) -> bool { mainCalled = true; return true; };

    db.onReuseConfSettings =
      [&](int, int) -> bool { confCalled = true; return true; };

    db.onReuseCopyrights =
      [&](int, int, int) -> bool { copyrightCalled = true; return true; };

    bool ok = runProcess(db, 3, 3, 2);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_MESSAGE("reuseMainLicense must be called", mainCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseConfSettings must not be called", !confCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseCopyrights must not be called",
      !copyrightCalled);
  }

  void testReuseConfFlagOnly()
  {
    MockReuserDatabaseHandler db;

    bool mainCalled      = false;
    bool confCalled      = false;
    bool copyrightCalled = false;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{2, 3, REUSE_CONF}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 2, 1, 100}; return true; };

    db.onProcessUploadReuse =
      [](int, int, int, int, int) -> bool { return true; };

    db.onReuseMainLicense =
      [&](int, int, int, int) -> bool { mainCalled = true; return true; };

    db.onReuseConfSettings =
      [&](int, int) -> bool { confCalled = true; return true; };

    db.onReuseCopyrights =
      [&](int, int, int) -> bool { copyrightCalled = true; return true; };

    bool ok = runProcess(db, 3, 3, 2);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_MESSAGE("reuseMainLicense must not be called", !mainCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseConfSettings must be called", confCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseCopyrights must not be called",
      !copyrightCalled);
  }

  void testReuseCopyrightFlagOnly()
  {
    MockReuserDatabaseHandler db;

    bool mainCalled      = false;
    bool confCalled      = false;
    bool copyrightCalled = false;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{2, 3, REUSE_COPYRIGHT}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 2, 1, 100}; return true; };

    db.onProcessUploadReuse =
      [](int, int, int, int, int) -> bool { return true; };

    db.onReuseMainLicense =
      [&](int, int, int, int) -> bool { mainCalled = true; return true; };

    db.onReuseConfSettings =
      [&](int, int) -> bool { confCalled = true; return true; };

    db.onReuseCopyrights =
      [&](int, int, int) -> bool { copyrightCalled = true; return true; };

    bool ok = runProcess(db, 3, 3, 2);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_MESSAGE("reuseMainLicense must not be called", !mainCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseConfSettings must not be called", !confCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseCopyrights must be called", copyrightCalled);
  }

  void testOptionalFlagsNotCalledWithoutBits()
  {
    MockReuserDatabaseHandler db;

    bool mainCalled      = false;
    bool confCalled      = false;
    bool copyrightCalled = false;
    bool standardCalled  = false;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{2, 3, 0}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 2, 1, 100}; return true; };

    db.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
      { standardCalled = true; return true; };

    db.onReuseMainLicense =
      [&](int, int, int, int) -> bool { mainCalled = true; return true; };

    db.onReuseConfSettings =
      [&](int, int) -> bool { confCalled = true; return true; };

    db.onReuseCopyrights =
      [&](int, int, int) -> bool { copyrightCalled = true; return true; };

    bool ok = runProcess(db, 3, 3, 2);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_MESSAGE("standard reuse must be called", standardCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseMainLicense must not be called", !mainCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseConfSettings must not be called", !confCalled);
    CPPUNIT_ASSERT_MESSAGE("reuseCopyrights must not be called",
      !copyrightCalled);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ReuserScenarioTest);
