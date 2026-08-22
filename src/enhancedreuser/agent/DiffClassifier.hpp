/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#pragma once

#include <string>
#include <vector>

#include "EnhancedReuserTypes.hpp"

/**
 * @brief Classification of a change between the reused and target file.
 */
enum class ChangeType
{
  LicenseChanged,          ///< license identity differs -> do NOT copy decision
  LicenseSame,             ///< identical content or non-license comment change -> copy
  CopyrightChange,         ///< copyright statement changed (year, holder, ...) -> copy
  CodeChange,              ///< change in code (no license/copyright impact) -> copy
  CodeAndCopyrightChange,  ///< both a code hunk and a copyright hunk changed -> copy
  Unknown                  ///< cannot be classified reliably -> do NOT copy
};

/**
 * @brief A single line that changed in a unified diff.
 */
struct ChangedLine
{
  int         line; ///< 1-based line number within its own file
  std::string text; ///< content without the '-'/'+' diff prefix
};

/**
 * @brief One hunk of a unified diff.
 */
struct DiffHunk
{
  std::vector<ChangedLine> removed; ///< lines removed from the old file
  std::vector<ChangedLine> added;   ///< lines added to the new file
};

/**
 * @brief Result of running diff -u on two files.
 */
enum class DiffStatus
{
  Ok,   ///< diff ran; hunks may be empty (files identical) or carry differences
  Error ///< diff failed (missing/binary/unreadable file, etc.)
};

struct DiffResult
{
  DiffStatus          status;
  std::vector<DiffHunk> hunks;
};

/**
 * @brief Parse the output of `diff -u` into hunks.
 *
 * Assumes GNU diff output with `---`, `+++` headers followed by one or more
 * `@@ -O,C +N,C @@` hunks.  Context lines keep both line counters in sync so
 * each changed line carries its real line number in its own file.
 *
 * @param diffOutput Raw output from `diff -u`.
 * @return Parsed hunks.  Empty if the output contains no hunks (identical files
 *         or unparseable output).
 */
std::vector<DiffHunk> parseUnifiedDiff(const std::string& diffOutput);

/**
 * @brief Run `diff -u` on two repository files.
 *
 * @param oldPath Path of the reused file.
 * @param newPath Path of the target file.
 * @return DiffResult.  status is Error when the diff could not be produced
 *         (missing file, binary content) and Ok otherwise.
 */
DiffResult unifiedDiff(const std::string& oldPath, const std::string& newPath);

/**
 * @brief Classify a change between two files based on its hunks and the
 *        comment structure extracted by nirjas for both files.
 *
 * Explicit license-identity/SPDX protection (nomos matched-text compare,
 * ojo SPDX-id compare) happens upstream, before this function is called
 * (see EnhancedReuserDatabaseHandler::processEnhancedUploadReuse()); by the
 * time classifyChange() runs, either an agent has confirmed the license text
 * is unchanged or no agent had an opinion at all.
 *
 * Decision table (per hunk, in order of precedence):
 *   1. license/SPDX-hint text change (even inside a recognized comment) ->
 *      LicenseChanged (being inside a comment does not prove the license
 *      text itself is unchanged); takes precedence over copyright changes
 *   2. copyright statement change (year, holder, added/removed) ->
 *      contributes CopyrightChange
 *   3. changed lines inside comment blocks -> LicenseSame (license text intact)
 *   4. changed lines outside comments (code) -> CodeChange
 *      - if comment data is unavailable for a file, changed lines mentioning
 *        license keywords (but no copyright marker) are treated as
 *        LicenseChanged (conservative fallback when no agent/comment data
 *        exists at all); copyright-only changes still apply
 *
 * A file can contain both a copyright-only hunk and a separate code hunk; in
 * that case the result is CodeAndCopyrightChange rather than silently
 * reporting CodeChange and discarding the copyright signal.
 *
 * @param hunks        Hunks from unifiedDiff().
 * @param oldComments  Nirjas comment data of the reused file (lang "error" if
 *                     extraction failed).
 * @param newComments  Nirjas comment data of the target file.
 * @return ChangeType for the whole file.
 */
ChangeType classifyChange(const std::vector<DiffHunk>& hunks,
                          const NirjasOutput& oldComments,
                          const NirjasOutput& newComments);

/**
 * @brief Whether removed vs added lines differ in any copyright statement.
 *
 * Compares the normalized text of all copyright lines (year, holder,
 * added/removed) between removed and added lines.  A difference indicates a
 * copyright change, not a license identity change.
 */
bool isCopyrightChange(const std::vector<ChangedLine>& removed,
                       const std::vector<ChangedLine>& added);

/**
 * @brief Whether license/SPDX-hint text (excluding copyright lines) differs
 *        between removed and added lines, even when those lines sit inside
 *        a comment recognized by nirjas.
 */
bool hasLicenseHintChange(const std::vector<ChangedLine>& removed,
                          const std::vector<ChangedLine>& added);

/**
 * @brief Whether a 1-based file line number falls inside any nirjas comment
 *        block.
 */
bool isLineInComments(int lineNo, const NirjasOutput& comments);
