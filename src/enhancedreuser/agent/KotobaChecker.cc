/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/

#include "KotobaChecker.hpp"

#include <algorithm>

std::map<int, std::vector<std::string>> getKotobaShortnamesByPfile(
  const fo::DbManager& dbManager, int uploadId,
  const std::vector<int>& pfileIds)
{
  std::map<int, std::vector<std::string>> result;
  if (uploadId <= 0 || pfileIds.empty()) return result;

  std::string inList;
  for (size_t i = 0; i < pfileIds.size(); ++i)
  {
    if (i) inList += ",";
    inList += std::to_string(pfileIds[i]);
  }

  fo::QueryResult qr = dbManager.queryPrintf(
    "SELECT lf.pfile_fk, lr.rf_shortname"
    " FROM license_file lf"
    " INNER JOIN license_ref lr ON lf.rf_fk = lr.rf_pk"
    " WHERE lf.agent_fk IN (SELECT agent_pk FROM agent"
    "                        WHERE agent_name = 'kotoba')"
    "   AND lf.pfile_fk IN (%s)"
    "   AND lf.agent_fk IN (SELECT agent_fk FROM ars_master"
    "                        WHERE upload_fk = %d AND ars_success)",
    inList.c_str(), uploadId);

  for (int i = 0; i < qr.getRowCount(); ++i)
  {
    auto row = qr.getRow(i);
    if (row.size() < 2) continue;
    int pfileId = 0;
    try
    {
      pfileId = std::stoi(row[0]);
    }
    catch (const std::exception&)
    {
      continue;
    }
    result[pfileId].push_back(row[1]);
  }
  return result;
}

bool kotobaLicensesEqual(const std::vector<std::string>& a,
                         const std::vector<std::string>& b)
{
  if (a.size() != b.size()) return false;
  std::vector<std::string> sa = a;
  std::vector<std::string> sb = b;
  std::sort(sa.begin(), sa.end());
  std::sort(sb.begin(), sb.end());
  return sa == sb;
}
