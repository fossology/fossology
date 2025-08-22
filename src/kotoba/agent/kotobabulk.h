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

#define BULK_DECISION_TYPE_MEANING "bulk"
#define BULK_DECISION_TYPE 2
#define BULK_DECISION_SCOPE "upload"

typedef struct {
    long licenseId;
    int removing;
    char* comment;
    char* reportinfo;
    char* acknowledgement;
} BulkAction;

typedef struct {
  long bulkId;
  long uploadTreeId;
  long uploadTreeLeft;
  long uploadTreeRight;
  long licenseId;
  int uploadId;
  int jobId;
  int userId;
  int groupId;
  char* refText;
  bool ignoreIrre;
  bool scanFindings;
  char* delimiters;
  BulkAction** actions;
} BulkArguments;

#endif // KOTOBA_AGENT_BULK_H
