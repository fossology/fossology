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

/** One nomos license-text match region: byte offsets [start, end) into the file. */
struct NomosRegion
{
  int start;
  int end;
};

/**
 * @brief Whether the nomos agent ran successfully on the given upload.
 *
 * This is the precondition of the whole nomos gate: without a successful
 * nomos run on BOTH uploads of a reuse pair, the gate has no opinion and is
 * skipped. (Without this check, an upload without a nomos run would look
 * like "nomos found no license anywhere" and could falsely veto or pass
 * pairs based on missing data.)
 *
 * @param dbManager Database connection.
 * @param uploadId  Upload to check (ars_master.upload_fk).
 * @return true if ars_master contains a successful nomos run for the upload.
 */
bool nomosRanOnUpload(const fo::DbManager& dbManager, int uploadId);

/**
 * @brief Query the license names the nomos agent detected for a set of
 *        pfiles in an upload.
 *
 * Only findings of successful nomos runs (ars_master.ars_success) on the
 * given upload are considered.
 *
 * @param dbManager Database connection.
 * @param uploadId  Upload the findings belong to (ars_master.upload_fk).
 * @param pfileIds  Pfile ids to look up.
 * @return Map pfile_fk -> detected license names.  Pfiles without findings
 *         are absent from the map.
 */
std::map<int, std::vector<std::string>> getNomosLicensesByPfile(
  const fo::DbManager& dbManager, int uploadId,
  const std::vector<int>& pfileIds);

/**
 * @brief Whether two nomos license-name sets are equal, order-insensitively.
 *
 * Both sides are sorted and compared (multiset semantics, as in
 * kotobaLicensesEqual()); two empty sets are equal.
 */
bool nomosLicensesEqual(const std::vector<std::string>& a,
                        const std::vector<std::string>& b);

/**
 * @brief Query the license-text regions the nomos agent matched for a set of
 *        pfiles in an upload.
 *
 * Nomos writes its findings to license_file with byte offsets in highlight
 * (fl_fk, start, len, type). Only rows from successful nomos runs
 * (ars_master.ars_success) on the given upload are considered, and only
 * highlight rows of type 'M'/'M+'/'M-'/'MR' (match variants) or 'L'
 * (signature) are included; 'K' (keyword-only) rows are too weak a signal
 * and are excluded. Each highlight row becomes its own region: disjoint
 * matches of the same license (fl_pk) are NOT merged into one bounding span,
 * so gaps between them (e.g. code between two matched spans) do not become
 * part of the compared license text. Regions are returned sorted by start
 * ascending.
 *
 * @param dbManager Database connection.
 * @param uploadId  Upload the findings belong to (ars_master.upload_fk).
 * @param pfileIds  Pfile ids to look up.
 * @return Map pfile_fk -> regions.  Pfiles without findings (or without a
 *         successful nomos run on this upload) are absent from the map.
 */
std::map<int, std::vector<NomosRegion>> getNomosRegionsByPfile(
  const fo::DbManager& dbManager, int uploadId,
  const std::vector<int>& pfileIds);

/**
 * @brief Extract and concatenate the given byte regions from a file's raw
 *        content, normalizing whitespace so trivial formatting differences
 *        don't count as a change.
 *
 * Regions are expected sorted by start (as returned by
 * getNomosRegionsByPfile()). Whitespace runs are collapsed to a single space
 * and the result is trimmed.
 *
 * @param filePath Path of the file to read.
 * @param regions  Byte regions to extract, sorted by start.
 * @return Concatenated normalized text, or "" if filePath cannot be read,
 *         regions is empty, or all regions are out of range.
 */
std::string extractNormalizedLicenseText(const std::string& filePath,
  const std::vector<NomosRegion>& regions);