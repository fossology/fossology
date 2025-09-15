/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_SERIALIZE_H
#define KOTOBA_AGENT_SERIALIZE_H

#include "kotoba.h"

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

#endif // KOTOBA_AGENT_SERIALIZE_H
