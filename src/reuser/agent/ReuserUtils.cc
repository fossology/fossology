/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/

#include "ReuserUtils.hpp"

#include <cstdlib>
#include <cstdio>

using namespace fo;

ReuserState getState(DbManager& dbManager)
{
  return ReuserState(queryAgentId(dbManager));
}

int queryAgentId(DbManager& dbManager)
{
  char* commitHash = fo_sysconfig(AGENT_NAME, "COMMIT_HASH");
  char* version    = fo_sysconfig(AGENT_NAME, "VERSION");

  if (!commitHash || !version)
  {
    LOG_FATAL("Reuser: fo_sysconfig returned NULL for VERSION or COMMIT_HASH.");
    bail(1);
  }

  char* revision = nullptr;
  if (asprintf(&revision, "%s.%s", version, commitHash) < 0)
  {
    LOG_FATAL("Reuser: asprintf failed allocating revision string.");
    bail(1);
  }

  int agentId = fo_GetAgentKey(dbManager.getConnection(),
    AGENT_NAME, 0, revision, AGENT_DESC);
  free(revision);

  if (agentId <= 0)
    bail(1);

  return agentId;
}

int writeARS(const ReuserState& state, int arsId, int uploadId, int success,
  DbManager& dbManager)
{
  return fo_WriteARS(dbManager.getConnection(), arsId, uploadId,
    state.getAgentId(), AGENT_ARS, nullptr, success);
}

void bail(int exitval)
{
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

bool processUploadId(const ReuserState& /*state*/, int uploadId,
  ReuserDatabaseHandler& databaseHandler)
{
  int groupId = fo_scheduler_groupID();
  int userId  = fo_scheduler_userID();

  auto reusedUploads = databaseHandler.getReusedUploads(uploadId, groupId);

  for (const auto& triple : reusedUploads)
  {
    ItemTreeBounds boundsReused;
    if (!databaseHandler.getParentItemBounds(triple.reusedUploadId,
          boundsReused))
    {
      LOG_WARNING("Reuser: could not determine parent bounds for reused"
                  " upload %d – skipping.", triple.reusedUploadId);
      continue;
    }

    if (triple.reuseMode & REUSE_ENHANCED)
    {
      if (!databaseHandler.processEnhancedUploadReuse(
            uploadId, triple.reusedUploadId,
            groupId, triple.reusedGroupId, userId))
        return false;
    }
    else
    {
      if (!databaseHandler.processUploadReuse(
            uploadId, triple.reusedUploadId,
            groupId, triple.reusedGroupId, userId))
        return false;
    }

    // Failures are logged but do not abort the overall reuse run, matching
    // the original PHP behaviour where their return values were also not checked.
    if (triple.reuseMode & REUSE_MAIN)
    {
      if (!databaseHandler.reuseMainLicense(uploadId, groupId,
            triple.reusedUploadId, triple.reusedGroupId))
        LOG_WARNING("Reuser: reuseMainLicense failed for upload %d"
                    " (reused %d) – continuing.",
                    uploadId, triple.reusedUploadId);
    }

    if (triple.reuseMode & REUSE_CONF)
    {
      if (!databaseHandler.reuseConfSettings(uploadId, triple.reusedUploadId))
        LOG_WARNING("Reuser: reuseConfSettings failed for upload %d"
                    " (reused %d) – continuing.",
                    uploadId, triple.reusedUploadId);
    }

    if (triple.reuseMode & REUSE_COPYRIGHT)
    {
      if (!databaseHandler.reuseCopyrights(uploadId, triple.reusedUploadId,
            userId))
        LOG_WARNING("Reuser: reuseCopyrights failed for upload %d"
                    " (reused %d) – continuing.",
                    uploadId, triple.reusedUploadId);
    }
  }
  return true;
}
