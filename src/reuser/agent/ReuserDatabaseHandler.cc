/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/

#include "ReuserDatabaseHandler.hpp"

#include <algorithm>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <set>
#include <sstream>
#include <sys/wait.h>

#include <unicode/unistr.h>

extern "C" {
#include "libfossagent.h"
}

using namespace fo;

// ── Construction ─────────────────────────────────────────────────────────────

ReuserDatabaseHandler::ReuserDatabaseHandler(DbManager dbManager)
  : AgentDatabaseHandler(dbManager)
{
}

ReuserDatabaseHandler ReuserDatabaseHandler::spawn() const
{
  return ReuserDatabaseHandler(dbManager.spawn());
}

// ── Private helpers ───────────────────────────────────────────────────────────

bool ReuserDatabaseHandler::isValidIdentifier(const std::string& s)
{
  if (s.empty()) return false;
  for (char c : s)
    if (!((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') ||
          (c >= '0' && c <= '9') || c == '_'))
      return false;
  return true;
}

std::string ReuserDatabaseHandler::replaceUnicodeControlChars(
  const std::string& input)
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

std::string ReuserDatabaseHandler::shellEscape(const std::string& s)
{
  std::string r = "'";
  for (char c : s)
    r += (c == '\'') ? std::string("'\\''") : std::string(1, c);
  r += "'";
  return r;
}

int ReuserDatabaseHandler::diffLineCount(const std::string& a,
  const std::string& b)
{
  if (a.empty() || b.empty()) return -1;

  // Run diff directly (no pipeline) so pclose() returns diff's own exit code.
  // Redirect diff's stderr to suppress "no such file" noise.
  std::string cmd = "diff -- " + shellEscape(a) + " " + shellEscape(b)
                  + " 2>/dev/null";
  FILE* pipe = popen(cmd.c_str(), "r");
  if (!pipe) return -1;

  int lines = 0;
  char buf[4096];
  while (fgets(buf, sizeof(buf), pipe))
    ++lines;

  int status = pclose(pipe);
  // diff exit codes: 0 = identical, 1 = differences found, 2 = error.
  if (WIFEXITED(status) && WEXITSTATUS(status) == 2)
    return -1;

  return lines;
}

std::string ReuserDatabaseHandler::getRepoPathOfPfile(int pfileId)
{
  char* pfileName =
    getPFileNameForFileId(static_cast<unsigned long>(pfileId));
  if (!pfileName) return {};
  char* filePath = fo_RepMkPath("files", pfileName);
  free(pfileName);
  if (!filePath) return {};
  std::string result(filePath);
  free(filePath);
  return result;
}

// ── Upload-tree helpers ───────────────────────────────────────────────────────

bool ReuserDatabaseHandler::getParentItemBounds(int uploadId,
  ItemTreeBounds& out)
{
  std::string table = queryUploadTreeTableName(uploadId);
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

// ── Reuse relationship queries ────────────────────────────────────────────────

std::vector<ReuseTriple> ReuserDatabaseHandler::getReusedUploads(
  int uploadId, int groupId)
{
  std::vector<ReuseTriple> result;

  QueryResult qr = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserGetReusedUploads",
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

std::map<int, int> ReuserDatabaseHandler::getClearingDecisionMapByPfile(
  int uploadId, int groupId)
{
  std::map<int, int> result;

  std::string table = queryUploadTreeTableName(uploadId);
  if (!isValidIdentifier(table)) return result;

  bool needsUploadFilter =
    (table == "uploadtree" || table == "uploadtree_a");

  // Determine whether global (REPO) decisions should be applied.
  bool applyGlobal = true;
  QueryResult globalQr = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserGetGlobalDecision",
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

  // decision_type 0 == WIP; scope 0 == ITEM takes priority over scope 1 == REPO.
  // The inner CTE picks the best decision per uploadtree_pk (ITEM before REPO,
  // newest first).  The outer DISTINCT ON(pfile_id) then picks one
  // uploadtree_pk per pfile deterministically (newest decision id wins),
  // matching PHP's mapByFileId(getFileClearingsFolder(...)) behaviour.
  QueryResult qr = dbManager.queryPrintf(
    "WITH per_item AS ("
    " SELECT DISTINCT ON(ut.uploadtree_pk)"
    "   cd.clearing_decision_pk AS id,"
    "   cd.pfile_fk AS pfile_id"
    " FROM clearing_decision cd"
    " INNER JOIN %s ut ON (%s)%s"
    " WHERE cd.decision_type != 0"
    " ORDER BY ut.uploadtree_pk, cd.scope ASC,"
    "          cd.clearing_decision_pk DESC"
    "),"
    " per_pfile AS ("
    " SELECT DISTINCT ON(pfile_id) id, pfile_id"
    " FROM per_item"
    " ORDER BY pfile_id, id DESC"
    ")"
    " SELECT id, pfile_id FROM per_pfile",
    table.c_str(), joinCond.c_str(), uploadFilter.c_str());

  for (int i = 0; i < qr.getRowCount(); ++i)
  {
    auto row    = qr.getRow(i);
    int  decId  = std::stoi(row[0]);
    int  pfileId = std::stoi(row[1]);
    if (pfileId > 0 && result.find(pfileId) == result.end())
      result[pfileId] = decId;
  }
  return result;
}

std::map<int, std::vector<int>>
ReuserDatabaseHandler::getUploadTreePksForPfiles(
  int uploadId, const std::vector<int>& pfileIds)
{
  std::map<int, std::vector<int>> result;
  if (pfileIds.empty()) return result;

  std::string table = queryUploadTreeTableName(uploadId);
  if (!isValidIdentifier(table)) return result;

  // Build PostgreSQL integer-array literal – all values are integers, safe.
  std::string arr;
  for (size_t i = 0; i < pfileIds.size(); ++i)
  {
    if (i > 0) arr += ",";
    arr += std::to_string(pfileIds[i]);
  }

  bool needsUploadFilter =
    (table == "uploadtree" || table == "uploadtree_a");

  QueryResult qr =
    needsUploadFilter
    ? dbManager.queryPrintf(
        "SELECT uploadtree_pk, pfile_fk FROM %s"
        " WHERE upload_fk=%d AND pfile_fk=ANY('{%s}'::int[])",
        table.c_str(), uploadId, arr.c_str())
    : dbManager.queryPrintf(
        "SELECT uploadtree_pk, pfile_fk FROM %s"
        " WHERE pfile_fk=ANY('{%s}'::int[])",
        table.c_str(), arr.c_str());

  for (int i = 0; i < qr.getRowCount(); ++i)
  {
    auto row    = qr.getRow(i);
    int  pk     = std::stoi(row[0]);
    int  pfileId = std::stoi(row[1]);
    if (pk > 0 && pfileId > 0)
      result[pfileId].push_back(pk);
  }
  return result;
}

// ── Clearing-decision operations ──────────────────────────────────────────────

int ReuserDatabaseHandler::insertClearingEvent(
  int uploadTreeId, int userId, int groupId,
  int licenseId, bool removed, int type,
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
        "reuserMarkDecisionAsWip",
        "INSERT INTO clearing_decision"
        " (uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope)"
        " VALUES ($1,"
        " (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk=$1),"
        " $2, $3, 0, 0)",
        int, int, int),
      uploadTreeId, userId, groupId);

    QueryResult qr = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
        "reuserInsertClearingEvent",
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
        "reuserInsertClearingEventWithJob",
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

int ReuserDatabaseHandler::createDecisionFromEvents(
  int uploadTreeId, int userId, int groupId,
  int decType, int scope, const std::vector<int>& eventIds)
{
  if (eventIds.empty()) return 0;

  if (!begin()) return 0;

  // Remove stale WIP decisions for this item/group.
  QueryResult rRem = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserRemoveWipDecision",
      "DELETE FROM clearing_decision"
      " WHERE uploadtree_fk=$1 AND group_fk=$2 AND decision_type=0",
      int, int),
    uploadTreeId, groupId);

  if (!rRem) { rollback(); return 0; }

  // Insert the new clearing_decision.
  QueryResult rIns = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserInsertClearingDecision",
      "INSERT INTO clearing_decision"
      " (uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope)"
      " VALUES ($1,"
      " (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk=$1),"
      " $2, $3, $4, $5)"
      " RETURNING clearing_decision_pk",
      int, int, int, int, int),
    uploadTreeId, userId, groupId, decType, scope);

  if (!rIns || rIns.getRowCount() == 0) { rollback(); return 0; }
  int decisionPk = std::stoi(rIns.getRow(0)[0]);

  // Link events to the new decision.
  // Former PHP's ClearingDao::createDecisionFromEvents did not check individual
  // insert results in the loop (freeResult without error check), so we match
  // that behaviour: log a warning on failure but continue and commit.
  auto* stmtLink = fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
    "reuserInsertClearingDecisionEvent",
    "INSERT INTO clearing_decision_event"
    " (clearing_decision_fk, clearing_event_fk) VALUES($1,$2)",
    int, int);

  for (int evPk : eventIds)
  {
    QueryResult rLink = dbManager.execPrepared(stmtLink, decisionPk, evPk);
    if (!rLink)
      LOG_WARNING("Reuser: failed to link clearing_event %d to"
                  " clearing_decision %d – continuing.", evPk, decisionPk);
  }

  if (!commit()) { rollback(); return 0; }
  return decisionPk;
}

