/*
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef NOMOS_GAP_H
#define	NOMOS_GAP_H
#ifndef  _GNU_SOURCE
#define  _GNU_SOURCE
#endif   /* not defined _GNU_SOURCE */
#include <glib.h>

struct PairPosOff {
  int pos;
  int off;
};
typedef struct PairPosOff pairPosOff;

pairPosOff* getPairPosOff(GArray* in, int index);
int uncollapsePosition(int collapsedPos, GArray* shifter);
GArray* collapseSpaces(char* text);
GArray*  collapseInvisible(char* text, char invisible);
#endif	/* NOMOS_GAP_H */
