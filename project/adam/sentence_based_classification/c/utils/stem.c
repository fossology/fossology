#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string.h>
#include <ctype.h>
#include "stem.h"
#include "libstemmer.h"

struct sb_stemmer * stemmer = NULL;

void stem_free(void *v) {
    stem *t;
    t = (stem*)v;
    free(t->string);
    free(t->stemmed);
    free(t);
}

void* stem_create_from_string(char *string, int start, int end) {
    int i = 0;
    stem *t = (stem*)malloc(sizeof(stem));
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
    
    for (i = 0; i<end-start; i++) {
        if (isupper(t->string[i])) {
            b[i] = tolower(t->string[i]);
        } else {
            b[i] = t->string[i];
        }
    }
    const sb_symbol * stemmed = sb_stemmer_stem(stemmer, b, end-start);
    t->stemmed = (char*)malloc(sizeof(char)*(end-start)+1);
    for (i = 0; stemmed[i] != 0; i++) {
        t->stemmed[i] = stemmed[i];
    }
    t->stemmed[i+1] = '\0';
    t->start = start;
    t->end = end;
    return t;
}

void stem_print(void *v) {
    stem *t;
    t = (stem*)v;
    printf("{ string: '%s', stemmed: '%s', start: %d, end: %d }",t->string, t->stemmed, t->start, t->end);
}
