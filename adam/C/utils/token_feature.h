/*
   Functions for a token feature list
 */
#ifndef __TOKEN_FEATURE_H__
#define __TOKEN_FEATURE_H__

#if defined(__cplusplus)
extern "C" {
#endif

/* std library */
#include <stdio.h>
#include <stdlib.h>

/* local includes */
#include "cvector.h"

typedef int c_bool;
#define FALSE 0
#define TRUE (1)
#define FT_CHAR_MAP " \n.:;,!?"
#define FT_CHAR_MAP_LEN 8

/*!
 * Our token feature datatype
 *
 * TODO fill this in
 */
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

/*!
 * \brief creates a vector function registry for a cvector of token_features
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* token_feature_cvector_registry();

/*!
 * \brief takes a string and creates a token_feature from it
 *
 * this function will create the token_feature struct that belongs to a certain
 * string. This will allocate memory for the string in the token and will
 * set the start, end and length
 *
 * \param string: the string that will be stored in this token_feature
 * \param start: the start location of the string in the source file
 * \param end: the location of the end of the string in the source file
 */
void* token_feature_create_from_string(char *string, int start, int end);

#if defined(__cplusplus)
}
#endif

#endif
