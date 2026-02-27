/*
Author: Harshit Gandhi
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_KOTOBA_H
#define MONK_AGENT_KOTOBA_H

#include "monk.h"
#include "database.h"
#include <glib.h>

// Override monk's agent macros with kotoba-specific values
#undef AGENT_NAME
#undef AGENT_DESC
#undef AGENT_ARS

#define AGENT_NAME "kotoba" ///< the name of the agent, used to get agent key
#define AGENT_DESC "kotoba agent" ///< what program this is
#define AGENT_ARS  "kotoba_ars"

#define BULK_DECISION_TYPE_MEANING "bulk"
#define BULK_DECISION_TYPE_KOTOBA 6
#define BULK_DECISION_SCOPE "upload"

// Phrase and LicenseMapping structures are now defined in database.h

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

// Highlight support function
int saveKotobaHighlights(MonkState* state, const File* file, const GArray* matches,
                        long clearingEventId, long phraseId);

#endif // MONK_AGENT_KOTOBA_H