int ReuserDatabaseHandler::createCopyOfClearingDecision(
  int newItemUploadTreePk, int userId, int groupId, int originalDecisionPk)
{
  // Fetch decision meta (type and scope).
  QueryResult rMeta = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserGetDecisionMeta",
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
      "reuserGetEventsForDecision",
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
    int  evPk     = insertClearingEvent(newItemUploadTreePk, userId, groupId,
                      rfFk, isRemoved, evType,
                      row[2], row[3], row[4], jobId);
    if (evPk > 0)
      newEventIds.push_back(evPk);
  }

  if (newEventIds.empty()) return 0;
  return createDecisionFromEvents(newItemUploadTreePk, userId, groupId,
    decType, scope, newEventIds);
}

// ── ARS record ────────────────────────────────────────────────────────────────

int ReuserDatabaseHandler::writeArsRecord(int agentId, int uploadId,
  int arsId, bool success)
{
  return fo_WriteARS(dbManager.getConnection(), arsId, uploadId, agentId,
    "reuser_ars", nullptr, success ? 1 : 0);
}

// ── Reuse operations ──────────────────────────────────────────────────────────

bool ReuserDatabaseHandler::processUploadReuse(
  int uploadId, int reusedUploadId,
  int groupId, int reusedGroupId, int userId)
{
  auto reusedMap  = getClearingDecisionMapByPfile(reusedUploadId, reusedGroupId);
  if (reusedMap.empty()) return true;

  auto currentMap = getClearingDecisionMapByPfile(uploadId, groupId);

  // Collect pfiles present in the reused upload but not yet cleared here.
  std::vector<int> toImport;
  for (const auto& kv : reusedMap)
    if (currentMap.find(kv.first) == currentMap.end())
      toImport.push_back(kv.first);

  if (toImport.empty()) return true;

  constexpr size_t chunkSize = 100;
  for (size_t i = 0; i < toImport.size(); i += chunkSize)
  {
    size_t end = std::min(i + chunkSize, toImport.size());
    std::vector<int> chunk(toImport.begin() + i, toImport.begin() + end);
    auto pkMap = getUploadTreePksForPfiles(uploadId, chunk);

    for (const auto& entry : pkMap)
    {
      int originalDecision = reusedMap.at(entry.first);
      for (int uploadtreePk : entry.second)
      {
        int newDecision = createCopyOfClearingDecision(
          uploadtreePk, userId, groupId, originalDecision);
        if (newDecision > 0)
          fo_scheduler_heart(1);
      }
    }
  }
  return true;
}

