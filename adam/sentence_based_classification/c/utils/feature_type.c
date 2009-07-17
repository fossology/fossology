#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string.h>
#include <ctype.h>
#include "feature_type.h"
#include <libstemmer.h>

struct sb_stemmer * stemmer = NULL;

void feature_type_free(void *v) {
    feature_type *ft;
    ft = (feature_type*)v;
    free(ft->string);
    free(ft->stemmed);
    free(ft);
}

void* feature_type_create_from_string(char *string, int start, int end) {
    int i = 0;
    feature_type *t = (feature_type*)malloc(sizeof(feature_type));
    sb_symbol * b = (sb_symbol *) malloc(end-start * sizeof(sb_symbol));

    if (end<=start) {
        return NULL;
    }
    if (stemmer==NULL) {
        stemmer = sb_stemmer_new("english", NULL);
    }


    t->string = (char*)malloc(sizeof(char)*(end-start)+1);
    strncpy(t->string,string+start,end-start);
    t->string[end-start] = '\0';

    t->capped = isupper(t->string[0]);
    t->upper = true;
    t->number = true;
    t->incnum = false;
    t->word = true;

    for (i = 0; i<end-start; i++) {
        t->upper = t->upper && isupper(t->string[i]);
        if (isupper(t->string[i])) {
            b[i] = tolower(t->string[i]);
        } else {
            b[i] = t->string[i];
        }
        if (('0' <= t->string[i] && t->string[i] <= '9') || ('a' <= b[i] && b[i] <= 'z')) {
            if ('0' <= t->string[i] && t->string[i] <= '9') {
                t->incnum = t->incnum || true;
                t->number = t->number && true;
            } else {
                t->number = false;
            }
            t->word = t->word && true;
        } else {
            t->number = false;
            t->word = false;
            t->incnum = t->incnum || false;
        }
    }
    const sb_symbol * stemmed = sb_stemmer_stem(stemmer, b, end-start);
    t->stemmed = (char*)malloc(sizeof(char)*(end-start)+1);
    for (i = 0; stemmed[i] != 0; i++) {
        t->stemmed[i] = stemmed[i];
    }
    t->stemmed[i] = '\0';

    t->start = start;
    t->end = end;
    t->length = end-start;
    
    return t;
}

void feature_type_print(void *v) {
    feature_type *ft;
    ft = (feature_type*)v;
    printf("{\n");
    printf("  string:  '%s',\n", ft->string);
    printf("  stemmed: '%s',\n", ft->stemmed);
    printf("  word:    '%s',\n", (ft->word)?"true":"false");
    printf("  capped:  '%s',\n", (ft->capped)?"true":"false");
    printf("  upper:   '%s',\n", (ft->upper)?"true":"false");
    printf("  number:  '%s',\n", (ft->number)?"true":"false");
    printf("  incnum:  '%s',\n", (ft->incnum)?"true":"false");
    printf("  length:  '%d'\n", ft->length);
    printf("}\n");
}
