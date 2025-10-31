/*
Author: Harshit Gandhi
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_BULK_H
#define KOTOBA_AGENT_BULK_H

#define AGENT_BULK_NAME "kotobabulk" ///< the name of the agent, used to get agent key
#define AGENT_BULK_DESC "kotobabulk agent" ///< what program this is
#define AGENT_BULK_ARS  "kotobabulk_ars"

#include "kotoba.h"
#include <glib.h>

#define BULK_DECISION_TYPE_MEANING "bulk"
#define BULK_DECISION_TYPE_KOTOBA 6
#define BULK_DECISION_SCOPE "upload"

/**
 * @brief Structure to hold a custom phrase and its mapped licenses
 */
typedef struct {
    long cpId;                ///< cp_pk from custom_phrase table
    char* text;              ///< phrase text to match
    char* acknowledgement;    ///< acknowledgement text (nullable)
    char* comments;          ///< comments (nullable)
    GArray* licenseIds;      ///< array of rf_pk values from custom_phrase_license_map
} Phrase;

/**
 * @brief Arguments for phrase-mode bulk scanning
 */
typedef struct {
    int uploadId;            ///< upload ID to scan
    int userId;              ///< user ID from scheduler
    int groupId;             ///< group ID from scheduler  
    int jobId;               ///< job ID from scheduler
    GArray* phrases;         ///< array of Phrase* structures
    char* delimiters;        ///< token delimiters
} PhraseModeArgs;

#endif // KOTOBA_AGENT_BULK_H
