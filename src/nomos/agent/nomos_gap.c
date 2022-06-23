/*
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "nomos_gap.h"
#include <stdio.h>      /* printf, scanf, NULL */
#include <stdlib.h>     /* malloc, free, rand */

/**
 * \file
 * Compile using `gcc nomos_gap.c $(pkg-config glib-2.0 --cflags --libs) -std=c99`
 */

/**
 * collapse text by elimination of invisible char, returns position+offset pairs
 */
GArray* collapseInvisible(char* text, char invisible)
{
  int offset = 0;
  char* readPointer = text;
  char* writePointer = text;
  int iAmVisible;
  GArray* pairs = g_array_new(FALSE, FALSE, sizeof(pairPosOff));
  for( iAmVisible = FALSE; *readPointer; readPointer++)
  {
    if(*readPointer == invisible){
      offset++;
      iAmVisible = FALSE;
      continue;
    }
    // now: *readPointer != invisible
    if (!iAmVisible){
      pairPosOff pair;
      pair.pos = writePointer-text;
      pair.off = offset;
      g_array_append_val(pairs, pair);
      iAmVisible = TRUE;
    }
    *writePointer++ = *readPointer;
  }
  *writePointer = '\0';
  return pairs;
}

/**
 * Collapse spaces in text, returns position+offset pairs
 * \todo delete me
 */
GArray* collapseSpaces(char* text)
{
  int start = 0;
  int cutOff; /* -1,0,1,... */
  char* readPointer = text;
  char* writePointer = text;
  GArray* pairs = g_array_new(FALSE, FALSE, sizeof(pairPosOff));
  for( cutOff=1; *readPointer; readPointer++)
  {
    if((*readPointer!=' ') && (cutOff>0)){
      pairPosOff pair;
      pair.pos = start;
      pair.off = readPointer-writePointer;
      g_array_append_val(pairs, pair);
    }
    if(*readPointer==' '){
      cutOff++;
    }
    else{ // far away from cutting
      cutOff = -1;
    }
    if (cutOff==0){
      start = writePointer+1-text;
    }
    if (cutOff < 1){
      *writePointer++ = *readPointer;
    }
  }
  *writePointer = '\0';
  return pairs;
}

inline pairPosOff* getPairPosOff(GArray* in, int index)
{
  return &g_array_index(in, pairPosOff, index);
}

int uncollapsePosition(int collapsedPos, GArray* shifter)
{
  int shifterIndex;
  pairPosOff* thePoA;
  for (shifterIndex = 1; shifterIndex < shifter->len; ++shifterIndex) {
    thePoA = getPairPosOff(shifter, shifterIndex);
    if (thePoA->pos > collapsedPos) {
      break;
    }
  }
  thePoA = getPairPosOff(shifter, shifterIndex - 1);
  return collapsedPos + thePoA->off;
}
