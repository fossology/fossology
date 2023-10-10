/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014, 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_BULK_H
#define MONK_AGENT_BULK_H

#define AGENT_BULK_NAME "monkbulk" ///< the name of the agent, used to get agent key
#define AGENT_BULK_DESC "monkbulk agent" ///< what program this is
#define AGENT_BULK_ARS  "monkbulk_ars"

#include "monk.h"

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

#endif // MONK_AGENT_BULK_H
