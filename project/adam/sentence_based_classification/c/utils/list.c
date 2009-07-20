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
#include "list.h"

SGLIB_DEFINE_DL_LIST_PROTOTYPES(default_list, DEFAULT_COMPARATOR, ptr_to_previous, ptr_to_next);
SGLIB_DEFINE_DL_LIST_FUNCTIONS(default_list, DEFAULT_COMPARATOR, ptr_to_previous, ptr_to_next);

void default_list_append(default_list **list, void **data) {
    default_list *last, *l;
    last = sglib_default_list_get_last(*list);
    l = (default_list*)malloc(sizeof(default_list));
    l->data = data[0];
    if (last == NULL) {
        sglib_default_list_add_after(list,l);
    } else {
        sglib_default_list_add_after(&last,l);
    }
}

int default_list_length(default_list **list) {
    return sglib_default_list_len(list[0]);
}

int default_list_get(default_list **list, int index, void **data) {
    default_list *l;   
    int i;
    if (index < 0 || index > sglib_default_list_len(list[0])) {
        return -1;
    }
    i = 0;
    for (l=sglib_default_list_get_first(list[0]); l!=NULL; l=l->ptr_to_next) {
        if (i == index) {
            data[0] = l->data;
            return 0;
        }
        i++;
    }
}

void default_list_free(default_list **list, void (*freeFunc)(void*)) {
    struct sglib_default_list_iterator  it;    
    default_list *l;
    for (l=sglib_default_list_it_init(&it,list[0]); l!=NULL; l=sglib_default_list_it_next(&it)) {
        freeFunc(l->data);
        free(l);
    }
    list[0] = NULL;
}

void default_list_print(default_list **list, void (*printFunc)(void *)) {
    struct sglib_default_list_iterator  it;
    default_list *l;
    printf("( ");
    l=sglib_default_list_it_init(&it,list[0]);
    if (l!=NULL) {
        printFunc(l->data);
    }
    for (l=sglib_default_list_it_next(&it); l!=NULL; l=sglib_default_list_it_next(&it)) {
        printf(", ");
        printFunc(l->data);
    }
    printf(" )\n");

}
