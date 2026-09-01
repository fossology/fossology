/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/

#include "EnhancedReuserDatabaseHandler.hpp"
#include "CommentExtractor.hpp"
#include "DiffClassifier.hpp"
#include "KotobaChecker.hpp"
#include "NomosChecker.hpp"
#include "OjoChecker.hpp"

#include <algorithm>
#include <cstdio>
#include <cstdlib>
#include <map>
#include <set>

extern "C" {
#include "libfossagent.h"
}

using namespace fo;

namespace
{

/**
 * @brief Relative path of an uploadtree item below the upload's root item,
 *        skipping artifact-mode components (ufile_mode bit 1<<28), mirroring
 *        PHP's Dir2Path()/Isartifact(). Used to require the reused and
 *        target items to sit at the same location, not just share a name.
 *        Returns "" for the root item itself or on a lookup error.
 */
std::string relativePathOf(const DbManager& dbManager, const std::string& table,
  int topUploadtreePk, int uploadtreePk, std::map<int, std::string>& cache)
{
  if (uploadtreePk <= 0 || uploadtreePk == topUploadtreePk) return "";

  auto cached = cache.find(uploadtreePk);
  if (cached != cache.end()) return cached->second;

  std::vector<std::string> components;
  int current = uploadtreePk;
  for (int hops = 0; hops < 1000 && current > 0 && current != topUploadtreePk; ++hops)
  {
    QueryResult qr = dbManager.queryPrintf(
      "SELECT parent, ufile_mode, ufile_name FROM %s WHERE uploadtree_pk=%d",
      table.c_str(), current);
    if (!qr || qr.getRowCount() == 0) { components.clear(); current = 0; break; }
    auto row    = qr.getRow(0);
    int  parent = row[0].empty() ? 0 : std::stoi(row[0]);
    long mode   = row[1].empty() ? 0 : std::stol(row[1]);
    if ((mode & (1L << 28)) == 0) // not an artifact entry
      components.push_back(row[2]);
    current = parent;
  }

  std::string path;
  for (auto it = components.rbegin(); it != components.rend(); ++it)
  {
    if (!path.empty()) path += '/';
    path += *it;
  }
  return cache.emplace(uploadtreePk, std::move(path)).first->second;
}

/**
 * @brief Uploadtree_pk of the first real branch point below @p rootUploadtreePk,
 *        skipping the leading chain of wrapper entries above the package
 *        content: synthetic artifact dirs ("artifact.dir"/"artifact.unpacked"
 *        created by the unpack agent, PHP Isartifact()) and directories that
 *        each wrap exactly one child (e.g. how "foo-1.2.3.tar.gz" unpacks into
 *        "foo-1.2.3.tar" into a single version-named top directory
 *        "foo-1.2.3"). Comparing paths relative to this boundary rather than
 *        the literal tree root means two uploads of different versions of the
 *        same package, whose wrapper names differ ("nltk-3.10.0" vs
 *        "nltk-3.9.3"), still line up on their real content
 *        ("nltk/app/chartparser_app.py").
 */
int findPathBoundary(const DbManager& dbManager, const std::string& table,
  int rootUploadtreePk)
{
  int current = rootUploadtreePk;
  for (int hops = 0; hops < 1000; ++hops)
  {
    QueryResult qr = dbManager.queryPrintf(
      "SELECT uploadtree_pk, ufile_name, ufile_mode FROM %s WHERE parent=%d",
      table.c_str(), current);
    if (!qr || qr.getRowCount() == 0) break; // leaf

    int  realChild = 0;
    int  wrapChild = 0;
    bool branch    = false;
    for (int i = 0; i < qr.getRowCount(); ++i)
    {
      auto row = qr.getRow(i);
      if (row.size() < 3) continue;
      long mode = row[2].empty() ? 0 : std::stol(row[2]);
      if ((mode & (1L << 28)) == 0)
      {
        if (realChild) branch = true; // a second real child
        else realChild = std::stoi(row[0]);
      }
      else if (row[1] == "artifact.dir" || row[1] == "artifact.unpacked")
      {
        if (!wrapChild) wrapChild = std::stoi(row[0]);
      }
    }

    if (branch)          break;                // real branch point
    if (realChild)       current = realChild;  // single real child
    else if (wrapChild)  current = wrapChild;  // single artifact wrapper chain
    else                 break;
  }
  return current;
}

} // namespace

// ── Construction ─────────────────────────────────────────────────────────────

EnhancedReuserDatabaseHandler::EnhancedReuserDatabaseHandler(DbManager dbManager)
  : AgentDatabaseHandler(dbManager)
{
}

