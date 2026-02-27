/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_DATABASE_H
#define MONK_AGENT_DATABASE_H

#include <glib.h>
#include <libfossology.h>
#include "highlight.h"

#define DECISION_TYPE_FOR_IRRELEVANT 4

// Kotoba-specific structures for custom phrase scanning
/**
 * @brief Structure to hold a license mapping with add/remove flag
 */
typedef struct LicenseMapping_t {
    long rfPk;               ///< rf_pk from license_ref table
    int removing;            ///< 0 = add license, 1 = remove license
} LicenseMapping;

/**
 * @brief Structure to hold a custom phrase and its mapped licenses
 */
typedef struct Phrase_t {
    long cpId;                ///< cp_pk from custom_phrase table
    char* text;              ///< phrase text to match
    char* acknowledgement;    ///< acknowledgement text (nullable)
    char* comments;          ///< comments (nullable)
    GArray* licenseMappings;  ///< array of LicenseMapping structures
} Phrase;

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

// Kotoba phrase-mode database functions
GArray* queryActiveCustomPhrases(fo_dbManager* dbManager);
GArray* queryMappedLicensesForPhrase(fo_dbManager* dbManager, long cpId);
void phrase_free(Phrase* phrase);
void phrases_free(GArray* phrases);

#endif // MONK_AGENT_DATABASE_H
