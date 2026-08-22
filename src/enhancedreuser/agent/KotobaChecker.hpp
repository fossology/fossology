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

/**
 * @brief Query the licenses the kotoba agent detected for a set of pfiles in
 *        an upload.
 *
 * Kotoba writes its findings to license_file just like the other scanners.
 * Only findings of successful kotoba runs on the given upload are considered,
 * so results are tied to the upload rather than to any individual file path.
 *
 * @param dbManager Database connection.
 * @param uploadId  Upload the findings belong to (ars_master.upload_fk).
 * @param pfileIds  Pfile ids to look up.
 * @return Map pfile_fk -> detected license shortnames (deduplicated order not
 *         guaranteed).  Pfiles without findings are absent from the map.
 */
std::map<int, std::vector<std::string>> getKotobaShortnamesByPfile(
  const fo::DbManager& dbManager, int uploadId,
  const std::vector<int>& pfileIds);

/**
 * @brief Whether two kotoba license sets are equal (order independent).
 */
bool kotobaLicensesEqual(const std::vector<std::string>& a,
                         const std::vector<std::string>& b);
