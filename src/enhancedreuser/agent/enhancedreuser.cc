/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
/**
 * @dir
 * @brief The enhancedreuser agent
 * @file
 * @brief Entry point for the enhancedreuser
 * @page enhancedreuser EnhancedReuser
 * @tableofcontents
 *
 * The enhancedreuser copies clearing decisions from a previously cleared
 * upload to a new upload based on file-name matching combined with
 * diff-based change classification.  For each same-named file pair the
 * change between the reused and the target file is classified:
 *
 *   1. If kotoba has findings for both files, the license identity is
 *      authoritative: equal licenses -> copy, different licenses -> skip.
 *   2. Otherwise the unified diff is analysed together with the comment
 *      structure extracted by nirjas:
 *        - license identity changed (SPDX ids / license names / grant text) -> skip
 *        - copyright statement changed (year, holder, added/removed) -> copy
 *        - code-only change -> copy
 *        - license-preserving comment change -> copy
 *        - unclassifiable / diff error -> skip
 *
 * @section enhancedreuseractions Supported modes (set in upload_reuse.reuse_mode)
 * | Bit | Constant         | Description                          |
 * | --: | :--------------- | :----------------------------------- |
 * |   2 | REUSE_ENHANCED   | Match by filename + diff classification (enhancedreuser) |
 *
 * @section enhancedreusersource Agent source
 *   - @link src/enhancedreuser/agent @endlink
 */

#include "EnhancedReuserUtils.hpp"

int main(int argc, char** argv)
{
  fo::DbManager dbManager(&argc, argv);
  EnhancedReuserDatabaseHandler databaseHandler(dbManager);

  EnhancedReuserState state = getState(dbManager);

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

    std::string metricsJson = databaseHandler.getMetrics().toJson();
    writeARS(state, arsId, uploadId, 1, dbManager, metricsJson.c_str());
    printf("EnhancedReuser: metrics %s\n", metricsJson.c_str());
  }

  fo_scheduler_heart(0);
  fo_scheduler_disconnect(0);
  return 0;
}
