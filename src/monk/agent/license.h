/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
