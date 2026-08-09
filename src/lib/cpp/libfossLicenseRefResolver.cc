/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "libfossLicenseRefResolver.hpp"

#include "libfossUtils.hpp"
#include "libfossdbQueryResult.hpp"

#include <algorithm>
#include <cctype>

extern "C" {
#include "libfossology.h"
}

namespace
{
  bool hasEnding(const std::string& firstString, const std::string& ending)
  {
    return firstString.length() >= ending.length()
      && firstString.compare(firstString.length() - ending.length(),
        ending.length(), ending) == 0;
  }

  void addUnique(std::vector<std::string>& values, const std::string& value)
  {
    if (!value.empty()
        && std::find(values.begin(), values.end(), value) == values.end())
    {
      values.push_back(value);
    }
  }
}

namespace fo
{
  LicenseRefResolver::LicenseRefResolver(const DbManager& dbManager) :
    dbManager(dbManager)
  {
  }

  unsigned long LicenseRefResolver::resolveExistingLicenseId(
    const std::string& primaryName,
    const std::string& fallbackName) const
  {
    unsigned long licenseId = selectFromLicenseRef(
      getComparableNames(primaryName));
    if (licenseId > 0)
    {
      return licenseId;
    }

    if (!fallbackName.empty() && fallbackName != primaryName)
    {
      licenseId = selectFromLicenseRef(getComparableNames(fallbackName));
      if (licenseId > 0)
      {
        return licenseId;
      }
    }

    licenseId = selectFromLicenseLynx(primaryName);
    if (licenseId > 0)
    {
      return licenseId;
    }

    if (!fallbackName.empty() && fallbackName != primaryName)
    {
      licenseId = selectFromLicenseLynx(fallbackName);
      if (licenseId > 0)
      {
        return licenseId;
      }
    }

    return 0;
  }

  std::vector<std::string> LicenseRefResolver::getComparableNames(
    const std::string& licenseName) const
  {
    std::vector<std::string> comparableNames;
    if (licenseName.empty())
    {
      return comparableNames;
    }

    icu::UnicodeString unicodeCleanShortname = recodeToUnicode(licenseName);
    std::string cleanName;
    unicodeCleanShortname.toUTF8String(cleanName);

    std::transform(cleanName.begin(), cleanName.end(), cleanName.begin(),
      [](unsigned char c) { return static_cast<char>(std::tolower(c)); });

    if (hasEnding(cleanName, "+") || hasEnding(cleanName, "-or-later"))
    {
      std::string baseName(cleanName);
      const std::string plus("+");
      const std::string orLater("-or-later");

      size_t plusLast = baseName.rfind(plus);
      size_t orLaterLast = baseName.rfind(orLater);

      if (plusLast != std::string::npos)
      {
        baseName.erase(plusLast, std::string::npos);
      }
      if (orLaterLast != std::string::npos)
      {
        baseName.erase(orLaterLast, std::string::npos);
      }

      addUnique(comparableNames, baseName + plus);
      addUnique(comparableNames, baseName + orLater);
    }
    else
    {
      std::string baseName(cleanName);
      const std::string only("-only");

      size_t onlyLast = baseName.rfind(only);
      if (onlyLast != std::string::npos)
      {
        baseName.erase(onlyLast, std::string::npos);
      }

      addUnique(comparableNames, baseName);
      addUnique(comparableNames, baseName + only);
    }

    return comparableNames;
  }

  unsigned long LicenseRefResolver::selectFromLicenseRef(
    const std::vector<std::string>& licenseNames) const
  {
    if (licenseNames.empty())
    {
      return 0;
    }

    QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
        dbManager.getStruct_dbManager(),
        "selectExistingLicenseRef",
        "SELECT rf_pk FROM ONLY license_ref"
        " WHERE LOWER(rf_shortname) = LOWER($1)"
        " OR LOWER(rf_shortname) = LOWER($2)"
        " OR LOWER(rf_spdx_id) = LOWER($1)"
        " OR LOWER(rf_spdx_id) = LOWER($2)"
        " LIMIT 1",
        char*, char*),
      licenseNames[0].c_str(),
      licenseNames.size() > 1 ? licenseNames[1].c_str() : licenseNames[0].c_str());

    if (queryResult && queryResult.getRowCount() > 0)
    {
      std::vector<unsigned long> results =
        queryResult.getSimpleResults<unsigned long>(0, stringToUnsignedLong);
      if (!results.empty())
      {
        return results[0];
      }
    }

    return 0;
  }

  unsigned long LicenseRefResolver::selectFromLicenseLynx(
    const std::string& licenseName) const
  {
    if (licenseName.empty() || !dbManager.tableExists("licenselynx_map"))
    {
      return 0;
    }

    QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
        dbManager.getStruct_dbManager(),
        "selectLicenseIdFromLicenseLynx",
        "SELECT lr.rf_pk FROM ONLY licenselynx_map ll"
        " INNER JOIN ONLY license_ref lr"
        " ON LOWER(lr.rf_spdx_id) = LOWER(ll.spdx_id)"
        " OR LOWER(lr.rf_shortname) = LOWER(ll.spdx_id)"
        " WHERE LOWER(ll.raw_name) = LOWER($1)"
        " LIMIT 1",
        char*),
      licenseName.c_str());

    if (queryResult && queryResult.getRowCount() > 0)
    {
      std::vector<unsigned long> results =
        queryResult.getSimpleResults<unsigned long>(0, stringToUnsignedLong);
      if (!results.empty())
      {
        LOG_NOTICE("LicenseLynx mapped license '%s' to license_ref rf_pk %lu\n",
          licenseName.c_str(), results[0]);
        return results[0];
      }
    }

    return 0;
  }
}
