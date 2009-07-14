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
