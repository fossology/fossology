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
#include <ctype.h>
#include "token.h"

void token_free(void *v) {
    token *t;
    t = (token*)v;
    free(t->string);
    free(t);
}

void* token_create_from_string(char *string, int start, int end) {
    if (end<=start) {
        return NULL;
    }
    token *t = (token*)malloc(sizeof(token));
    t->string = (char*)malloc(sizeof(char)*(end-start)+1);
    strncpy(t->string,string+start,end-start);
    t->string[end-start] = '\0';
    return t;
}

void token_print(void *v) {
    token *t;
    t = (token*)v;
    printf("'%s'",t->string);
}
