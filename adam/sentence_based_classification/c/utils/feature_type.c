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
#include "feature_type.h"
#include <libstemmer.h>

struct sb_stemmer * stemmer = NULL;

int char_count(unsigned char *str, unsigned char c) {
    unsigned char *char_ptr;
    int count = 0;
    for (char_ptr = str; *char_ptr != '\0'; char_ptr++) {
        if (c==*char_ptr) {
            count++;
        }   
    }
    return count;
}

void feature_type_free(void *v) {
    feature_type *ft;
    ft = (feature_type*)v;
    free(ft->string);
    free(ft->stemmed);
    free(ft);
}

void* feature_type_create_from_string(unsigned char *string, int start, int end) {
    int i = 0;
    feature_type *t = (feature_type*)malloc(sizeof(feature_type));
    sb_symbol * b = (sb_symbol *) malloc(end-start * sizeof(sb_symbol));

    if (end<=start) {
        return NULL;
    }
    if (stemmer==NULL) {
        stemmer = sb_stemmer_new("english", NULL);
    }


    t->string = (unsigned char*)malloc(sizeof(unsigned char)*(end-start)+1);
    strncpy(t->string,string+start,end-start);
    t->string[end-start] = '\0';

    t->capped = isupper(t->string[0]);
    t->upper = TRUE;
    t->number = TRUE;
    t->incnum = FALSE;
    t->word = TRUE;

    for (i = 0; i<end-start; i++) {
        t->upper = t->upper && isupper(t->string[i]);
        if (isupper(t->string[i])) {
            b[i] = tolower(t->string[i]);
        } else {
            b[i] = t->string[i];
        }
        if (('0' <= t->string[i] && t->string[i] <= '9') || ('a' <= b[i] && b[i] <= 'z')) {
            if ('0' <= t->string[i] && t->string[i] <= '9') {
                t->incnum = t->incnum || TRUE;
                t->number = t->number && TRUE;
            } else {
                t->number = FALSE;
            }
            t->word = t->word && TRUE;
        } else {
            t->number = FALSE;
            t->word = FALSE;
            t->incnum = t->incnum || FALSE;
        }
    }
    const sb_symbol * stemmed = sb_stemmer_stem(stemmer, b, end-start);
    t->stemmed = (unsigned char*)malloc(sizeof(unsigned char)*(end-start)+1);
    for (i = 0; stemmed[i] != 0; i++) {
        t->stemmed[i] = stemmed[i];
    }
    t->stemmed[i] = '\0';

    t->start = start;
    t->end = end;
    t->length = end-start;

    if (t->word==FALSE) {
        for (i=0; i<FT_CHAR_MAP_LEN; i++) {
            t->char_vector[i] = char_count(t->string,FT_CHAR_MAP[i]);
        }
    }
    
    return t;
}

void feature_type_print(void *v) {
    feature_type *ft;
    ft = (feature_type*)v;
    printf("{\n");
    printf("  string:  '%s',\n", ft->string);
    printf("  stemmed: '%s',\n", ft->stemmed);
    printf("  word:    '%s',\n", (ft->word==TRUE)?"true":"false");
    printf("  capped:  '%s',\n", (ft->capped==TRUE)?"true":"false");
    printf("  upper:   '%s',\n", (ft->upper==TRUE)?"true":"false");
    printf("  number:  '%s',\n", (ft->number==TRUE)?"true":"false");
    printf("  incnum:  '%s',\n", (ft->incnum==TRUE)?"true":"false");
    printf("  length:  '%d'\n", ft->length);
    printf("}\n");
}
