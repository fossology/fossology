#ifndef __STEM__H_
#define __STEM__H_

#if defined(__cplusplus)
extern "C" {
#endif

// Our simple datatype
typedef struct stem {
    char *string;
    char *stemmed;
    int start;
    int end;
} stem;

// This function frees all the internal data in the datatype.
void stem_free(void *v);

// This function is used to create the datatype from a substring.
void* stem_create_from_string(char *string, int start, int end);

// This function is used to print the datatype.
void stem_print(void *v);

#if defined(__cplusplus)
}
#endif

#endif
