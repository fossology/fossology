#ifndef __DEFAULT_LIST_BASIC_H__
#define __DEFAULT_LIST_BASIC_H__

#if defined(__cplusplus)
extern "C" {
#endif

#include <stdlib.h>
#include <stdio.h>

// all functions are in the format 'default_list_type_function_TYPE_FUNCNAME'.

/*
   Functions for an integer list
*/
int default_list_type_int(void);
void default_list_type_int_init(void);
void* default_list_type_function_int_create(void *v);
void* default_list_type_function_int_copy(void *v);
void default_list_type_function_int_destroy(void *v);
void default_list_type_function_int_print(void *v, FILE *f);
int default_list_type_function_int_dump(void *v, FILE *f);
void* default_list_type_function_int_load(FILE *f);

/*
   Functions for a double list
*/
int default_list_type_double(void);
void default_list_type_double_init(void);
void* default_list_type_function_double_create(void *v);
void* default_list_type_function_double_copy(void *v);
void default_list_type_function_double_destroy(void *v);
void default_list_type_function_double_print(void *v, FILE *f);
int default_list_type_function_double_dump(void *v, FILE *f);
void* default_list_type_function_double_load(FILE *f);

/*
   Functions for string list
*/
int default_list_type_string(void);
void default_list_type_string_init(void);
void* default_list_type_function_string_create(void *v);
void* default_list_type_function_string_copy(void *v);
void default_list_type_function_string_destroy(void *v);
void default_list_type_function_string_print(void *v, FILE *f);
int default_list_type_function_string_dump(void *v, FILE *f);
void* default_list_type_function_string_load(FILE *f);

#if defined(__cplusplus)
}
#endif

#endif
