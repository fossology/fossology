/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_DIFF_H
#define KOTOBA_AGENT_DIFF_H

#include "string_operations.h"
#include "kotoba.h"

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

#endif // KOTOBA_AGENT_DIFF_H
