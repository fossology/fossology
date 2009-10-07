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
#include <string.h>
#include "token.h"

/*
   Functions for a token list
*/
int default_list_type_token(void) {
    default_list_type_token_init();
    return default_list_type_id_by_name("token");
}

int default_list_type_token_init(void) {
    if (default_list_type_id_by_name("token") < 0) {
        default_list_register_type(
            "token",
            &default_list_type_function_token_create,
            &default_list_type_function_token_copy,
            &default_list_type_function_token_destroy,
            &default_list_type_function_token_print,
            &default_list_type_function_token_dump,
            &default_list_type_function_token_load);
    }
}

void* default_list_type_function_token_create(void *v) {
    int i;
    token *tf = v;
    token *temp = malloc(sizeof(token));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }

    if (tf != NULL) {
        temp->string = malloc(strlen(tf->string)+1);
        if (temp->string == NULL) {
            fprintf(stderr, "Memory error at line %d in file %s.\n",
                    __LINE__, __FILE__);
            free(temp);
            return NULL;
        }
        strcpy(temp->string,tf->string);
        temp->start = tf->start;
        temp->end = tf->end;
        temp->length = tf->length;
    } else {
        temp->string = NULL;
        temp->start = 0;
        temp->end = 0;
        temp->length = 0;
    }

    return (void *)temp;
}

void* default_list_type_function_token_copy(void *v) {
    int i;
    token *tf = v;
    token *temp = malloc(sizeof(token));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }

    if (tf != NULL) {
        temp->string = malloc(strlen(tf->string)+1);
        if (temp->string == NULL) {
            fprintf(stderr, "Memory error at line %d in file %s.\n",
                    __LINE__, __FILE__);
            free(temp);
            return NULL;
        }
        strcpy(temp->string,tf->string);
        temp->start = tf->start;
        temp->end = tf->end;
        temp->length = tf->length;
    } else {
        free(temp);
        temp = NULL;
    }
    return (void *)temp;
}

void default_list_type_function_token_destroy(void *v) {
    token *tf = v;
    free(tf->string);
    free(v);
}

void default_list_type_function_token_print(void *v, FILE *f) {
    token *tf = v;
    fprintf(f, "'%s'", tf->string);
}

int default_list_type_function_token_dump(void *v, FILE *f) {
    token *tf = v;
    int len = strlen(tf->string) + 1;
    fwrite(&len, sizeof(int), 1, f);
    fwrite(tf->string, 1, len, f);
    fwrite(&tf->start, sizeof(int), 1, f);
    fwrite(&tf->end, sizeof(int), 1, f);
    fwrite(&tf->length, sizeof(int), 1, f);
    return 0;
}

void* default_list_type_function_token_load(FILE *f) {
    token *tf = malloc(sizeof(token));
    if (tf == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    int len;
    fread(&len, sizeof(int), 1, f);
    tf->string = malloc(len);
    if (tf->string == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        free(tf);
        return NULL;
    }
    fread(tf->string, 1, len, f);
    fread(&tf->start, sizeof(int), 1, f);
    fread(&tf->end, sizeof(int), 1, f);
    fread(&tf->length, sizeof(int), 1, f);
    
    return (void *)tf;
}

token* token_create_from_string(char *string, int start, int end) {
    int i = 0;
    token *t = malloc(sizeof(token));

    if (end<=start) {
        return NULL;
    }

    t->string = malloc((end-start)+1);
    strncpy(t->string,string+start,end-start);
    t->string[end-start] = '\0';

    t->start = start;
    t->end = end;
    t->length = end-start;

    return t;
}


