/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#ifndef MONK_AGENT_DATABASE_H
#define MONK_AGENT_DATABASE_H

#include <libfossology.h>
#include "highlight.h"

PGresult* queryFileIdsForUpload(fo_dbManager* dbManager, int uploadId);
char* queryPFileForFileId(fo_dbManager* dbManager, long int fileId);
PGresult* queryAllLicenses(fo_dbManager* dbManager);
char* getLicenseTextForLicenseRefId(fo_dbManager* dbManager, long refId);
char* getFileNameForFileId(fo_dbManager* dbManager, long int pFileId);
int hasAlreadyResultsFor(fo_dbManager* dbManager, int agentId, long pFileId);
long saveToDb(fo_dbManager* dbManager, int agentId, long int refId, long int pFileId, unsigned int percent);
int saveHighlightToDb(fo_dbManager* dbManager, char* type, DiffPoint* highlight, long int licenseFileId);
int saveDiffHighlightToDb(fo_dbManager* dbManager, DiffMatchInfo* diffInfo, long int licenseFileId);
int saveDiffHighlightsToDb(fo_dbManager* dbManager, GArray* matchedInfo, long licenseFileId);

#endif
