/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @file
 * @brief Scenario tests for processUploadId (ReuserUtils.cc).
 *
 * Uses MockReuserDatabaseHandler with injectable callbacks to verify that
 * the correct reuse path is taken and all arguments are forwarded correctly
 * to processUploadReuse / processEnhancedUploadReuse based on the reuseMode
 * bits stored in each ReuseTriple.
 *
 * Covered scenarios:
 *  - No upload_reuse link → no processing, success
 *  - Reuse link present, source upload has no decisions → agent still succeeds
 *  - Correct arguments forwarded to processUploadReuse (uploadId, reusedUploadId,
 *    groupId, reusedGroupId, userId)
 *  - reuseMode == 0 → standard reuse path regardless of clearing scope
 *  - REUSE_ENHANCED bit → enhanced reuse path
 *  - Enhanced reuse failure propagates to caller
 *  - Multiple upload_reuse rows → all links processed in order
 *  - REUSE_MAIN / REUSE_CONF / REUSE_COPYRIGHT bits → only the matching
 *    optional method is called
 *  - reuseMode == 0 → no optional methods called
 */

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "MockReuserDatabaseHandler.hpp"
#include "ReuserState.hpp"
#include "ReuserTypes.hpp"

// ── Minimal replica of processUploadId without scheduler dependency ───────────
// Accepts groupId / userId explicitly so tests remain self-contained.
// Mirrors the helper defined in test_worker.cc.
static bool runProcess(ReuserDatabaseHandler& db, int uploadId,
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

// ── Test fixture ──────────────────────────────────────────────────────────────
class ReuserScenarioTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ReuserScenarioTest);

  // ── No reuse link ────────────────────────────────────────────────────────
  CPPUNIT_TEST(testNoReuseLinkSucceedsWithoutProcessing);
  CPPUNIT_TEST(testReuseExistsButSourceHasNoClearings);

  // ── Argument forwarding ──────────────────────────────────────────────────
  CPPUNIT_TEST(testCorrectArgumentsForwardedToProcessUploadReuse);

  // ── Scope handling ───────────────────────────────────────────────────────
  CPPUNIT_TEST(testRepoClearingUsesStandardReusePath);

  // ── Enhanced reuse ───────────────────────────────────────────────────────
  CPPUNIT_TEST(testEnhancedReuseUsesEnhancedPath);
  CPPUNIT_TEST(testEnhancedReuseFailurePropagates);

  // ── Multiple upload_reuse rows ───────────────────────────────────────────
  CPPUNIT_TEST(testMultipleReuseLinksAllProcessed);

  // ── Optional flag dispatch ───────────────────────────────────────────────
  CPPUNIT_TEST(testReuseMainLicenseFlagOnly);
  CPPUNIT_TEST(testReuseConfFlagOnly);
  CPPUNIT_TEST(testReuseCopyrightFlagOnly);
  CPPUNIT_TEST(testOptionalFlagsNotCalledWithoutBits);

  CPPUNIT_TEST_SUITE_END();

