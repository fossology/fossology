/*********************************************************************
Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

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
*********************************************************************/

#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string.h>
#include "sglib.h"

#ifndef _DEFAULT_LIST__h_
#define _DEFAULT_LIST__h_

#if defined(__cplusplus)
extern "C" {
#endif

typedef struct default_list {
    struct default_list *ptr_to_next;
    struct default_list *ptr_to_previous;
    void *data;
} default_list;

#define DEFAULT_COMPARATOR(s1, s2) (1-1)

void default_list_append(default_list **list, void **data);
int default_list_length(default_list **list);
int default_list_get(default_list **list, int index, void **data);
void default_list_free(default_list **list, void (*freeFunc)(void *));
void default_list_print(default_list **list, void (*printFunc)(void *));

#if defined(__cplusplus)
}
#endif

#endif

