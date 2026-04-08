/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
#pragma once

#include <array>
#include <map>
#include <string>
#include <vector>

#include "libfossologyCPP.hpp"
#include "ReuserTypes.hpp"

/**
 * @class ReuserDatabaseHandler
 * @brief Database handler for the reuser agent.
 *
 * Extends fo::AgentDatabaseHandler (fossologyCPP) and consolidates all
 * database operations previously spread across DBManager, ReuserDao,
 * AgentDao, reuser_logic and reuser_worker.
 *
 * Mirrors the design of OjosDatabaseHandler from the ojo agent.
 */
class ReuserDatabaseHandler : public fo::AgentDatabaseHandler
{
public:
  explicit ReuserDatabaseHandler(fo::DbManager dbManager);
  ReuserDatabaseHandler(ReuserDatabaseHandler&& other) = default;
  virtual ~ReuserDatabaseHandler() = default;

  /** Spawn a new handler sharing the same connection pool (for threads). */
  virtual ReuserDatabaseHandler spawn() const;

  // ── Upload-tree helpers ───────────────────────────────────────────────────

  /**
   * @brief Fetch the parent item bounds for a given upload.
   * @return true on success, false if no row found.
   */
  virtual bool getParentItemBounds(int uploadId, ItemTreeBounds& out);

  // ── Reuse relationship queries ────────────────────────────────────────────

  /**
   * @brief Return the list of uploads that should be reused for @p uploadId.
   */
  virtual std::vector<ReuseTriple> getReusedUploads(int uploadId, int groupId);

  /**
   * @brief Build a pfile_fk → clearing_decision_pk map for @p uploadId.
   *
   * Considers both ITEM-scope and (when enabled) REPO-scope decisions.
   */
  virtual std::map<int, int> getClearingDecisionMapByPfile(int uploadId, int groupId);

  /**
   * @brief For a set of pfile ids, return a map pfile_fk → [uploadtree_pk].
   */
  virtual std::map<int, std::vector<int>> getUploadTreePksForPfiles(
    int uploadId, const std::vector<int>& pfileIds);

  // ── Clearing-decision operations ─────────────────────────────────────────

  /**
   * @brief Insert a new clearing event and return its primary key (0 on error).
   */
  virtual int insertClearingEvent(int uploadTreeId, int userId, int groupId,
    int licenseId, bool removed, int type,
    const std::string& reportInfo, const std::string& comment,
    const std::string& ack, int jobId);

  /**
   * @brief Create a clearing_decision linked to @p eventIds.
   * @return New clearing_decision_pk or 0 on error.
   */
  virtual int createDecisionFromEvents(int uploadTreeId, int userId, int groupId,
    int decType, int scope, const std::vector<int>& eventIds);

  /**
   * @brief Copy an existing clearing decision to a new uploadtree item.
   * @return New clearing_decision_pk or 0 on error.
   */
  virtual int createCopyOfClearingDecision(int newItemUploadTreePk, int userId,
    int groupId, int originalDecisionPk);

  // ── ARS record ───────────────────────────────────────────────────────────

  /**
   * @brief Write (insert or update) an ARS record.
   * @return ARS primary key or -1 on error.
   */
  virtual int writeArsRecord(int agentId, int uploadId, int arsId = 0,
    bool success = false);

  // ── Reuse operations ─────────────────────────────────────────────────────

  /** Standard reuse: copy clearing decisions matched by pfile id. */
  virtual bool processUploadReuse(int uploadId, int reusedUploadId,
    int groupId, int reusedGroupId, int userId);

  /** Enhanced reuse: match by filename + diff threshold. */
  virtual bool processEnhancedUploadReuse(int uploadId, int reusedUploadId,
    int groupId, int reusedGroupId, int userId);

  /** Copy main-license entries from reused upload. */
  virtual bool reuseMainLicense(int uploadId, int groupId,
    int reusedUploadId, int reusedGroupId);

  /** Copy report_info configuration from reused upload. */
  virtual bool reuseConfSettings(int uploadId, int reusedUploadId);

  /** Copy copyright events from reused upload. */
  virtual bool reuseCopyrights(int uploadId, int reusedUploadId, int userId);

protected:
  /** @brief Validate that @p s contains only characters safe for SQL identifiers. */
  static bool        isValidIdentifier(const std::string& s);
  /** @brief Strip Unicode control characters (C0, C1, DEL) from @p input. */
  static std::string replaceUnicodeControlChars(const std::string& input);

private:
  static std::string shellEscape(const std::string& s);
  static int         diffLineCount(const std::string& a, const std::string& b);

  /** Return the repository file path for a pfile, or empty string on error. */
  std::string getRepoPathOfPfile(int pfileId);
};
