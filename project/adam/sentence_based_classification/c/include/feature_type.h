#ifndef __FEATURE_TYPE__H_
#define __FEATURE_TYPE__H_

#if defined(__cplusplus)
extern "C" {
#endif

typedef int c_bool;
#define FALSE 0
#define TRUE (1)

// Our simple datatype
typedef struct feature_type {
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
} feature_type;

// This function frees all the internal data in the datatype.
void feature_type_free(void *v);

// This function is used to create the datatype from a substring.
void* feature_type_create_from_string(char *string, int start, int end);

// This function is used to print the datatype.
void feature_type_print(void *v);

#if defined(__cplusplus)
}
#endif

#endif
