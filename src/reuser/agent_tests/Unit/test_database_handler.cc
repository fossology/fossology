/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @file
 * @brief Unit tests for ReuserDatabaseHandler query results.
 *
 * Uses MockReuserDatabaseHandler with injectable callbacks to verify
 * that getClearingDecisionMapByPfile and getUploadTreePksForPfiles
 * produce correctly-shaped output.
 */

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "MockReuserDatabaseHandler.hpp"

#include <map>
#include <vector>

class ReuserDatabaseHandlerTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ReuserDatabaseHandlerTest);
  CPPUNIT_TEST(testGetClearingDecisionMapReturnsFirstDecisionPerPfile);
  CPPUNIT_TEST(testGetClearingDecisionMapEmptyOnNoData);
  CPPUNIT_TEST(testGetUploadTreePksGroupedByPfile);
  CPPUNIT_TEST(testGetUploadTreePksEmptyOnEmptyInput);
  CPPUNIT_TEST(testGetReusedUploadsReturnedInOrder);
  CPPUNIT_TEST(testGetParentItemBoundsPopulatesStruct);
  CPPUNIT_TEST_SUITE_END();

protected:
  /**
   * @brief getClearingDecisionMapByPfile returns one entry per distinct pfile_fk.
   *
   * The map key is the pfile_fk and the value is the clearing_decision_pk.
   * When the callback returns two rows for different pfiles, both must appear
   * in the result with their respective decision ids.
   */
  void testGetClearingDecisionMapReturnsFirstDecisionPerPfile()
  {
    MockReuserDatabaseHandler handler;

    // Simulate: two rows for pfile 101 (highest id first), one row for pfile 50.
    handler.onGetClearingDecisionMapByPfile =
      [](int /*uploadId*/, int /*groupId*/) -> std::map<int, int>
    {
      return {{101, 20}, {50, 10}};
    };

    auto result = handler.getClearingDecisionMapByPfile(1, 1);

    CPPUNIT_ASSERT_EQUAL(2u, static_cast<unsigned>(result.size()));
    CPPUNIT_ASSERT(result.find(101) != result.end());
    CPPUNIT_ASSERT_EQUAL(20, result.at(101));
    CPPUNIT_ASSERT(result.find(50) != result.end());
    CPPUNIT_ASSERT_EQUAL(10, result.at(50));
  }

  /**
   * @brief getClearingDecisionMapByPfile returns an empty map when no callback is set.
   *
   * The default mock behaviour (no onGetClearingDecisionMapByPfile callback)
   * must return an empty map rather than crashing or returning garbage.
   */
  void testGetClearingDecisionMapEmptyOnNoData()
  {
    MockReuserDatabaseHandler handler;
    // Callback not set → default returns empty map.
    auto result = handler.getClearingDecisionMapByPfile(99, 1);
    CPPUNIT_ASSERT(result.empty());
  }

  /**
   * @brief getUploadTreePksForPfiles groups uploadtree_pk values by pfile_fk.
   *
   * For each pfile_fk in the input list the result map must contain an entry
   * whose value is the vector of matching uploadtree primary keys, preserving
   * order within each group.
   */
  void testGetUploadTreePksGroupedByPfile()
  {
    MockReuserDatabaseHandler handler;

    handler.onGetUploadTreePksForPfiles =
      [](int /*uploadId*/,
         const std::vector<int>& /*pfileIds*/)
        -> std::map<int, std::vector<int>>
    {
      return {{101, {1001, 1002}}, {50, {1200}}};
    };

    std::vector<int> pfiles = {101, 50};
    auto result = handler.getUploadTreePksForPfiles(1, pfiles);

    CPPUNIT_ASSERT_EQUAL(2u, static_cast<unsigned>(result.size()));

    CPPUNIT_ASSERT(result.find(101) != result.end());
    CPPUNIT_ASSERT_EQUAL(2, static_cast<int>(result.at(101).size()));
    CPPUNIT_ASSERT_EQUAL(1001, result.at(101)[0]);
    CPPUNIT_ASSERT_EQUAL(1002, result.at(101)[1]);

    CPPUNIT_ASSERT(result.find(50) != result.end());
    CPPUNIT_ASSERT_EQUAL(1, static_cast<int>(result.at(50).size()));
    CPPUNIT_ASSERT_EQUAL(1200, result.at(50)[0]);
  }

  /**
   * @brief getUploadTreePksForPfiles returns an empty map for an empty pfile list.
   *
   * Passing an empty pfile id vector must yield an empty result map;
   * no crash or undefined behaviour is acceptable.
   */
  void testGetUploadTreePksEmptyOnEmptyInput()
  {
    MockReuserDatabaseHandler handler;
    // Callback not set and empty input → must return empty map.
    auto result = handler.getUploadTreePksForPfiles(1, {});
    CPPUNIT_ASSERT(result.empty());
  }

  /**
   * @brief getReusedUploads preserves the order and content of ReuseTriple entries.
   *
   * The returned vector must contain every element delivered by the callback in
   * the same order, with reusedUploadId and reuseMode intact.
   */
  void testGetReusedUploadsReturnedInOrder()
  {
    MockReuserDatabaseHandler handler;

    handler.onGetReusedUploads =
      [](int /*uploadId*/, int /*groupId*/) -> std::vector<ReuseTriple>
    {
      return {{10, 1, REUSE_MAIN}, {20, 2, REUSE_ENHANCED | REUSE_COPYRIGHT}};
    };

    auto result = handler.getReusedUploads(5, 1);

    CPPUNIT_ASSERT_EQUAL(2u, static_cast<unsigned>(result.size()));
    CPPUNIT_ASSERT_EQUAL(10,         result[0].reusedUploadId);
    CPPUNIT_ASSERT_EQUAL(REUSE_MAIN, result[0].reuseMode);
    CPPUNIT_ASSERT_EQUAL(20,                          result[1].reusedUploadId);
    CPPUNIT_ASSERT_EQUAL(REUSE_ENHANCED | REUSE_COPYRIGHT,
                         result[1].reuseMode);
  }

  /**
   * @brief getParentItemBounds populates all ItemTreeBounds fields on success.
   *
   * When the callback writes to the output parameter and returns true, every
   * field of the struct (uploadtree_pk, uploadTreeTableName, upload_fk, lft,
   * rgt) must reflect the values written by the callback.
   */
  void testGetParentItemBoundsPopulatesStruct()
  {
    MockReuserDatabaseHandler handler;

    handler.onGetParentItemBounds =
      [](int /*uploadId*/, ItemTreeBounds& out) -> bool
    {
      out = {500, "uploadtree", 7, 1, 42};
      return true;
    };

    ItemTreeBounds bounds{};
    bool ok = handler.getParentItemBounds(7, bounds);

    CPPUNIT_ASSERT(ok);
    CPPUNIT_ASSERT_EQUAL(500,                        bounds.uploadtree_pk);
    CPPUNIT_ASSERT_EQUAL(std::string("uploadtree"),  bounds.uploadTreeTableName);
    CPPUNIT_ASSERT_EQUAL(7,                          bounds.upload_fk);
    CPPUNIT_ASSERT_EQUAL(1,                          bounds.lft);
    CPPUNIT_ASSERT_EQUAL(42,                         bounds.rgt);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ReuserDatabaseHandlerTest);
