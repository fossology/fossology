/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_KOTOBA_H
#define MONK_AGENT_KOTOBA_H

#include "monk.h"
#include "database.h"
#include <glib.h>

/* Override monk's agent macros with kotoba-specific values. */
#undef AGENT_NAME
#undef AGENT_DESC
#undef AGENT_ARS

#define AGENT_NAME "kotoba"
#define AGENT_DESC "kotoba agent"
#define AGENT_ARS  "kotoba_ars"

#define BULK_DECISION_TYPE_MEANING "bulk"
#define BULK_DECISION_TYPE_KOTOBA 6
#define BULK_DECISION_SCOPE "upload"

/* Arguments for phrase-mode bulk scanning. */
typedef struct {
    int uploadId;
    int userId;
    int groupId;
    int jobId;
    GArray* phrases;
    char* delimiters;
    char* uploadTreeTableName; /* cached once per upload */
    char* insertSql;           /* pre-built INSERT SQL (depends on table name) */
} PhraseModeArgs;

int saveKotobaHighlights(MonkState* state, const File* file, const GArray* matches,
                        long clearingEventId, long phraseId);

#endif /* MONK_AGENT_KOTOBA_H */
