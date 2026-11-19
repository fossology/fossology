/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef LIBFOSS_LICENSE_REF_RESOLVER_HPP_
#define LIBFOSS_LICENSE_REF_RESOLVER_HPP_

#include "libfossdbmanagerclass.hpp"

#include <string>
#include <vector>

namespace fo
{
  /**
   * \class LicenseRefResolver
   * \brief Resolve scanner license names to existing license_ref rows.
   *
   * This resolver performs only lookups. It never creates license_ref or
   * license_candidate rows, so scanner-specific fallback behavior stays in the
   * scanner database handlers.
   */
  class LicenseRefResolver
  {
    public:
      explicit LicenseRefResolver(const DbManager& dbManager);

      unsigned long resolveExistingLicenseId(
        const std::string& primaryName,
        const std::string& fallbackName = "") const;

    private:
      std::vector<std::string> getComparableNames(
        const std::string& licenseName) const;
      unsigned long selectFromLicenseRef(
        const std::vector<std::string>& licenseNames) const;
      unsigned long selectFromLicenseLynx(
        const std::string& licenseName) const;

      const DbManager& dbManager;
  };
}

#endif
