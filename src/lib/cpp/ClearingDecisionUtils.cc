/*
 SPDX-License-Identifier: GPL-2.0-only
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
 */

#include "ClearingDecisionUtils.hpp"

#include <cstdlib>

#include <unicode/unistr.h>

#include "libfossologyCPP.hpp"

namespace fo
{
namespace ClearingDecisionUtils
{

namespace
{

/** @brief Upload-tree table name for @p uploadId (same as AgentDatabaseHandler). */
std::string queryUploadTreeTableName(const DbManager& dbManager, int uploadId)
{
  return std::string(getUploadTreeTableName(dbManager.getStruct_dbManager(),
    uploadId));
}

} // namespace

bool isValidIdentifier(const std::string& s)
{
  if (s.empty()) return false;
  for (char c : s)
    if (!((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') ||
          (c >= '0' && c <= '9') || c == '_'))
      return false;
  return true;
}

std::string replaceUnicodeControlChars(const std::string& input)
{
  icu::UnicodeString us = icu::UnicodeString::fromUTF8(input);
  icu::UnicodeString result;
  for (int32_t i = 0; i < us.length(); ++i)
  {
    UChar32 cp = us.char32At(i);
    if (cp > 0xFFFF) ++i; // surrogate pair: char32At already consumed 2 units
    bool isControl = (cp <= 0x08)
                  || (cp == 0x0B)
                  || (cp == 0x0C)
                  || (cp >= 0x0E && cp <= 0x1F)
                  || (cp >= 0x7F && cp <= 0x9F);
    if (!isControl)
      result.append(cp);
  }
  std::string out;
  result.toUTF8String(out);
  return out;
}

int getDecisionTypePriority(int decisionType)
{
  switch (decisionType)
  {
    case 5: return 5;  // IDENTIFIED
    case 6: return 4;  // DO_NOT_USE
    case 7: return 3;  // NON_FUNCTIONAL
    case 4: return 2;  // IRRELEVANT
    case 3: return 1;  // TO_BE_DISCUSSED
    default: return 0; // WIP and others
  }
}

std::string getRepoPathOfPfile(const DbManager& dbManager, int pfileId)
{
  char* pfileName = queryPFileForFileId(dbManager.getStruct_dbManager(),
    static_cast<unsigned long>(pfileId));
  if (!pfileName) return {};
  char* filePath = fo_RepMkPath("files", pfileName);
  free(pfileName);
  if (!filePath) return {};
  std::string result(filePath);
  free(filePath);
  return result;
}

bool getParentItemBounds(const DbManager& dbManager, int uploadId,
  ItemTreeBounds& out)
{
  std::string table = queryUploadTreeTableName(dbManager, uploadId);
  if (!isValidIdentifier(table)) return false;

  bool needsUploadFilter =
    (table == "uploadtree" || table == "uploadtree_a");

  QueryResult result =
    needsUploadFilter
    ? dbManager.queryPrintf(
        "SELECT uploadtree_pk, upload_fk, lft, rgt"
        " FROM %s WHERE parent IS NULL AND upload_fk=%d",
        table.c_str(), uploadId)
    : dbManager.queryPrintf(
        "SELECT uploadtree_pk, upload_fk, lft, rgt"
        " FROM %s WHERE parent IS NULL",
        table.c_str());

  if (!result || result.getRowCount() == 0) return false;

  auto row = result.getRow(0);
  out.uploadtree_pk       = std::stoi(row[0]);
  out.uploadTreeTableName = table;
  out.upload_fk           = std::stoi(row[1]);
  out.lft                 = std::stoi(row[2]);
  out.rgt                 = std::stoi(row[3]);
  return true;
}

std::vector<ReuseTriple> getReusedUploads(const DbManager& dbManager,
  int uploadId, int groupId)
{
  std::vector<ReuseTriple> result;

  QueryResult qr = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "cduGetReusedUploads",
      "SELECT reused_upload_fk, reused_group_fk, reuse_mode"
      " FROM upload_reuse"
      " WHERE upload_fk=$1 AND group_fk=$2"
      " ORDER BY date_added DESC",
      int, int),
    uploadId, groupId);