EnhancedReuserDatabaseHandler EnhancedReuserDatabaseHandler::spawn() const
{
  return EnhancedReuserDatabaseHandler(dbManager.spawn());
}

// ── Upload-tree helpers ───────────────────────────────────────────────────────

bool EnhancedReuserDatabaseHandler::getParentItemBounds(int uploadId,
  ItemTreeBounds& out)
{
  return ClearingDecisionUtils::getParentItemBounds(dbManager, uploadId, out);
}

// ── Reuse relationship queries ────────────────────────────────────────────────

std::vector<ReuseTriple> EnhancedReuserDatabaseHandler::getReusedUploads(
  int uploadId, int groupId)
{
  return ClearingDecisionUtils::getReusedUploads(dbManager, uploadId, groupId);
}

std::map<int, int> EnhancedReuserDatabaseHandler::getClearingDecisionMapByPfile(
  int uploadId, int groupId)
{
  return ClearingDecisionUtils::getClearingDecisionMapByPfile(dbManager,
    uploadId, groupId);
}

// ── Clearing-decision operations ──────────────────────────────────────────────

int EnhancedReuserDatabaseHandler::insertClearingEvent(
  int uploadTreeId, int userId, int groupId,
  int licenseId, bool removed, int type,
  const std::string& reportInfo, const std::string& comment,
  const std::string& ack, int jobId)
{
  return ClearingDecisionUtils::insertClearingEvent(dbManager, uploadTreeId,
    userId, groupId, licenseId, removed, type, reportInfo, comment, ack,
    jobId);
}

int EnhancedReuserDatabaseHandler::createDecisionFromEvents(
  int uploadTreeId, int userId, int groupId,
  int decType, int scope, const std::vector<int>& eventIds)
{
  return ClearingDecisionUtils::createDecisionFromEvents(dbManager,
    uploadTreeId, userId, groupId, decType, scope, eventIds);
}

int EnhancedReuserDatabaseHandler::createCopyOfClearingDecision(
  int newItemUploadTreePk, int userId, int groupId, int originalDecisionPk)
{
  return ClearingDecisionUtils::createCopyOfClearingDecision(dbManager,
    newItemUploadTreePk, userId, groupId, originalDecisionPk);
}

// ── Diff-based change classification reuse ─────────────────────────────────

std::string EnhancedReuserDatabaseHandler::Metrics::toJson() const
{
  return "{\"pathMismatch\":" + std::to_string(pathMismatch)
       + ",\"kotobaMatched\":" + std::to_string(kotobaMatched)
       + ",\"kotobaSkipped\":" + std::to_string(kotobaSkipped)
       + ",\"nomosTextChanged\":" + std::to_string(nomosTextChanged)
       + ",\"ojoSpdxChanged\":" + std::to_string(ojoSpdxChanged)
       + ",\"licenseSame\":" + std::to_string(licenseSame)
       + ",\"licenseChanged\":" + std::to_string(licenseChanged)
       + ",\"copyrightChange\":" + std::to_string(copyrightChange)
       + ",\"codeChange\":" + std::to_string(codeChange)
       + ",\"codeAndCopyrightChange\":" + std::to_string(codeAndCopyrightChange)
       + ",\"diffError\":" + std::to_string(diffError)
       + ",\"diffSkipped\":" + std::to_string(diffSkipped)
       + ",\"copyFailed\":" + std::to_string(copyFailed) + "}";
}

// ── Kotoba-based reuse ──────────────────────────────────────────────────────

