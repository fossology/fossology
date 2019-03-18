/*
Author: Maximilian Huber
Copyright (C) 2018, TNG Technology Consulting GmbH

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