  for (int i = 0; i < qr.getRowCount(); ++i)
  {
    auto row = qr.getRow(i);
    result.push_back({std::stoi(row[0]), std::stoi(row[1]),
                      std::stoi(row[2])});
  }
  return result;
}

std::map<int, int> getClearingDecisionMapByPfile(const DbManager& dbManager,
  int uploadId, int groupId)
{
  std::map<int, int> result;

  std::string table = queryUploadTreeTableName(dbManager, uploadId);
  if (!isValidIdentifier(table)) return result;

  bool needsUploadFilter =
    (table == "uploadtree" || table == "uploadtree_a");

  // Determine whether global (REPO) decisions should be applied.
  bool applyGlobal = true;
  QueryResult globalQr = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "cduGetGlobalDecision",
      // Cast int2 to boolean so PostgreSQL returns 't'/'f' regardless of storage
      // format ('1'/'0' vs 'true'/'false'); stringToBool only recognises 't'/'true'.
      "SELECT (ri_globaldecision != 0) FROM report_info WHERE upload_fk=$1",
      int),
    uploadId);
  if (globalQr && globalQr.getRowCount() > 0)
    applyGlobal = fo::stringToBool(globalQr.getRow(0)[0].c_str());

  // Build JOIN condition (no user input embedded – only integers).
  // When applyGlobal is false, PHP's ClearingDao::getRelevantDecisionsCte also
  // does NOT add cd.scope = 0 – it only switches from pfile_fk to uploadtree_fk
  // matching.  We intentionally mirror that behaviour: scope=1 (REPO) rows whose
  // uploadtree_fk happens to match a concrete item would also be included, exactly
  // as they would be in PHP.  Adding "AND cd.scope = 0" here would diverge from PHP.
  std::string joinCond =
    applyGlobal
    ? "(ut.pfile_fk = cd.pfile_fk AND cd.scope = 1)"
      " OR (ut.uploadtree_pk = cd.uploadtree_fk"
      " AND cd.scope = 0 AND cd.group_fk = " + std::to_string(groupId) + ")"
    : "(ut.uploadtree_pk = cd.uploadtree_fk"
      " AND cd.group_fk = " + std::to_string(groupId) + ")";

  std::string uploadFilter =
    needsUploadFilter
    ? " AND ut.upload_fk = " + std::to_string(uploadId)
    : "";

  // The inner CTE picks the best decision per uploadtree_pk (ITEM before REPO,
  // newest first).  The outer CTE preserves all rows so that when the same
  // pfile has different decisions at different locations, priority-based
  // conflict resolution (via getDecisionTypePriority) can choose the stronger
  // decision.
  QueryResult qr = dbManager.queryPrintf(
    "WITH per_item AS ("
    " SELECT DISTINCT ON(ut.uploadtree_pk)"
    "   cd.clearing_decision_pk AS id,"
    "   cd.pfile_fk AS pfile_id,"
    "   cd.decision_type AS dec_type"
    " FROM clearing_decision cd"
    " INNER JOIN %s ut ON (%s)%s"
    " WHERE cd.decision_type != 0"
    " ORDER BY ut.uploadtree_pk, cd.scope ASC,"
    "          cd.clearing_decision_pk DESC"
    "),"
    " per_pfile AS ("
    " SELECT id, pfile_id, dec_type"
    " FROM per_item"
    " ORDER BY pfile_id, id DESC"
    ")"
    " SELECT id, pfile_id, dec_type FROM per_pfile",
    table.c_str(), joinCond.c_str(), uploadFilter.c_str());

  std::map<int, int> resultTypes;

  for (int i = 0; i < qr.getRowCount(); ++i)
  {
    auto row     = qr.getRow(i);
    int  decId   = std::stoi(row[0]);
    int  pfileId = std::stoi(row[1]);
    int  decType = std::stoi(row[2]);
    if (pfileId > 0) {
      auto it = result.find(pfileId);
      if (it == result.end()) {
        result[pfileId] = decId;
        resultTypes[pfileId] = decType;
      } else if (getDecisionTypePriority(decType) >
                 getDecisionTypePriority(resultTypes[pfileId])) {
        printf("INFO :: Detected conflicting decisions for the same pfile."
               " Applying the stronger decision.\n");
        result[pfileId] = decId;
        resultTypes[pfileId] = decType;
      }
    }
  }
  return result;
}

int insertClearingEvent(const DbManager& dbManager, int uploadTreeId,
  int userId, int groupId, int licenseId, bool removed, int type,
  const std::string& reportInfo, const std::string& comment,
  const std::string& ack, int jobId)
{
  // Strip Unicode control characters (mirrors PHP StringOperation).
  std::string safeReport  = replaceUnicodeControlChars(reportInfo);
  std::string safeComment = replaceUnicodeControlChars(comment);
  std::string safeAck     = replaceUnicodeControlChars(ack);
  const char* removedStr  = removed ? "t" : "f";

  if (jobId <= 0)
  {
    // Mark existing decision as WIP first (mirrors ClearingDao::markDecisionAsWip).
    // DecisionTypes::WIP = 0, DecisionScopes::ITEM = 0
    dbManager.execPrepared(
      fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
        "cduMarkDecisionAsWip",
        "INSERT INTO clearing_decision"
        " (uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope)"
        " VALUES ($1,"
        " (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk=$1),"
        " $2, $3, 0, 0)",
        int, int, int),
      uploadTreeId, userId, groupId);

    QueryResult qr = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
        "cduInsertClearingEvent",
        "INSERT INTO clearing_event"
        " (uploadtree_fk, user_fk, group_fk, type_fk, rf_fk,"
        "  removed, reportinfo, comment, acknowledgement)"
        " VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)"
        " RETURNING clearing_event_pk",
        int, int, int, int, int, char*, char*, char*, char*),
      uploadTreeId, userId, groupId, type, licenseId,
      removedStr, safeReport.c_str(), safeComment.c_str(), safeAck.c_str());

    if (!qr || qr.getRowCount() == 0) return 0;
    return std::stoi(qr.getRow(0)[0]);
  }
  else
  {
    QueryResult qr = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
        "cduInsertClearingEventWithJob",
        "INSERT INTO clearing_event"
        " (uploadtree_fk, user_fk, group_fk, type_fk, rf_fk,"
        "  removed, reportinfo, comment, acknowledgement, job_fk)"
        " VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)"
        " RETURNING clearing_event_pk",
        int, int, int, int, int, char*, char*, char*, char*, int),
      uploadTreeId, userId, groupId, type, licenseId,
      removedStr, safeReport.c_str(), safeComment.c_str(), safeAck.c_str(),
      jobId);

    if (!qr || qr.getRowCount() == 0) return 0;
    return std::stoi(qr.getRow(0)[0]);
  }
}