bool ReuserDatabaseHandler::processEnhancedUploadReuse(
  int uploadId, int reusedUploadId,
  int groupId, int reusedGroupId, int userId)
{
  auto reusedMap  = getClearingDecisionMapByPfile(reusedUploadId, reusedGroupId);
  if (reusedMap.empty()) return true;

  auto currentMap = getClearingDecisionMapByPfile(uploadId, groupId);

  std::vector<int> toImport;
  for (const auto& kv : reusedMap)
    if (currentMap.find(kv.first) == currentMap.end())
      toImport.push_back(kv.first);

  if (toImport.empty()) return true;

  for (int pfileFk : toImport)
  {
    int originalDecision = reusedMap.at(pfileFk);

    std::string reusedPath = getRepoPathOfPfile(pfileFk);
    if (reusedPath.empty()) continue;

    std::string tableReused = queryUploadTreeTableName(reusedUploadId);
    std::string tableTarget = queryUploadTreeTableName(uploadId);
    if (!isValidIdentifier(tableReused) || !isValidIdentifier(tableTarget))
      continue;

    bool reusedNeedsFilter = (tableReused == "uploadtree" || tableReused == "uploadtree_a");
    bool targetNeedsFilter = (tableTarget == "uploadtree" || tableTarget == "uploadtree_a");

    std::string reusedFilter = reusedNeedsFilter
      ? " AND ur.upload_fk=" + std::to_string(reusedUploadId) : "";
    std::string targetFilter = targetNeedsFilter
      ? " AND ut.upload_fk=" + std::to_string(uploadId) : "";

    // Find items in target upload with matching filename.
    QueryResult rr = dbManager.queryPrintf(
      "SELECT ut.uploadtree_pk, ut.pfile_fk"
      " FROM %s ur, %s ut"
      " WHERE ur.pfile_fk=%d%s"
      "   AND ut.ufile_name=ur.ufile_name%s",
      tableReused.c_str(), tableTarget.c_str(),
      pfileFk, reusedFilter.c_str(),
      targetFilter.c_str());

    for (int i = 0; i < rr.getRowCount(); ++i)
    {
      auto row      = rr.getRow(i);
      int  newItemPk  = std::stoi(row[0]);
      int  newPfileFk = std::stoi(row[1]);
      if (newItemPk <= 0 || newPfileFk <= 0) continue;

      std::string newPath = getRepoPathOfPfile(newPfileFk);
      if (newPath.empty()) continue;

      int diffCount = diffLineCount(reusedPath, newPath);
      if (diffCount < 0) return false; // diff itself failed – abort
      if (diffCount < 5)
      {
        int newDecision = createCopyOfClearingDecision(
          newItemPk, userId, groupId, originalDecision);
        if (newDecision > 0)
          fo_scheduler_heart(1);
      }
    }
  }
  return true;
}

