/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_MATCH_H
#define KOTOBA_AGENT_MATCH_H

#include <glib.h>

#include "kotoba.h"
#include "string_operations.h"
#include "diff.h"

#define MATCH_TYPE_FULL 0
#define MATCH_TYPE_DIFF 1

typedef struct {
  const License* license;
  union {
    DiffPoint* full;
    DiffResult* diff;
  } ptr;
  int type;
} Match;

typedef struct {
  int (*onAll)(KotobaState* state, const File* file, const GArray* matches);
  int (*onNo)(KotobaState* state, const File* file);
  int (*onFull)(KotobaState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo);
  int (*onDiff)(KotobaState* state, const File* file, const License* license, const DiffResult* diffResult);
  int (*onBeginOutput)(KotobaState* state);
  int (*onBetweenIndividualOutputs)(KotobaState* state);
  int (*onEndOutput)(KotobaState* state);
  int (*ignore)(KotobaState* state, const File* file);
} MatchCallbacks;

void match_array_free(GArray* matches);
#define match_array_index(matches, i) (g_array_index(matches, Match*, i))

void match_free(Match* match);

#if GLIB_CHECK_VERSION(2,32,0)
void match_destroyNotify(gpointer matchP);
#endif

size_t match_getStart(const Match* match);
size_t match_getEnd(const Match* match);

GArray* findAllMatchesBetween(const File* file, const Licenses* licenses, unsigned maxAllowedDiff, unsigned minAdjacentMatches, unsigned maxLeadingDiff);

int matchPFileWithLicenses(KotobaState* state, long pFileId, const Licenses* licenses, const MatchCallbacks* callbacks, char* delimiters);
int matchFileWithLicenses(KotobaState* state, const File* file, const Licenses* licenses, const MatchCallbacks* callbacks);

void findDiffMatches(const File* file, const License* license,
                     size_t textStartPosition, size_t searchStartPosition,
                     GArray* matches,
                     unsigned maxAllowedDiff, unsigned minAdjacentMatches);

GArray* filterNonOverlappingMatches(GArray* matches);
int match_partialComparator(const Match* thisMatch, const Match* otherMatch);

int processMatches(KotobaState* state, const File* file, const GArray* matches, const MatchCallbacks* callbacks);

char* formatMatchArray(GArray* matchInfo);

#endif // KOTOBA_AGENT_MATCH_H
