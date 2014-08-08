/***************************************************************
 Copyright (C) 2006-2009 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/
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
