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

#ifndef MONK_AGENT_DATABASE_H
#define MONK_AGENT_DATABASE_H

#include <libfossology.h>
#include "highlight.h"

PGresult* queryFileIdsForUploadAndLimits(fo_dbManager* dbManager, int uploadId, long left, long right, long groupId);
PGresult* queryAllLicenses(fo_dbManager* dbManager);
char* getLicenseTextForLicenseRefId(fo_dbManager* dbManager, long refId);
int hasAlreadyResultsFor(fo_dbManager* dbManager, int agentId, long pFileId);
long saveToDb(fo_dbManager* dbManager, int agentId, long int refId, long int pFileId, unsigned int percent);
int saveNoResultToDb(fo_dbManager* dbManager, int agentId, long pFileId);
int saveDiffHighlightToDb(fo_dbManager* dbManager, const DiffMatchInfo* diffInfo, long licenseFileId);
int saveDiffHighlightsToDb(fo_dbManager* dbManager, const GArray* matchedInfo, long licenseFileId);

#endif // MONK_AGENT_DATABASE_H
