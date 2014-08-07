/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#ifndef MONK_AGENT_MATCH_H
#define MONK_AGENT_MATCH_H

#include <glib.h>

#include "monk.h"
#include "string_operations.h"
#include "diff.h"

#define MATCH_TYPE_FULL 0
#define MATCH_TYPE_DIFF 1



typedef struct {
  License* license;
  union {
    DiffPoint* full;
    DiffResult* diff;
  } ptr;
  int type;
} Match;


void match_array_free(GArray* matches);
void match_free(Match* match);

#ifdef GLIB_VERSION_2_32
void match_destroyNotify(gpointer matchP);
#endif

size_t match_getStart(const Match* match);
size_t match_getEnd(const Match* match);

gint compareMatchByRank (gconstpointer  a, gconstpointer  b);
gint compareMatchIncuded (gconstpointer  a, gconstpointer  b);
Match* greatestMatchInGroup(GArray* matches, GCompareFunc compare);

void appendMatchesBetween(File* file, License* license, GArray* matches,
                          int maxAllowedDiff, int minTrailingMatches);

GArray* findAllMatchesBetween(File* file, GArray* licenses,
                              int maxAllowedDiff, int minTrailingMatches);


void matchPFileWithLicenses(MonkState* state, long pFileId, GArray* licenses);
void matchFileWithLicenses(MonkState* state, File* file, GArray* licenses);

void findDiffMatches(File* file, License* license, GArray* matches,
                     int maxAllowedDiff, int minTrailingMatches);

GArray* filterNonOverlappingMatches(GArray* matches);
void processMatches(MonkState* state, File* file, GArray* matches);

char* formatMatchArray(GArray * matchInfo);

#endif	/* MONK_AGENT_MATCH_H */
