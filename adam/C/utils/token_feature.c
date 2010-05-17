#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <libstemmer.h>
#include "token_feature.h"

/*
   Functions for a token_feature list
 */

int char_count(char *str, char c) {
  char *char_ptr;
  int count = 0;
  for (char_ptr = str; *char_ptr != '\0'; char_ptr++) {
    if (c==*char_ptr) {
      count++;
    }
  }
  return count;
}

void* _token_feature_copy(void *v) {
  int i;
  token_feature *rhs = v, * tf = NULL;

  /* allocate the copy of the token feature */
  tf = (token_feature*)calloc(1, sizeof(token_feature));
  if (tf == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    return NULL;
  }

  /* allocate the stemmed string in the new token_feature */
  tf->stemmed = (char*)calloc(strlen(rhs->stemmed)+1, sizeof(char));
  if (tf->stemmed == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    free(tf);
    return NULL;
  }
  strcpy(tf->stemmed, rhs->stemmed);

  /* allocate the normal string in the new token_feature */
  tf->string = malloc(strlen(rhs->string)+1);
  if (tf->string == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    free(tf->stemmed);
    free(tf);
    return NULL;
  }
  strcpy(tf->string, rhs->string);

  /* copy all of the other pieces over */
  tf->word = rhs->word;
  tf->start = rhs->start;
  tf->end = rhs->end;
  tf->length = rhs->length;
  tf->capped = rhs->capped;
  tf->upper = rhs->upper;
  tf->number = rhs->number;
  tf->incnum = rhs->incnum;
  for (i = 0; i < FT_CHAR_MAP_LEN; i++) {
    tf->char_vector[i] = rhs->char_vector[i];
  }

  return tf;
}

void _token_feature_destroy(void *v) {
  token_feature *tf = v;
  free(tf->stemmed);
  free(tf->string);
  free(v);
}

void _token_feature_print(void *v, FILE *f) {
  /* TODO don't like fix later */
  token_feature *tf = v;
  int len = strlen(tf->string) + 1;
  fwrite(&len, sizeof(int), 1, f);
  fwrite(tf->string, 1, len, f);
  len = strlen(tf->stemmed) + 1;
  fwrite(&len, sizeof(int), 1, f);
  fwrite(tf->stemmed, 1, len, f);
  fwrite(&tf->start, sizeof(int), 1, f);
  fwrite(&tf->end, sizeof(int), 1, f);
  fwrite(&tf->length, sizeof(int), 1, f);
  fwrite(&tf->word, sizeof(c_bool), 1, f);
  fwrite(&tf->capped, sizeof(c_bool), 1, f);
  fwrite(&tf->upper, sizeof(c_bool), 1, f);
  fwrite(&tf->number, sizeof(c_bool), 1, f);
  fwrite(&tf->incnum, sizeof(c_bool), 1, f);
  fwrite(tf->char_vector, sizeof(int), FT_CHAR_MAP_LEN, f);
}

function_registry* token_feature_cvector_registry() {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "token feature";
  ret->copy = &_token_feature_copy;
  ret->destroy = &_token_feature_destroy;
  ret->print = &_token_feature_print;

  return ret;
}

void* token_feature_create_from_string(char *string, int start, int end) {
  int i = 0;
  token_feature *t = malloc(sizeof(token_feature));
  sb_symbol* b = (sb_symbol *)calloc(end-start, sizeof(sb_symbol));
  struct sb_stemmer * stemmer = NULL;

  if (end<=start) {
    return NULL;
  }

  stemmer = sb_stemmer_new("english", NULL);

  t->string = malloc((end-start)+1);
  strncpy(t->string,string+start,end-start);
  t->string[end-start] = '\0';

  t->capped = isupper(t->string[0]);
  t->upper = TRUE;
  t->number = TRUE;
  t->incnum = FALSE;
  t->word = TRUE;

  for (i = 0; i<end-start; i++) {
    t->upper = t->upper && isupper(t->string[i]);
    if (isupper(t->string[i])) {
      b[i] = tolower(t->string[i]);
    } else {
      b[i] = t->string[i];
    }
    if (('0' <= t->string[i] && t->string[i] <= '9') || ('a' <= b[i] && b[i] <= 'z')) {
      if ('0' <= t->string[i] && t->string[i] <= '9') {
        t->incnum = t->incnum || TRUE;
        t->number = t->number && TRUE;
      } else {
        t->number = FALSE;
      }
      t->word = t->word && TRUE;
    } else {
      t->number = FALSE;
      t->word = FALSE;
      t->incnum = t->incnum || FALSE;
    }
  }
  const sb_symbol * stemmed = sb_stemmer_stem(stemmer, b, end-start);
  t->stemmed = (char*)malloc(sizeof(char)*(end-start)+1);
  for (i = 0; stemmed[i] != 0; i++) {
    t->stemmed[i] = stemmed[i];
  }
  t->stemmed[i] = '\0';

  t->start = start;
  t->end = end;
  t->length = end-start;

  if (t->word==FALSE) {
    for (i=0; i<FT_CHAR_MAP_LEN; i++) {
      t->char_vector[i] = char_count(t->string,FT_CHAR_MAP[i]);
    }
  } else {
    for (i=0; i<FT_CHAR_MAP_LEN; i++) {
      t->char_vector[i] = 0;
    }
  }

  sb_stemmer_delete(stemmer);
  free(b);

  return t;
}

