/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_LICENSE_H
#define MONK_AGENT_LICENSE_H

#include "database.h"
#include "monk.h"

int isIgnoredLicense(const License* license);

/** @return License* */
#define license_index(licenses, index) (&g_array_index((licenses), License, (index)))

Licenses* extractLicenses(fo_dbManager* dbManager, PGresult* licensesResult, unsigned minAdjacentMatches, unsigned maxLeadingDiff);
Licenses* buildLicenseIndexes(GArray* licenses, unsigned minAdjacentMatches, unsigned maxLeadingDiff);
void licenses_free(Licenses* licenses);
const GArray* getLicenseArrayFor(const Licenses* licenses, unsigned searchPos, const GArray* textTokens, unsigned textStart);
const GArray* getShortLicenseArray(const Licenses* licenses);


#endif // MONK_AGENT_LICENSE_H
