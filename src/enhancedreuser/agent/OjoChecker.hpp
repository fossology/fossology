/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#pragma once

#include <map>
#include <set>
#include <string>
#include <vector>

#include "libfossologyCPP.hpp"

/**
 * @brief Query the SPDX license identifiers the ojo agent detected for a set
 *        of pfiles in an upload.
 *
 * Ojo writes its findings to license_file just like the other scanners. Only
 * findings of successful ojo runs (ars_master.ars_success) on the given
 * upload are considered. Uses license_ref.rf_spdx_id when present, falling
 * back to rf_shortname when rf_spdx_id is null/empty.
 *
 * @param dbManager Database connection.
 * @param uploadId  Upload the findings belong to (ars_master.upload_fk).
 * @param pfileIds  Pfile ids to look up.
 * @return Map pfile_fk -> set of detected identifiers.  Pfiles without
 *         findings are absent from the map.
 */
std::map<int, std::set<std::string>> getOjoSpdxIdsByPfile(
  const fo::DbManager& dbManager, int uploadId,
  const std::vector<int>& pfileIds);

/**
 * @brief Whether all of the given SPDX identifiers still occur in a file's
 *        content (case-insensitive substring match).
 *
 * Used when ojo found identifiers for only one of the two files being
 * compared: the identifiers of the side that has findings are looked up in
 * the other file's content. If every identifier is still present, the SPDX
 * declaration was preserved and the other side's scanner simply had no
 * opinion (or did not run); if any identifier is absent, the declaration was
 * added or removed and the clearing decision must not be copied.
 *
 * @param filePath File whose content is searched.
 * @param spdxIds  Identifiers to look for.
 * @return true if every identifier is present in filePath. false if filePath
 *         cannot be read, spdxIds is empty, or an identifier is missing.
 */
bool spdxIdsPresentInFile(const std::string& filePath,
  const std::set<std::string>& spdxIds);
