/*
   Functions for a token feature list
*/
#ifndef __TOKEN_FEATURE_H__
#define __TOKEN_FEATURE_H__

#if defined(__cplusplus)
extern "C" {
#endif

#include <stdio.h>
#include <stdlib.h>
#include <default_list.h>

typedef int c_bool;
#define FALSE 0
#define TRUE (1)
#define FT_CHAR_MAP "\n.!?"
#define FT_CHAR_MAP_LEN 4
// #define FT_CHAR_MAP "\n.:;,/\\|~`!@#$%^&*-_=+?()[]{}<>"
// #define FT_CHAR_MAP_LEN 31

// Our simple datatype
typedef struct token_feature {
    char *string;
    char *stemmed;
    int start;
    int end;
    int length;
    c_bool word;
    c_bool capped;
    c_bool upper;
    c_bool number;
    c_bool incnum;
    int char_vector[FT_CHAR_MAP_LEN];
} token_feature;

int default_list_type_token_feature(void);
int default_list_type_token_feature_init(void);
void* default_list_type_function_token_feature_create(void *v);
void* default_list_type_function_token_feature_copy(void *v);
void default_list_type_function_token_feature_destroy(void *v);
void default_list_type_function_token_feature_print(void *v, FILE *f);
int default_list_type_function_token_feature_dump(void *v, FILE *f);
void* default_list_type_function_token_feature_load(FILE *f);
token_feature* token_feature_create_from_string(char *string, int start, int end);

#if defined(__cplusplus)
}
#endif

#endif
