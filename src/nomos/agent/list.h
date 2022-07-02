/*
 SPDX-FileCopyrightText: © 2006-2009 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _LIST_H
#define _LIST_H
#include "nomos.h"
void listInit(list_t *l, int size, char *label);
void listClear(list_t *l, int deallocFlag);
item_t *listGetItem(list_t *l, char *s);
item_t *listAppend(list_t *l, char *s);

#ifdef notdef
item_t *listLookupName(list_t *l, char *s);
item_t *listLookupAlias(list_t *l, char *s);
#endif /* notdef */

item_t *listIterate(list_t *l);
void listIterationReset(list_t *l);
int listDelete(list_t *l, item_t *p);
void listSort(list_t *l, int sortType);
int listCount(list_t *l);
void listDump(list_t *l, int verbose);

#define DEALLOC_LIST	1
#define NOTOUCH_LIST	0

#endif /* _LIST_H */