bool ReuserDatabaseHandler::reuseMainLicense(
  int uploadId, int groupId, int reusedUploadId, int reusedGroupId)
{
  QueryResult r1 = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserGetMainLicenses",
      "SELECT rf_fk FROM upload_clearing_license"
      " WHERE upload_fk=$1 AND group_fk=$2",
      int, int),
    reusedUploadId, reusedGroupId);

  std::set<int> reusedSet;
  for (int i = 0; i < r1.getRowCount(); ++i)
    reusedSet.insert(std::stoi(r1.getRow(i)[0]));

  if (reusedSet.empty()) return true;

  QueryResult r2 = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserGetMainLicenses",
      "SELECT rf_fk FROM upload_clearing_license"
      " WHERE upload_fk=$1 AND group_fk=$2",
      int, int),
    uploadId, groupId);

  std::set<int> existingSet;
  for (int i = 0; i < r2.getRowCount(); ++i)
    existingSet.insert(std::stoi(r2.getRow(i)[0]));

  for (int rf : reusedSet)
  {
    if (existingSet.count(rf)) continue;
    QueryResult rIns = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
        "reuserInsertMainLicense",
        "INSERT INTO upload_clearing_license (upload_fk, group_fk, rf_fk)"
        " VALUES ($1,$2,$3)",
        int, int, int),
      uploadId, groupId, rf);
    if (!rIns) return false;
  }
  return true;
}

bool ReuserDatabaseHandler::reuseConfSettings(
  int uploadId, int reusedUploadId)
{
  // Check that the reused upload has a report_info row.
  QueryResult rCheck = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserConfSettingsCheck",
      "SELECT 1 FROM report_info WHERE upload_fk=$1 LIMIT 1",
      int),
    reusedUploadId);

  if (!rCheck || rCheck.getRowCount() == 0) return true;

  if (!begin()) return false;

  // Remove any existing report_info for the target upload.
  QueryResult rDel = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserConfSettingsDelete",
      "DELETE FROM report_info WHERE upload_fk=$1",
      int),
    uploadId);

  if (!rDel) { rollback(); return false; }

  // Dynamically discover columns (excluding pk and upload_fk).
  // quote_ident ensures safe embedding in the subsequent INSERT.
  QueryResult rCols = dbManager.queryPrintf(
    "SELECT string_agg(quote_ident(column_name), ',')"
    " FROM information_schema.columns"
    " WHERE table_name = 'report_info'"
    "   AND column_name != 'ri_pk'"
    "   AND column_name != 'upload_fk'");

  if (!rCols || rCols.getRowCount() == 0) { rollback(); return false; }
  std::string cols = rCols.getRow(0)[0];
  if (cols.empty()) { rollback(); return false; }

  // INSERT … SELECT copies all remaining columns from the reused upload.
  QueryResult rCopy = dbManager.queryPrintf(
    "INSERT INTO report_info(upload_fk, %s)"
    " SELECT %d, %s FROM report_info WHERE upload_fk=%d",
    cols.c_str(), uploadId, cols.c_str(), reusedUploadId);

  if (!rCopy) { rollback(); return false; }

  if (!commit()) { rollback(); return false; }
  return true;
}