protected:

  /**
   * @brief No upload_reuse row exists for this upload.
   *
   * getReusedUploads returns an empty list → no processing takes place,
   * the overall result is success.
   */
  void testNoReuseLinkSucceedsWithoutProcessing()
  {
    MockReuserDatabaseHandler db;

    bool processUploadCalled = false;
    db.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
      { processUploadCalled = true; return true; };

    // onGetReusedUploads not set → default returns empty vector
    bool ok = runProcess(db, /*uploadId=*/1, /*groupId=*/3, /*userId=*/2);

    CPPUNIT_ASSERT_MESSAGE("should succeed with no reuse links", ok);
    CPPUNIT_ASSERT_MESSAGE("processUploadReuse must not be called",
      !processUploadCalled);
  }

  /**
   * @brief upload_reuse row exists but the source upload has no clearing decisions.
   *
   * The agent calls processUploadReuse and trusts the return value.
   * Whether 0 rows were actually copied is a database detail — the agent
   * must succeed as long as processUploadReuse returns true.
   */
  void testReuseExistsButSourceHasNoClearings()
  {
    MockReuserDatabaseHandler db;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{42, 3, 0}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {100, "uploadtree_a", 42, 1, 100}; return true; };

    int processCalls = 0;
    // returns true even if 0 decisions were present in the source upload
    db.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
      { ++processCalls; return true; };

    bool ok = runProcess(db, /*uploadId=*/3, /*groupId=*/3, /*userId=*/2);

    CPPUNIT_ASSERT_MESSAGE("should succeed even if source has no decisions", ok);
    CPPUNIT_ASSERT_EQUAL_MESSAGE(
      "processUploadReuse must be called exactly once", 1, processCalls);
  }

  /**
   * @brief All five arguments are forwarded correctly to processUploadReuse.
   *
   * Verifies that uploadId, reusedUploadId, groupId, reusedGroupId and userId
   * are passed through without modification.
   */
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
        return {{reusedUpload, reusedGroupId, 0 /* standard reuse, ITEM scope */}};
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

  /**
   * @brief reuseMode == 0 always takes the standard reuse path.
   *
   * The clearing scope (ITEM vs REPO) is a database attribute that the
   * agent never inspects.  Without the REUSE_ENHANCED bit, processUploadReuse
   * is called regardless of which scope the source clearing has.
   */
  void testRepoClearingUsesStandardReusePath()
  {
    MockReuserDatabaseHandler db;

    bool standardCalled = false;
    bool enhancedCalled = false;

    // reuseMode == 0 → no REUSE_ENHANCED → standard path, even for REPO scope
    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{2, 3, 0}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 2, 1, 100}; return true; };

    db.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
      { standardCalled = true; return true; };

    db.onProcessEnhancedUploadReuse =
      [&](int, int, int, int, int) -> bool
      { enhancedCalled = true; return true; };

    bool ok = runProcess(db, 3, 3, 2);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_MESSAGE("standard path for repo-scoped clearing",
      standardCalled);
    CPPUNIT_ASSERT_MESSAGE("enhanced path must not be used", !enhancedCalled);
  }

  /**
   * @brief REUSE_ENHANCED bit routes to processEnhancedUploadReuse.
   *
   * When the REUSE_ENHANCED bit is set in reuseMode, processEnhancedUploadReuse
   * must be called and processUploadReuse must not be called.
   */
  void testEnhancedReuseUsesEnhancedPath()
  {
    MockReuserDatabaseHandler db;

    bool standardCalled = false;
    bool enhancedCalled = false;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{2, 3, REUSE_ENHANCED}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 2, 1, 100}; return true; };

    db.onProcessUploadReuse =
      [&](int, int, int, int, int) -> bool
      { standardCalled = true; return true; };

    db.onProcessEnhancedUploadReuse =
      [&](int, int, int, int, int) -> bool
      { enhancedCalled = true; return true; };

    bool ok = runProcess(db, 3, 3, 2);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_MESSAGE("enhanced path must be taken", enhancedCalled);
    CPPUNIT_ASSERT_MESSAGE("standard path must not be taken", !standardCalled);
  }

  /**
   * @brief A false return from processEnhancedUploadReuse propagates to the caller.
   */
  void testEnhancedReuseFailurePropagates()
  {
    MockReuserDatabaseHandler db;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{2, 3, REUSE_ENHANCED}}; };

    db.onGetParentItemBounds = [](int, ItemTreeBounds& out) -> bool
    { out = {1, "uploadtree_a", 2, 1, 100}; return true; };

    db.onProcessEnhancedUploadReuse =
      [](int, int, int, int, int) -> bool { return false; };

    bool ok = runProcess(db, 3, 3, 2);

    CPPUNIT_ASSERT_MESSAGE("failure of enhanced reuse must propagate", !ok);
  }

  /**
   * @brief All upload_reuse rows for an upload are processed in order.
   *
   * A single agent invocation iterates over every reuse link returned by
   * getReusedUploads and calls processUploadReuse for each.
   */
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
    CPPUNIT_ASSERT_EQUAL_MESSAGE("both reuse links must be processed",
      2u, static_cast<unsigned>(calledWithReused.size()));
    CPPUNIT_ASSERT_EQUAL(10, calledWithReused[0]);
    CPPUNIT_ASSERT_EQUAL(20, calledWithReused[1]);
  }

  /**
   * @brief REUSE_MAIN bit causes only reuseMainLicense to be called.
   *
   * reuseConfSettings and reuseCopyrights must remain uncalled.
   */
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

  /**
   * @brief REUSE_CONF bit causes only reuseConfSettings to be called.
   *
   * reuseMainLicense and reuseCopyrights must remain uncalled.
   */
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

  /**
   * @brief REUSE_COPYRIGHT bit causes only reuseCopyrights to be called.
   *
   * reuseMainLicense and reuseConfSettings must remain uncalled.
   */
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

  /**
   * @brief reuseMode == 0 → only processUploadReuse is called, no optional extras.
   *
   * With none of the REUSE_MAIN, REUSE_CONF, or REUSE_COPYRIGHT bits set,
   * none of the corresponding methods must be called.
   */
  void testOptionalFlagsNotCalledWithoutBits()
  {
    MockReuserDatabaseHandler db;

    bool mainCalled      = false;
    bool confCalled      = false;
    bool copyrightCalled = false;
    bool standardCalled  = false;

    db.onGetReusedUploads = [](int, int) -> std::vector<ReuseTriple>
    { return {{2, 3, 0 /* no flags */}}; };

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
