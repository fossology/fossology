/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#pragma once

#include "EnhancedReuserDatabaseHandler.hpp"

#include <functional>
#include <map>
#include <vector>

class MockEnhancedReuserDatabaseHandler : public EnhancedReuserDatabaseHandler
{
public:
  explicit MockEnhancedReuserDatabaseHandler();

  std::function<bool(int, ItemTreeBounds&)>
    onGetParentItemBounds;

  std::function<std::vector<ReuseTriple>(int, int)>
    onGetReusedUploads;

  std::function<std::map<int, int>(int, int)>
    onGetClearingDecisionMapByPfile;

  std::function<bool(int, int, int, int, int)>
    onProcessEnhancedUploadReuse;

  bool getParentItemBounds(int uploadId, ItemTreeBounds& out) override
  {
    if (onGetParentItemBounds) return onGetParentItemBounds(uploadId, out);
    return false;
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

  bool processEnhancedUploadReuse(int uploadId, int reusedUploadId,
    int groupId, int reusedGroupId, int userId) override
  {
    if (onProcessEnhancedUploadReuse)
      return onProcessEnhancedUploadReuse(uploadId, reusedUploadId,
        groupId, reusedGroupId, userId);
    return true;
  }
};
