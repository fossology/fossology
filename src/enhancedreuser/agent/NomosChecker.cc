/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/

#include "NomosChecker.hpp"

#include <algorithm>
#include <cctype>
#include <fstream>
#include <sstream>

namespace
{

/**
 * @brief Collapse whitespace runs to single spaces and trim the result.
 */
std::string normalizeWhitespace(const std::string& in)
{
  std::string normalized;
  bool prevSpace = false;
  for (unsigned char c : in)
  {
    if (isspace(c))
    {
      if (!prevSpace && !normalized.empty()) normalized += ' ';
      prevSpace = true;
    }
    else
    {
      normalized += static_cast<char>(c);
      prevSpace = false;
    }
  }
  size_t end = normalized.find_last_not_of(' ');
  return (end == std::string::npos) ? "" : normalized.substr(0, end + 1);
}

std::string readFile(const std::string& filePath)
{
  std::ifstream in(filePath, std::ios::binary);
  if (!in) return "";
  std::ostringstream ss;
  ss << in.rdbuf();
  return ss.str();
}

std::string joinPfileIds(const std::vector<int>& pfileIds)
{
  std::string inList;
  for (size_t i = 0; i < pfileIds.size(); ++i)
  {
    if (i) inList += ",";
    inList += std::to_string(pfileIds[i]);
  }
  return inList;
}

} // namespace

bool nomosRanOnUpload(const fo::DbManager& dbManager, int uploadId)
{
  if (uploadId <= 0) return false;
  fo::QueryResult qr = dbManager.queryPrintf(
    "SELECT 1 FROM ars_master"
    " WHERE agent_fk IN (SELECT agent_pk FROM agent"
    "                    WHERE agent_name = 'nomos')"
    "   AND upload_fk = %d AND ars_success"
    " LIMIT 1",
    uploadId);
  return qr.getRowCount() > 0;
}

std::map<int, std::vector<std::string>> getNomosLicensesByPfile(
  const fo::DbManager& dbManager, int uploadId,
  const std::vector<int>& pfileIds)
{
  std::map<int, std::vector<std::string>> result;
  if (uploadId <= 0 || pfileIds.empty()) return result;

  fo::QueryResult qr = dbManager.queryPrintf(
    "SELECT lf.pfile_fk, lr.rf_shortname"
    " FROM license_file lf"
    " INNER JOIN license_ref lr ON lf.rf_fk = lr.rf_pk"
    " WHERE lf.agent_fk IN (SELECT agent_pk FROM agent"
    "                        WHERE agent_name = 'nomos')"
    "   AND lf.pfile_fk IN (%s)"
    "   AND lf.agent_fk IN (SELECT agent_fk FROM ars_master"
    "                        WHERE upload_fk = %d AND ars_success)",
    joinPfileIds(pfileIds).c_str(), uploadId);

  for (int i = 0; i < qr.getRowCount(); ++i)
  {
    auto row = qr.getRow(i);
    if (row.size() < 2) continue;
    try
    {
      int pfileId = std::stoi(row[0]);
      result[pfileId].push_back(row[1]);
    }
    catch (const std::exception&)
    {
      continue;
    }
  }
  return result;
}

bool nomosLicensesEqual(const std::vector<std::string>& a,
                        const std::vector<std::string>& b)
{
  if (a.size() != b.size()) return false;
  std::vector<std::string> sa = a;
  std::vector<std::string> sb = b;
  std::sort(sa.begin(), sa.end());
  std::sort(sb.begin(), sb.end());
  return sa == sb;
}

std::map<int, std::vector<NomosRegion>> getNomosRegionsByPfile(
  const fo::DbManager& dbManager, int uploadId,
  const std::vector<int>& pfileIds)
{
  std::map<int, std::vector<NomosRegion>> result;
  if (uploadId <= 0 || pfileIds.empty()) return result;

  // One region per highlight row: disjoint matches of the same license
  // (fl_pk) stay separate so that the gap between them (e.g. code between
  // two matched spans) does not become part of the compared license text.
  fo::QueryResult qr = dbManager.queryPrintf(
    "SELECT lf.pfile_fk, h.start, h.start + h.len AS region_end"
    " FROM license_file lf"
    " INNER JOIN highlight h ON h.fl_fk = lf.fl_pk"
    " WHERE lf.agent_fk IN (SELECT agent_pk FROM agent"
    "                        WHERE agent_name = 'nomos')"
    "   AND lf.pfile_fk IN (%s)"
    "   AND lf.agent_fk IN (SELECT agent_fk FROM ars_master"
    "                        WHERE upload_fk = %d AND ars_success)"
    "   AND h.type IN ('M','M+','M-','MR','L')",
    joinPfileIds(pfileIds).c_str(), uploadId);

  for (int i = 0; i < qr.getRowCount(); ++i)
  {
    auto row = qr.getRow(i);
    if (row.size() < 3) continue;
    try
    {
      int pfileId = std::stoi(row[0]);
      int start   = std::stoi(row[1]);
      int end     = std::stoi(row[2]);
      result[pfileId].push_back({start, end});
    }
    catch (const std::exception&)
    {
      continue;
    }
  }
  for (auto& kv : result)
    std::sort(kv.second.begin(), kv.second.end(),
      [](const NomosRegion& a, const NomosRegion& b) { return a.start < b.start; });
  return result;
}

std::string extractNormalizedLicenseText(const std::string& filePath,
  const std::vector<NomosRegion>& regions)
{
  if (regions.empty()) return "";

  const std::string content = readFile(filePath);
  if (content.empty()) return "";

  std::string combined;
  for (const auto& r : regions)
  {
    if (r.start < 0 || r.end > static_cast<int>(content.size()) || r.start >= r.end)
      continue;
    combined += content.substr(r.start, r.end - r.start);
    combined += ' ';
  }
  if (combined.empty()) return "";

  return normalizeWhitespace(combined);
}