int createDecisionFromEvents(const DbManager& dbManager, int uploadTreeId,
  int userId, int groupId, int decType, int scope,
  const std::vector<int>& eventIds)
{
  if (eventIds.empty()) return 0;

  if (!dbManager.begin()) return 0;

  // Remove stale WIP decisions for this item/group.
  QueryResult rRem = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "cduRemoveWipDecision",
      "DELETE FROM clearing_decision"
      " WHERE uploadtree_fk=$1 AND group_fk=$2 AND decision_type=0",
      int, int),
    uploadTreeId, groupId);

  if (!rRem) { dbManager.rollback(); return 0; }

  // Insert the new clearing_decision.
  QueryResult rIns = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "cduInsertClearingDecision",
      "INSERT INTO clearing_decision"
      " (uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope)"
      " VALUES ($1,"
      " (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk=$1),"
      " $2, $3, $4, $5)"
      " RETURNING clearing_decision_pk",
      int, int, int, int, int),
    uploadTreeId, userId, groupId, decType, scope);

  if (!rIns || rIns.getRowCount() == 0) { dbManager.rollback(); return 0; }
  int decisionPk = std::stoi(rIns.getRow(0)[0]);

  // Link events to the new decision.
  // Former PHP's ClearingDao::createDecisionFromEvents did not check individual
  // insert results in the loop (freeResult without error check), so we match
  // that behaviour: log a warning on failure but continue and commit.
  auto* stmtLink = fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
    "cduInsertClearingDecisionEvent",
    "INSERT INTO clearing_decision_event"
    " (clearing_decision_fk, clearing_event_fk) VALUES($1,$2)",
    int, int);

  for (int evPk : eventIds)
  {
    QueryResult rLink = dbManager.execPrepared(stmtLink, decisionPk, evPk);
    if (!rLink)
      LOG_WARNING("failed to link clearing_event %d to"
                  " clearing_decision %d – continuing.", evPk, decisionPk);
  }

  if (!dbManager.commit()) { dbManager.rollback(); return 0; }
  return decisionPk;
}

int createCopyOfClearingDecision(const DbManager& dbManager,
  int newItemUploadTreePk, int userId, int groupId, int originalDecisionPk)
{
  // Fetch decision meta (type and scope).
  QueryResult rMeta = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "cduGetDecisionMeta",
      "SELECT decision_type, scope FROM clearing_decision"
      " WHERE clearing_decision_pk=$1",
      int),
    originalDecisionPk);

  if (!rMeta || rMeta.getRowCount() == 0) return 0;
  int decType = std::stoi(rMeta.getRow(0)[0]);
  int scope   = std::stoi(rMeta.getRow(0)[1]);

  // Fetch the clearing events linked to the original decision.
  // Note: type_fk is intentionally not reused – copies always use USER type (1).
  //       job_fk is not reused – the copy is linked to the current scheduler job.
  QueryResult rEvents = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "cduGetEventsForDecision",
      "SELECT ce.rf_fk, ce.removed,"
      "       ce.reportinfo, ce.comment, ce.acknowledgement"
      " FROM clearing_event ce"
      " INNER JOIN clearing_decision_event cde"
      "   ON cde.clearing_event_fk = ce.clearing_event_pk"
      " WHERE cde.clearing_decision_fk=$1"
      " ORDER BY ce.clearing_event_pk ASC",
      int),
    originalDecisionPk);

  if (!rEvents) return 0;

  int jobId = fo_scheduler_jobId();
  std::vector<int> newEventIds;

  for (int i = 0; i < rEvents.getRowCount(); ++i)
  {
    auto row      = rEvents.getRow(i);
    int  rfFk     = std::stoi(row[0]);
    bool isRemoved = (row[1] == "t" || row[1] == "true");
    // Always use USER type (1) for copied events – mirrors PHP behavior.
    int  evType   = 1;
    int  evPk     = insertClearingEvent(dbManager, newItemUploadTreePk, userId,
                      groupId, rfFk, isRemoved, evType,
                      row[2], row[3], row[4], jobId);
    if (evPk > 0)
      newEventIds.push_back(evPk);
  }

  if (newEventIds.empty()) return 0;
  return createDecisionFromEvents(dbManager, newItemUploadTreePk, userId,
    groupId, decType, scope, newEventIds);
}

} // namespace ClearingDecisionUtils
} // namespace fo