bool ReuserDatabaseHandler::reuseCopyrights(
  int uploadId, int reusedUploadId, int userId)
{
  const std::string agentName = "copyright";

  // Resolve copyright agent id for both uploads.
  QueryResult rAgentT = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserCopyrightAgentId",
      "SELECT agent_pk AS agent_id FROM agent"
      " LEFT JOIN copyright_ars ON agent_fk=agent_pk"
      " WHERE agent_name=$2 AND agent_enabled"
      "   AND upload_fk=$1 AND ars_success"
      " ORDER BY agent_pk DESC LIMIT 1",
      int, char*),
    uploadId, agentName.c_str());

  if (!rAgentT || rAgentT.getRowCount() == 0) return true;
  int targetAgentId = std::stoi(rAgentT.getRow(0)[0]);

  QueryResult rAgentR = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserCopyrightAgentId",
      "SELECT agent_pk AS agent_id FROM agent"
      " LEFT JOIN copyright_ars ON agent_fk=agent_pk"
      " WHERE agent_name=$2 AND agent_enabled"
      "   AND upload_fk=$1 AND ars_success"
      " ORDER BY agent_pk DESC LIMIT 1",
      int, char*),
    reusedUploadId, agentName.c_str());

  if (!rAgentR || rAgentR.getRowCount() == 0) return true;
  int reusedAgentId = std::stoi(rAgentR.getRow(0)[0]);

  // Fetch existing copyright entries in the target upload, keyed by hash.
  std::string table = queryUploadTreeTableName(uploadId);
  if (!isValidIdentifier(table)) return true;

  bool needsUploadFilter = (table == "uploadtree" || table == "uploadtree_a");
  std::string uploadFilter = needsUploadFilter
    ? " AND UT.upload_fk = " + std::to_string(uploadId) : "";

  QueryResult rAll = dbManager.queryPrintf(
    "SELECT DISTINCT ON (C.copyright_pk, UT.uploadtree_pk)"
    "  C.copyright_pk, UT.uploadtree_pk, UT.upload_fk,"
    "  (CASE WHEN (CE.content IS NULL OR CE.content = '')"
    "        THEN C.content ELSE CE.content END) AS content,"
    "  (CASE WHEN (CE.hash IS NULL OR CE.hash = '')"
    "        THEN C.hash ELSE CE.hash END) AS hash"
    " FROM copyright C"
    " INNER JOIN %s UT ON C.pfile_fk = UT.pfile_fk%s"
    " LEFT JOIN copyright_event CE"
    "   ON CE.copyright_fk = C.copyright_pk"
    "  AND CE.upload_fk = %d"
    "  AND CE.uploadtree_fk = UT.uploadtree_pk"
    " WHERE C.content IS NOT NULL AND C.content <> ''"
    "   AND (CE.is_enabled IS NULL OR CE.is_enabled = 'true')"
    "   AND C.agent_fk = %d"
    " ORDER BY C.copyright_pk, UT.uploadtree_pk, content DESC",
    table.c_str(), uploadFilter.c_str(), uploadId, targetAgentId);

  // Index existing copyrights by hash.
  // hash → list of {copyright_pk, uploadtree_pk, upload_fk}
  using Row3 = std::array<int, 3>;
  std::map<std::string, std::vector<Row3>> allMap;
  for (int i = 0; i < rAll.getRowCount(); ++i)
  {
    auto row  = rAll.getRow(i);
    std::string hash = row[4];
    if (!hash.empty())
      allMap[hash].push_back(Row3{std::stoi(row[0]), std::stoi(row[1]),
                                   std::stoi(row[2])});
  }
  if (allMap.empty()) return true;

  // Fetch copyright events that were modified in the reused upload.
  // scope 1 == REPO (global events only).
  QueryResult rReused = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "reuserGetReusedCopyrightEvents",
      "SELECT C.copyright_pk, CE.is_enabled, C.hash,"
      "       CE.content AS contentedited"
      " FROM copyright_event CE"
      " INNER JOIN copyright C ON C.copyright_pk = CE.copyright_fk"
      " WHERE CE.upload_fk=$1 AND CE.scope=$3 AND C.agent_fk=$2",
      int, int, int),
    reusedUploadId, reusedAgentId, 1 /* REPO scope */);

  for (int i = 0; i < rReused.getRowCount(); ++i)
  {
    auto rRow    = rReused.getRow(i);
    std::string hash = rRow[2];
    if (hash.empty()) continue;

    auto it = allMap.find(hash);
    if (it == allMap.end() || it->second.empty()) continue;

    Row3  entry      = it->second.back();
    it->second.pop_back();
    int   copyrightPk  = entry[0];
    int   uploadtreePk = entry[1];
    int   uploadFk     = entry[2];
    bool  isEnabled    = fo::stringToBool(rRow[1].c_str());
    const std::string& contentEdited = rRow[3];

    // Check if a copyright_event already exists for this combination.
    QueryResult rExists = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
        "reuserCopyrightEventExists",
        "SELECT EXISTS("
        "  SELECT 1 FROM copyright_event"
        "  WHERE copyright_fk=$1 AND upload_fk=$2 AND uploadtree_fk=$3"
        ")::int",
        int, int, int),
      copyrightPk, uploadFk, uploadtreePk);

    bool eventExists = rExists && rExists.getRowCount() > 0
                    && std::stoi(rExists.getRow(0)[0]) != 0;

    // Former PHP's CopyrightDao::updateTable() called getSingleRow() without
    // checking the return value, and reuseCopyrights() always returned true
    // regardless. We match that behaviour: log a warning on failure but continue.
    if (!isEnabled)
    {
      QueryResult rWrite =
        eventExists
        ? dbManager.execPrepared(
            fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
              "reuserCopyrightEventDisableUpdate",
              "UPDATE copyright_event SET scope=$4, is_enabled=false"
              " WHERE upload_fk=$1 AND copyright_fk=$2 AND uploadtree_fk=$3",
              int, int, int, int),
            uploadFk, copyrightPk, uploadtreePk, 1)
        : dbManager.execPrepared(
            fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
              "reuserCopyrightEventDisableInsert",
              "INSERT INTO copyright_event"
              " (upload_fk, copyright_fk, uploadtree_fk, is_enabled, scope)"
              " VALUES($1,$2,$3,'f',$4)",
              int, int, int, int),
            uploadFk, copyrightPk, uploadtreePk, 1);
      if (!rWrite)
        LOG_WARNING("Reuser: failed to disable copyright_event"
                    " (copyright_fk=%d, uploadtree_fk=%d) – continuing.",
                    copyrightPk, uploadtreePk);
    }
    else
    {
      QueryResult rWrite =
        eventExists
        ? dbManager.execPrepared(
            fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
              "reuserCopyrightEventUpdateContent",
              "UPDATE copyright_event SET upload_fk=$1, content=$4,"
              " hash=md5($4)"
              " WHERE copyright_fk=$2 AND uploadtree_fk=$3",
              int, int, int, char*),
            uploadFk, copyrightPk, uploadtreePk, contentEdited.c_str())
        : dbManager.execPrepared(
            fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
              "reuserCopyrightEventInsertContent",
              "INSERT INTO copyright_event"
              " (upload_fk, uploadtree_fk, copyright_fk,"
              "  is_enabled, content, hash)"
              " VALUES($1,$3,$2,'true',$4,md5($4))",
              int, int, int, char*),
            uploadFk, copyrightPk, uploadtreePk, contentEdited.c_str());
      if (!rWrite)
        LOG_WARNING("Reuser: failed to update copyright_event content"
                    " (copyright_fk=%d, uploadtree_fk=%d) – continuing.",
                    copyrightPk, uploadtreePk);
    }
  }
  return true;
}
