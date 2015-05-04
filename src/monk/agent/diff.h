/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

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

#ifndef MONK_AGENT_DIFF_H
#define MONK_AGENT_DIFF_H

#include "string_operations.h"
#include "monk.h"

typedef struct {
  size_t start;
  size_t length;
} DiffPoint;

typedef struct {
  DiffPoint text;
  DiffPoint search;
  char* diffType;
} DiffMatchInfo;

typedef struct {
  size_t matched;
  size_t added;
  size_t removed;
  GArray* matchedInfo;
  double rank;
  unsigned short percentual;
} DiffResult;

int lookForDiff(const GArray* textTokens, const GArray* searchTokens,
                size_t iText, size_t iSearch,
                unsigned int maxAllowedDiff, unsigned int minAdjacentMatches,
                DiffMatchInfo* result);

int matchNTokens(const GArray* textTokens, size_t textStart, size_t textLength,
                 const GArray* searchTokens, size_t searchStart, size_t searchLength,
                 unsigned int numberOfWantedMatches);

DiffResult* findMatchAsDiffs(const GArray* textTokens, const GArray* searchTokens,
                             size_t textStartPosition, size_t searchStartPosition,
                             unsigned int maxAllowedDiff, unsigned int minAdjacentMatches);

void diffResult_free(DiffResult* diffResult);

#endif // MONK_AGENT_DIFF_H
