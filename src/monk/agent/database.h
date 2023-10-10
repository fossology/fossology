/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_DATABASE_H
#define MONK_AGENT_DATABASE_H

#include <libfossology.h>
#include "highlight.h"

#define DECISION_TYPE_FOR_IRRELEVANT 4

PGresult* queryFileIdsForUploadAndLimits(fo_dbManager* dbManager, int uploadId,
                                         long left, long right, long groupId,
                                         bool ignoreIrre, bool scanFindings);
PGresult* queryAllLicenses(fo_dbManager* dbManager);
char* getLicenseTextForLicenseRefId(fo_dbManager* dbManager, long refId);
int hasAlreadyResultsFor(fo_dbManager* dbManager, int agentId, long pFileId);
long saveToDb(fo_dbManager* dbManager, int agentId, long int refId, long int pFileId, unsigned int percent);
int saveNoResultToDb(fo_dbManager* dbManager, int agentId, long pFileId);
int saveDiffHighlightToDb(fo_dbManager* dbManager, const DiffMatchInfo* diffInfo, long licenseFileId);
int saveDiffHighlightsToDb(fo_dbManager* dbManager, const GArray* matchedInfo, long licenseFileId);

#endif // MONK_AGENT_DATABASE_H