bool EnhancedReuserDatabaseHandler::processEnhancedUploadReuse(
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

  if (toImport.empty())
  {
    printf("EnhancedReuser: no new pfiles to process (standard reuse already"
           " handled all decision-carrying pfiles on upload %d).\n",
           uploadId);
    return true;
  }

  printf("EnhancedReuser: processing %zu pfiles with diff-based change"
         " classification for upload %d ← %d\n",
         toImport.size(), uploadId, reusedUploadId);

  // Path verification is anchored below each tree's leading chain of wrapper
  // entries (archive name, unpacked archive name, versioned top directory,
  // synthetic artifact dirs, ...) rather than the literal tree root, so a
  // same-named item at the same *structural* location can be told apart
  // from a same-named item that merely lives elsewhere in the tree.
  ItemTreeBounds boundsReused, boundsTarget;
  if (!getParentItemBounds(reusedUploadId, boundsReused) ||
      !getParentItemBounds(uploadId, boundsTarget))
  {
    printf("EnhancedReuser: could not determine upload-tree bounds for path"
           " verification (upload %d ← %d); skipping.\n",
           uploadId, reusedUploadId);
    return true;
  }
  std::string reusedRootTable = queryUploadTreeTableName(reusedUploadId);
  std::string targetRootTable = queryUploadTreeTableName(uploadId);
  if (!ClearingDecisionUtils::isValidIdentifier(reusedRootTable) ||
      !ClearingDecisionUtils::isValidIdentifier(targetRootTable))
  {
    printf("EnhancedReuser: invalid upload-tree table name for path"
           " verification (upload %d ← %d); skipping.\n",
           uploadId, reusedUploadId);
    return true;
  }
  int reusedPathBoundary = findPathBoundary(dbManager, reusedRootTable,
    boundsReused.uploadtree_pk);
  int targetPathBoundary = findPathBoundary(dbManager, targetRootTable,
    boundsTarget.uploadtree_pk);
  std::map<int, std::string> reusedPathCache;
  std::map<int, std::string> targetPathCache;

  // Kotoba license oracle for the reused upload (batched).  When kotoba has
  // findings on BOTH sides the license identity is authoritative and the diff
  // can be skipped entirely.
  std::map<int, std::vector<std::string>> reusedKotoba =
    getKotobaShortnamesByPfile(dbManager, reusedUploadId, toImport);

  // Nomos and ojo oracles for the reused upload (batched), same shape as
  // the kotoba oracle above.  The nomos gate additionally requires a
  // successful nomos run on BOTH uploads before it trusts any finding.
  std::map<int, std::vector<std::string>> reusedNomosLicenses =
    getNomosLicensesByPfile(dbManager, reusedUploadId, toImport);
  std::map<int, std::vector<NomosRegion>> reusedNomosRegions =
    getNomosRegionsByPfile(dbManager, reusedUploadId, toImport);
  std::map<int, std::set<std::string>> reusedOjoSpdx =
    getOjoSpdxIdsByPfile(dbManager, reusedUploadId, toImport);
  bool nomosRanOnReused = nomosRanOnUpload(dbManager, reusedUploadId);
  bool nomosRanOnTarget = nomosRanOnUpload(dbManager, uploadId);

  // Nirjas runs as a separate Python process per call and dominates the
  // runtime.  Its output only depends on the file content (repo path) and the
  // extension of the supplied name, so memoise by (repo path, basename).  This
  // avoids re-scanning the same reused file for every matching target item and
  // re-scanning target files whose content appears at several paths.
  std::map<std::string, NirjasOutput> nirjasCache;
  auto commentsFor = [&nirjasCache](const std::string& path,
    const std::string& fileName) -> const NirjasOutput&
  {
    std::string key = path + "\x1f";
    size_t slash = fileName.find_last_of('/');
    key += (slash == std::string::npos) ? fileName : fileName.substr(slash + 1);
    auto it = nirjasCache.find(key);
    if (it != nirjasCache.end()) return it->second;
    return nirjasCache.emplace(key, extractComments(path, fileName)).first->second;
  };

  // Target-side kotoba findings are queried per pfile and memoised (the same
  // pfile can appear at several paths in the target upload).
  std::map<int, std::vector<std::string>> targetKotobaCache;
  auto kotobaFor = [this, uploadId, &targetKotobaCache](int pfileFk)
    -> const std::vector<std::string>&
  {
    static const std::vector<std::string> none;
    auto it = targetKotobaCache.find(pfileFk);
    if (it != targetKotobaCache.end()) return it->second;
    auto findings = getKotobaShortnamesByPfile(dbManager, uploadId, {pfileFk});
    return targetKotobaCache.emplace(pfileFk, std::move(findings[pfileFk]))
      .first->second;
  };

  std::map<int, std::vector<NomosRegion>> targetNomosCache;
  auto nomosRegionsFor = [this, uploadId, &targetNomosCache](int pfileFk)
    -> const std::vector<NomosRegion>&
  {
    static const std::vector<NomosRegion> none;
    auto it = targetNomosCache.find(pfileFk);
    if (it != targetNomosCache.end()) return it->second;
    auto findings = getNomosRegionsByPfile(dbManager, uploadId, {pfileFk});
    return targetNomosCache.emplace(pfileFk, std::move(findings[pfileFk]))
      .first->second;
  };

  std::map<int, std::vector<std::string>> targetNomosLicensesCache;
  auto nomosLicensesFor = [this, uploadId, &targetNomosLicensesCache](int pfileFk)
    -> const std::vector<std::string>&
  {
    static const std::vector<std::string> none;
    auto it = targetNomosLicensesCache.find(pfileFk);
    if (it != targetNomosLicensesCache.end()) return it->second;
    auto findings = getNomosLicensesByPfile(dbManager, uploadId, {pfileFk});
    return targetNomosLicensesCache.emplace(pfileFk, std::move(findings[pfileFk]))
      .first->second;
  };

  std::map<int, std::set<std::string>> targetOjoCache;
  auto ojoSpdxFor = [this, uploadId, &targetOjoCache](int pfileFk)
    -> const std::set<std::string>&
  {
    static const std::set<std::string> none;
    auto it = targetOjoCache.find(pfileFk);
    if (it != targetOjoCache.end()) return it->second;
    auto findings = getOjoSpdxIdsByPfile(dbManager, uploadId, {pfileFk});
    return targetOjoCache.emplace(pfileFk, std::move(findings[pfileFk]))
      .first->second;
  };

  auto applyDecision = [this](int newItemPk, int userId, int groupId,
    int originalDecision) -> bool
  {
    int newDecision = createCopyOfClearingDecision(
      newItemPk, userId, groupId, originalDecision);
    if (newDecision > 0) return true;
    ++metrics_.copyFailed;
    return false;
  };

  for (int pfileFk : toImport)
  {
    int originalDecision = reusedMap.at(pfileFk);

    std::string reusedPath = ClearingDecisionUtils::getRepoPathOfPfile(
      dbManager, pfileFk);
    if (reusedPath.empty()) continue;

    std::string tableReused = queryUploadTreeTableName(reusedUploadId);
    std::string tableTarget = queryUploadTreeTableName(uploadId);
    if (!ClearingDecisionUtils::isValidIdentifier(tableReused) ||
        !ClearingDecisionUtils::isValidIdentifier(tableTarget))
      continue;

    bool reusedNeedsFilter = (tableReused == "uploadtree" || tableReused == "uploadtree_a");
    bool targetNeedsFilter = (tableTarget == "uploadtree" || tableTarget == "uploadtree_a");

    std::string reusedFilter = reusedNeedsFilter
      ? " AND ur.upload_fk=" + std::to_string(reusedUploadId) : "";
    std::string targetFilter = targetNeedsFilter
      ? " AND ut.upload_fk=" + std::to_string(uploadId) : "";

    // Find items in target upload with matching filename.
    QueryResult rr = dbManager.queryPrintf(
      "SELECT ut.uploadtree_pk, ut.pfile_fk, ur.ufile_name, ur.uploadtree_pk"
      " FROM %s ur, %s ut"
      " WHERE ur.pfile_fk=%d%s"
      "   AND ut.ufile_name=ur.ufile_name%s",
      tableReused.c_str(), tableTarget.c_str(),
      pfileFk, reusedFilter.c_str(),
      targetFilter.c_str());

    for (int i = 0; i < rr.getRowCount(); ++i)
    {
      auto row       = rr.getRow(i);
      int  newItemPk   = std::stoi(row[0]);
      int  newPfileFk  = std::stoi(row[1]);
      std::string fileName = row.size() > 2 ? row[2] : "";
      int  reusedItemPk = row.size() > 3 && !row[3].empty() ? std::stoi(row[3]) : 0;
      if (newItemPk <= 0 || newPfileFk <= 0) continue;

      // Same filename is not enough: require the same relative path in both
      // trees (below each tree's wrapper boundary), otherwise two unrelated
      // same-named files elsewhere in the packages would be treated as a match.
      std::string reusedRelPath = relativePathOf(dbManager, tableReused,
        reusedPathBoundary, reusedItemPk, reusedPathCache);
      std::string targetRelPath = relativePathOf(dbManager, tableTarget,
        targetPathBoundary, newItemPk, targetPathCache);
      if (reusedRelPath != targetRelPath)
      {
        ++metrics_.pathMismatch;
        continue;
      }

      // 1) Kotoba license oracle: authoritative when both sides have findings.
      const std::vector<std::string>& reusedLicenses = reusedKotoba[pfileFk];
      const std::vector<std::string>& targetLicenses = kotobaFor(newPfileFk);
      if (!reusedLicenses.empty() && !targetLicenses.empty())
      {
        if (kotobaLicensesEqual(reusedLicenses, targetLicenses))
        {
          if (applyDecision(newItemPk, userId, groupId, originalDecision))
          {
            ++metrics_.kotobaMatched;
            fo_scheduler_heart(1);
          }
        }
        else
        {
          ++metrics_.kotobaSkipped;
        }
        continue;
      }

      // 2) Diff-based change classification.
      std::string newPath = ClearingDecisionUtils::getRepoPathOfPfile(
        dbManager, newPfileFk);
      if (newPath.empty()) continue;

      DiffResult diff = unifiedDiff(reusedPath, newPath);
      if (diff.status == DiffStatus::Error)
      {
        ++metrics_.diffError;
        continue;
      }
      if (diff.hunks.empty())
      {
        // Identical content – the decision is trivially applicable.
        if (applyDecision(newItemPk, userId, groupId, originalDecision))
        {
          ++metrics_.licenseSame;
          fo_scheduler_heart(1);
        }
        continue;
      }

      // 3) Nomos license oracle: authoritative only when nomos ran
      //    successfully on BOTH uploads. First the detected license sets are
      //    compared (order-insensitive): a differing set means the license
      //    changed — including a license being added or removed, where one
      //    side has findings and the other has none. If the sets are equal,
      //    the actual matched license text (nomos highlight spans) is
      //    compared, so a rewritten or truncated license text is still
      //    caught.
      if (nomosRanOnReused && nomosRanOnTarget)
      {
        const std::vector<std::string>& reusedLicenses = reusedNomosLicenses[pfileFk];
        const std::vector<std::string>& targetLicenses = nomosLicensesFor(newPfileFk);
        bool nomosVeto = !nomosLicensesEqual(reusedLicenses, targetLicenses);
        if (!nomosVeto)
        {
          const std::vector<NomosRegion>& reusedRegions = reusedNomosRegions[pfileFk];
          const std::vector<NomosRegion>& targetRegions = nomosRegionsFor(newPfileFk);
          if (!reusedRegions.empty() && !targetRegions.empty())
          {
            std::string reusedLicenseText =
              extractNormalizedLicenseText(reusedPath, reusedRegions);
            std::string targetLicenseText =
              extractNormalizedLicenseText(newPath, targetRegions);
            nomosVeto = !reusedLicenseText.empty() && !targetLicenseText.empty() &&
                        reusedLicenseText != targetLicenseText;
          }
        }
        if (nomosVeto)
        {
          ++metrics_.nomosTextChanged;
          continue;
        }
      }

      // 4) Ojo SPDX-id oracle: authoritative when ojo ran successfully and
      //    produced findings. With identifier sets on BOTH sides they must
      //    be equal; with identifiers on only ONE side they must still occur
      //    in the other file's content (SPDX declaration added/removed).
      const std::set<std::string>& reusedSpdx = reusedOjoSpdx[pfileFk];
      const std::set<std::string>& targetSpdx = ojoSpdxFor(newPfileFk);
      bool ojoVeto = false;
      if (!reusedSpdx.empty() && !targetSpdx.empty())
        ojoVeto = reusedSpdx != targetSpdx;
      else if (!reusedSpdx.empty())
        ojoVeto = !spdxIdsPresentInFile(newPath, reusedSpdx);
      else if (!targetSpdx.empty())
        ojoVeto = !spdxIdsPresentInFile(reusedPath, targetSpdx);
      if (ojoVeto)
      {
        ++metrics_.ojoSpdxChanged;
        continue;
      }

      // Comment data is only needed to map hunks to comment regions.
      // Pass the original filename so nirjas detects the language from the extension.
      const NirjasOutput& reusedComments = commentsFor(reusedPath, fileName);
      const NirjasOutput& targetComments = commentsFor(newPath, fileName);

      switch (classifyChange(diff.hunks, reusedComments, targetComments))
      {
        case ChangeType::LicenseChanged:
          ++metrics_.licenseChanged;
          break;
        case ChangeType::CopyrightChange:
          if (applyDecision(newItemPk, userId, groupId, originalDecision))
          {
            ++metrics_.copyrightChange;
            fo_scheduler_heart(1);
          }
          break;
        case ChangeType::CodeChange:
          if (applyDecision(newItemPk, userId, groupId, originalDecision))
          {
            ++metrics_.codeChange;
            fo_scheduler_heart(1);
          }
          break;
        case ChangeType::CodeAndCopyrightChange:
          if (applyDecision(newItemPk, userId, groupId, originalDecision))
          {
            ++metrics_.codeAndCopyrightChange;
            fo_scheduler_heart(1);
          }
          break;
        case ChangeType::LicenseSame:
          if (applyDecision(newItemPk, userId, groupId, originalDecision))
          {
            ++metrics_.licenseSame;
            fo_scheduler_heart(1);
          }
          break;
        case ChangeType::Unknown:
          ++metrics_.diffSkipped;
          break;
      }
    }
  }
  return true;
}
