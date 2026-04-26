/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @dir
 * @brief The reuser agent
 * @file
 * @brief Entry point for the reuser agent
 * @page reuser Reuser Agent
 * @tableofcontents
 *
 * The reuser agent copies clearing decisions, copyright events, main
 * licenses and report configuration from a previously cleared upload to a
 * new upload, based on pfile identity (standard reuse), file-name matching
 * with diff-threshold (enhanced reuse), or both.
 *
 * @section reuseractions Supported modes (set in upload_reuse.reuse_mode)
 * | Bit | Constant         | Description                          |
 * | --: | :--------------- | :----------------------------------- |
 * |   2 | REUSE_ENHANCED   | Match by filename + diff threshold   |
 * |   4 | REUSE_MAIN       | Copy main-license entries            |
 * |  16 | REUSE_CONF       | Copy report configuration            |
 * | 128 | REUSE_COPYRIGHT  | Copy copyright events                |
 *
 * @section reusersource Agent source
 *   - @link src/reuser/agent @endlink
 */

#include "ReuserUtils.hpp"

int main(int argc, char** argv)
{
  fo::DbManager dbManager(&argc, argv);
  ReuserDatabaseHandler databaseHandler(dbManager);

  ReuserState state = getState(dbManager);

  while (fo_scheduler_next() != nullptr)
  {
    int uploadId = atoi(fo_scheduler_current());
    if (uploadId == 0) continue;

    int arsId = writeARS(state, 0, uploadId, 0, dbManager);
    if (arsId <= 0)
      bail(5);

    if (!processUploadId(state, uploadId, databaseHandler))
      bail(2);

    fo_scheduler_heart(0);
    writeARS(state, arsId, uploadId, 1, dbManager);
  }

  fo_scheduler_heart(0);
  fo_scheduler_disconnect(0);
  return 0;
}
