/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_DATABASE_H
#define KOTOBA_AGENT_DATABASE_H

#include <libfossology.h>
#include "highlight.h"
#include "kotobabulk.h"

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

// Phrase-mode database functions
GArray* queryActiveCustomPhrases(fo_dbManager* dbManager);
GArray* queryMappedLicensesForPhrase(fo_dbManager* dbManager, long cpId);
void phrase_free(Phrase* phrase);
void phrases_free(GArray* phrases);

#endif // KOTOBA_AGENT_DATABASE_H
