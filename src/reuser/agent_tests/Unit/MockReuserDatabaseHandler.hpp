/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @file
 * @brief Mock ReuserDatabaseHandler for unit tests.
 *
 * Derives from ReuserDatabaseHandler and overrides only the public
 * business-logic methods so that no real DB connection is needed.
 * All private DB helpers stay in the base class and are never called
 * because the overrides short-circuit execution.
 */
#pragma once

#include "ReuserDatabaseHandler.hpp"

#include <functional>
#include <map>
#include <vector>

/**
 * @class MockReuserDatabaseHandler
 * @brief Test double for ReuserDatabaseHandler.
 *
 * Provides injectable lambdas for every public method.  Tests set up
 * only the callbacks they care about; unset callbacks return safe
 * defaults (empty containers / true).
 */
class MockReuserDatabaseHandler : public ReuserDatabaseHandler
{
public:
  /**
   * Construct without a real DB connection.
   *
   * fo::DbManager has no default constructor, so we use a delegating
   * path: pass a null fo_dbManager* wrapped as a DbManager.
   */
  explicit MockReuserDatabaseHandler();

  // ── Overridable callbacks ─────────────────────────────────────────────────

  std::function<bool(int, ItemTreeBounds&)>
    onGetParentItemBounds;

  std::function<std::vector<ReuseTriple>(int, int)>
    onGetReusedUploads;

  std::function<std::map<int, int>(int, int)>
    onGetClearingDecisionMapByPfile;

  std::function<std::map<int, std::vector<int>>(int, const std::vector<int>&)>
    onGetUploadTreePksForPfiles;

  std::function<bool(int, int, int, int, int)>
    onProcessUploadReuse;

  std::function<bool(int, int, int, int, int)>
    onProcessEnhancedUploadReuse;

  std::function<bool(int, int, int, int)>
    onReuseMainLicense;

  std::function<bool(int, int)>
    onReuseConfSettings;

  std::function<bool(int, int, int)>
    onReuseCopyrights;

  // ── ReuserDatabaseHandler overrides ──────────────────────────────────────

  bool getParentItemBounds(int uploadId, ItemTreeBounds& out) override
  {
    if (onGetParentItemBounds) return onGetParentItemBounds(uploadId, out);
    return false;  // no callback set: leave out unmodified, signal "not found"
  }

  std::vector<ReuseTriple> getReusedUploads(int uploadId, int groupId) override
  {
    if (onGetReusedUploads) return onGetReusedUploads(uploadId, groupId);
    return {};
  }

  std::map<int, int> getClearingDecisionMapByPfile(
    int uploadId, int groupId) override
  {
    if (onGetClearingDecisionMapByPfile)
      return onGetClearingDecisionMapByPfile(uploadId, groupId);
    return {};
  }

  std::map<int, std::vector<int>> getUploadTreePksForPfiles(
    int uploadId, const std::vector<int>& pfileIds) override
  {
    if (onGetUploadTreePksForPfiles)
      return onGetUploadTreePksForPfiles(uploadId, pfileIds);
    return {};
  }

  bool processUploadReuse(int uploadId, int reusedUploadId,
    int groupId, int reusedGroupId, int userId) override
  {
    if (onProcessUploadReuse)
      return onProcessUploadReuse(uploadId, reusedUploadId, groupId,
        reusedGroupId, userId);
    return true;
  }

  bool processEnhancedUploadReuse(int uploadId, int reusedUploadId,
    int groupId, int reusedGroupId, int userId) override
  {
    if (onProcessEnhancedUploadReuse)
      return onProcessEnhancedUploadReuse(uploadId, reusedUploadId, groupId,
        reusedGroupId, userId);
    return true;
  }

  bool reuseMainLicense(int uploadId, int groupId,
    int reusedUploadId, int reusedGroupId) override
  {
    if (onReuseMainLicense)
      return onReuseMainLicense(uploadId, groupId, reusedUploadId,
        reusedGroupId);
    return true;
  }

  bool reuseConfSettings(int uploadId, int reusedUploadId) override
  {
    if (onReuseConfSettings) return onReuseConfSettings(uploadId, reusedUploadId);
    return true;
  }

  bool reuseCopyrights(int uploadId, int reusedUploadId, int userId) override
  {
    if (onReuseCopyrights)
      return onReuseCopyrights(uploadId, reusedUploadId, userId);
    return true;
  }
};
