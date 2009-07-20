#ifndef __TOKEN__H_
#define __TOKEN__H_

#if defined(__cplusplus)
extern "C" {
#endif

// Our simple datatype
typedef struct token {
    char *string;
} token;

// This function frees all the internal data in the datatype.
void token_free(void *v);

// This function is used to create the datatype from a substring.
void* token_create_from_string(char *string, int start, int end);

// This function is used to print the datatype.
void token_print(void *v);

#if defined(__cplusplus)
}
#endif

#endif
