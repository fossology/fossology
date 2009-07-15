#ifndef __FEATURE_TYPE__H_
#define __FEATURE_TYPE__H_

// Our simple datatype
typedef struct feature_type {
    char *string;
    int length;
    bool word;
    bool capped;
    bool upper;
    bool number;
    bool incnum;
    char *stemmed;
} feature_type;

// This function frees all the internal data in the datatype.
void feature_type_free(void *v);

// This function is used to create the datatype from a substring.
void* feature_type_create_from_string(char *string, int start, int end);

// This function is used to print the datatype.
void feature_type_print(void *v);

#endif
