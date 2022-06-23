/*
 Author: Maximilian Huber
 SPDX-FileCopyrightText: © 2018 TNG Technology Consulting GmbH

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_SERIALIZE_H
#define MONK_AGENT_SERIALIZE_H

#include "monk.h"

typedef struct {
  long refId;
  size_t shortnameLen;
  guint tokensLen;
} SerializingMeta;

int serializeToFile(Licenses* licenses, char* filename);
int serialize(Licenses* licenses, FILE* fp);
int serializeGArray(GArray* licenses, FILE* fp);
int serializeOne(License* license, FILE* fp);
int serializeOneMeta(License* license, FILE* fp);
int serializeOneShortname(License* license, FILE* fp);
int serializeOneTokens(GArray* tokens, FILE* fp);

Licenses* deserializeFromFile(char* filename, unsigned minAdjacentMatches, unsigned maxLeadingDiff);
Licenses* deserialize(FILE* fp, unsigned minAdjacentMatches, unsigned maxLeadingDiff);
GArray* deserializeTokens(FILE* fp, guint tokensLen);

#endif // MONK_AGENT_SERIALIZE_H
