/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#pragma once

#include <map>
#include <string>
#include <vector>

#include "libfossologyCPP.hpp"
#include "EnhancedReuserTypes.hpp"

/**
 * @class EnhancedReuserDatabaseHandler
 * @brief Database handler for the enhancedreuser.
 *
 * Extends fo::AgentDatabaseHandler and mirrors the design of
 * ReuserDatabaseHandler from the reuser agent.
 */
class EnhancedReuserDatabaseHandler : public fo::AgentDatabaseHandler
{
public:
  explicit EnhancedReuserDatabaseHandler(fo::DbManager dbManager);
  EnhancedReuserDatabaseHandler(EnhancedReuserDatabaseHandler&& other) = default;
  virtual ~EnhancedReuserDatabaseHandler() = default;

  virtual EnhancedReuserDatabaseHandler spawn() const;

  // ── Upload-tree helpers ───────────────────────────────────────────────────
  virtual bool getParentItemBounds(int uploadId, ItemTreeBounds& out);

  // ── Reuse relationship queries ────────────────────────────────────────────
  virtual std::vector<ReuseTriple> getReusedUploads(int uploadId, int groupId);
  virtual std::map<int, int> getClearingDecisionMapByPfile(
    int uploadId, int groupId);


  // ── Clearing-decision operations ─────────────────────────────────────────
  virtual int insertClearingEvent(int uploadTreeId, int userId, int groupId,
    int licenseId, bool removed, int type,
    const std::string& reportInfo, const std::string& comment,
    const std::string& ack, int jobId);
  virtual int createDecisionFromEvents(int uploadTreeId, int userId,
    int groupId, int decType, int scope,
    const std::vector<int>& eventIds);
  virtual int createCopyOfClearingDecision(int newItemUploadTreePk, int userId,
    int groupId, int originalDecisionPk);



  // ── Enhanced reuse ──────────────────────────────────────────────────────
  /** Enhanced reuse: match by filename + diff-based change classification. */
  virtual bool processEnhancedUploadReuse(int uploadId, int reusedUploadId,
    int groupId, int reusedGroupId, int userId);

  /** Metrics accumulated across processEnhancedUploadReuse calls. */
  struct Metrics
  {
    int pathMismatch         = 0; ///< skipped: same filename but different path in each tree
    int kotobaMatched        = 0; ///< applied: kotoba found same licenses
    int kotobaSkipped        = 0; ///< skipped: kotoba found different licenses
    int nomosTextChanged     = 0; ///< skipped: nomos-matched license text differs
    int ojoSpdxChanged       = 0; ///< skipped: ojo-detected SPDX id set differs
    int licenseSame          = 0; ///< applied: identical or license-preserving comment change
    int licenseChanged       = 0; ///< skipped: diff shows a license identity change
    int copyrightChange      = 0; ///< applied: copyright statement changed (year, holder, ...)
    int codeChange           = 0; ///< applied: code-only change
    int codeAndCopyrightChange = 0; ///< applied: both a code hunk and a copyright hunk changed
    int diffError            = 0; ///< skipped: diff failed or binary content
    int diffSkipped          = 0; ///< skipped: change unclassifiable
    int copyFailed           = 0; ///< createCopyOfClearingDecision returned 0

    std::string toJson() const;
    void reset()
    {
      pathMismatch = 0;
      kotobaMatched = kotobaSkipped = 0;
      nomosTextChanged = ojoSpdxChanged = 0;
      licenseSame = licenseChanged = 0;
      copyrightChange = codeChange = codeAndCopyrightChange = 0;
      diffError = diffSkipped = copyFailed = 0;
    }
  };

  void resetMetrics() { metrics_.reset(); }
  const Metrics& getMetrics() const { return metrics_; }

private:
  Metrics metrics_;
};
