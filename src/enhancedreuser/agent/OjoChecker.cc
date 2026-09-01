/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/

#include "OjoChecker.hpp"

#include <algorithm>
#include <cctype>
#include <fstream>
#include <sstream>

namespace
{

std::string readFile(const std::string& filePath)
{
  std::ifstream in(filePath, std::ios::binary);
  if (!in) return "";
  std::ostringstream ss;
  ss << in.rdbuf();
  return ss.str();
}

std::string toLower(const std::string& s)
{
  std::string out = s;
  std::transform(out.begin(), out.end(), out.begin(),
    [](unsigned char c) { return static_cast<char>(tolower(c)); });
  return out;
}

} // namespace

std::map<int, std::set<std::string>> getOjoSpdxIdsByPfile(
  const fo::DbManager& dbManager, int uploadId,
  const std::vector<int>& pfileIds)
{
  std::map<int, std::set<std::string>> result;
  if (uploadId <= 0 || pfileIds.empty()) return result;

  std::string inList;
  for (size_t i = 0; i < pfileIds.size(); ++i)
  {
    if (i) inList += ",";
    inList += std::to_string(pfileIds[i]);
  }

  fo::QueryResult qr = dbManager.queryPrintf(
    "SELECT lf.pfile_fk,"
    "       COALESCE(NULLIF(lr.rf_spdx_id, ''), lr.rf_shortname) AS ident"
    " FROM license_file lf"
    " INNER JOIN license_ref lr ON lf.rf_fk = lr.rf_pk"
    " WHERE lf.agent_fk IN (SELECT agent_pk FROM agent"
    "                        WHERE agent_name = 'ojo')"
    "   AND lf.pfile_fk IN (%s)"
    "   AND lf.agent_fk IN (SELECT agent_fk FROM ars_master"
    "                        WHERE upload_fk = %d AND ars_success)",
    inList.c_str(), uploadId);

  for (int i = 0; i < qr.getRowCount(); ++i)
  {
    auto row = qr.getRow(i);
    if (row.size() < 2) continue;
    try
    {
      int pfileId = std::stoi(row[0]);
      result[pfileId].insert(row[1]);
    }
    catch (const std::exception&)
    {
      continue;
    }
  }
  return result;
}

bool spdxIdsPresentInFile(const std::string& filePath,
  const std::set<std::string>& spdxIds)
{
  if (spdxIds.empty()) return false;

  const std::string lowerContent = toLower(readFile(filePath));
  if (lowerContent.empty()) return false; // cannot confirm presence -> veto

  for (const auto& id : spdxIds)
  {
    const std::string lowerId = toLower(id);
    if (lowerId.empty()) continue;
    if (lowerContent.find(lowerId) == std::string::npos) return false;
  }
  return true;
}
