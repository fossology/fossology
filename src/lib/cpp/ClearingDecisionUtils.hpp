/*
 SPDX-License-Identifier: GPL-2.0-only
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
 */

#pragma once

#include <map>
#include <string>
#include <vector>

#include "libfossdbmanagerclass.hpp"

/**
 * @brief Bounds of an item within an uploadtree table.
 */
struct ItemTreeBounds
{
  int         uploadtree_pk;
  std::string uploadTreeTableName;
  int         upload_fk;
  int         lft;
  int         rgt;
};

/**
 * @brief A single reuse relationship between two uploads.
 */
struct ReuseTriple
{
  int reusedUploadId;
  int reusedGroupId;
  int reuseMode;
};

/** Reuse mode bit flags (mirror of PHP ReuseTypes constants). */
constexpr int REUSE_ENHANCED  =   2;  ///< Match by filename + comment similarity
constexpr int REUSE_MAIN      =   4;  ///< Copy main license
constexpr int REUSE_BULK      =   8;  ///< Copy bulk license ref/set and run monkbulk
constexpr int REUSE_CONF      =  16;  ///< Copy report configuration
constexpr int REUSE_COPYRIGHT = 128;  ///< Copy copyright events

namespace fo
{

/**
 * @brief Shared clearing-decision helpers used by the reuser and
 *        enhancedreuser agents.
 *
 * All functions operate on a DbManager so both agents share a single
 * implementation instead of duplicating it.
 */
namespace ClearingDecisionUtils
{

/** @brief Validate that @p s contains only characters safe for SQL identifiers. */
bool isValidIdentifier(const std::string& s);

/** @brief Strip Unicode control characters (C0, C1, DEL) from @p input. */
std::string replaceUnicodeControlChars(const std::string& input);

/**
 * @brief Priority for decision types during reuse conflict resolution.
 *
 * Higher priority values indicate a stronger decision.
 */
int getDecisionTypePriority(int decisionType);

/** @brief Return the repository file path for a pfile, or empty string on error. */
std::string getRepoPathOfPfile(const DbManager& dbManager, int pfileId);

/** @brief Fetch the parent item bounds for a given upload. */
bool getParentItemBounds(const DbManager& dbManager, int uploadId,
  ItemTreeBounds& out);

/** @brief Return the uploads that should be reused for @p uploadId. */
std::vector<ReuseTriple> getReusedUploads(const DbManager& dbManager,
  int uploadId, int groupId);

/** @brief Build a pfile_fk → clearing_decision_pk map for @p uploadId. */
std::map<int, int> getClearingDecisionMapByPfile(const DbManager& dbManager,
  int uploadId, int groupId);

/** @brief Insert a clearing event and return its primary key (0 on error). */
int insertClearingEvent(const DbManager& dbManager, int uploadTreeId,
  int userId, int groupId, int licenseId, bool removed, int type,
  const std::string& reportInfo, const std::string& comment,
  const std::string& ack, int jobId);

/** @brief Create a clearing_decision linked to @p eventIds. */
int createDecisionFromEvents(const DbManager& dbManager, int uploadTreeId,
  int userId, int groupId, int decType, int scope,
  const std::vector<int>& eventIds);

/** @brief Copy an existing clearing decision to a new uploadtree item. */
int createCopyOfClearingDecision(const DbManager& dbManager,
  int newItemUploadTreePk, int userId, int groupId, int originalDecisionPk);

} // namespace ClearingDecisionUtils
} // namespace fo
