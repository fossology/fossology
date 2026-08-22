/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/

#include "EnhancedReuserUtils.hpp"

#include <cstdlib>
#include <cstdio>
#include <set>
#include <tuple>

using namespace fo;

EnhancedReuserState getState(DbManager& dbManager)
{
  return EnhancedReuserState(queryAgentId(dbManager));
}

int queryAgentId(DbManager& dbManager)
{
  char* commitHash = fo_sysconfig(AGENT_NAME, "COMMIT_HASH");
  char* version    = fo_sysconfig(AGENT_NAME, "VERSION");

  if (!commitHash || !version)
  {
    LOG_FATAL("EnhancedReuser: fo_sysconfig returned NULL for VERSION or COMMIT_HASH.");
    bail(1);
  }

  char* revision = nullptr;
  if (asprintf(&revision, "%s.%s", version, commitHash) < 0)
  {
    LOG_FATAL("EnhancedReuser: asprintf failed allocating revision string.");
    bail(1);
  }

  int agentId = fo_GetAgentKey(dbManager.getConnection(),
    AGENT_NAME, 0, revision, AGENT_DESC);
  free(revision);

  if (agentId <= 0)
    bail(1);

  return agentId;
}

int writeARS(const EnhancedReuserState& state, int arsId, int uploadId, int success,
  DbManager& dbManager, const char* arsStatus)
{
  return fo_WriteARS(dbManager.getConnection(), arsId, uploadId,
    state.getAgentId(), AGENT_ARS, arsStatus, success);
}

void bail(int exitval)
{
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

bool processUploadId(const EnhancedReuserState& /*state*/, int uploadId,
  EnhancedReuserDatabaseHandler& databaseHandler)
{
  databaseHandler.resetMetrics();

  int groupId = fo_scheduler_groupID();
  int userId  = fo_scheduler_userID();

  auto reusedUploads = databaseHandler.getReusedUploads(uploadId, groupId);

  // upload_reuse can hold duplicate rows for the same (upload, reused upload,
  // group, mode) triple (e.g. a re-scheduled job inserted the row again).  Each
  // distinct triple is processed exactly once per run.
  std::set<std::tuple<int, int, int>> seen;

  for (const auto& triple : reusedUploads)
  {
    if (!(triple.reuseMode & REUSE_ENHANCED))
      continue;

    auto key = std::make_tuple(triple.reusedUploadId, triple.reusedGroupId,
                               triple.reuseMode);
    if (!seen.insert(key).second)
    {
      LOG_WARNING("EnhancedReuser: skipping duplicate reuse triple"
                  " (reused upload %d, group %d) for upload %d.",
                  triple.reusedUploadId, triple.reusedGroupId, uploadId);
      continue;
    }

    ItemTreeBounds boundsReused;
    if (!databaseHandler.getParentItemBounds(triple.reusedUploadId,
          boundsReused))
    {
      LOG_WARNING("EnhancedReuser: could not determine parent bounds for reused"
                  " upload %d – skipping.", triple.reusedUploadId);
      continue;
    }

    if (!databaseHandler.processEnhancedUploadReuse(
          uploadId, triple.reusedUploadId,
          groupId, triple.reusedGroupId, userId))
      return false;
  }
  return true;
}
