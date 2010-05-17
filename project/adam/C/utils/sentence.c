/*********************************************************************
Copyright (C) 2009, 2010 Hewlett-Packard Development Company, L.P.

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

/* std library includes */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>

/* other libraries */
#include <cvector.h>

/* local includes */
#include "sentence.h"

void* _sentence_copy(void *v) {
  sentence *temp = (sentence*)calloc(1, sizeof(sentence));
  sentence *s    = (sentence*)v;
  if (temp == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    return NULL;
  }
  if (v != NULL) {
    temp->string = malloc(strlen(s->string)+1);
    if (temp->string == NULL) {
      fprintf(stderr, "Memory error at line %d in file %s.\n",
          __LINE__, __FILE__);
      free(temp);
      return NULL;
    }
    strcpy(temp->string,s->string);
    temp->filename = malloc(strlen(s->filename)+1);
    if (temp->filename == NULL) {
      fprintf(stderr, "Memory error at line %d in file %s.\n",
          __LINE__, __FILE__);
      free(temp->string);
      free(temp);
      return NULL;
    }
    strcpy(temp->filename,s->filename);
    temp->licensename = malloc(strlen(s->licensename)+1);
    if (temp->licensename == NULL) {
      fprintf(stderr, "Memory error at line %d in file %s.\n",
          __LINE__, __FILE__);
      free(temp->filename);
      free(temp->string);
      free(temp);
      return NULL;
    }
    strcpy(temp->licensename,s->licensename);
    temp->id = s->id;

    temp->vector = (sv_vector)sv_copy(s->vector);

    temp->start = s->start;
    temp->end = s->end;
    temp->position = s->position;
  } else {
    free(temp);
    temp = NULL;
  }
  return (void *)temp;
}

void _sentence_destroy(void *v) {
  sentence *s = v;
  free(s->licensename);
  free(s->filename);
  free(s->string);
  sv_delete(s->vector);
  free(s);
}

void _sentence_print(void *v, FILE *f) {
  sentence *t;
  int temp;
  t = (sentence*)v;

  temp = strlen(t->string)+1;
  fwrite(&temp, sizeof(int),1,f);
  fwrite(t->string, sizeof(char),temp,f);
  fwrite(&t->start, sizeof(int),1,f);
  fwrite(&t->end, sizeof(int),1,f);
  fwrite(&t->position, sizeof(int),1,f);
  temp = strlen(t->filename)+1;
  fwrite(&temp, sizeof(int),1,f);
  fwrite(t->filename, sizeof(char),strlen(t->filename)+1,f);
  temp = strlen(t->licensename)+1;
  fwrite(&temp, sizeof(int),1,f);
  fwrite(t->licensename, sizeof(char),strlen(t->licensename)+1,f);
  fwrite(&t->id, sizeof(int),1,f);
  sv_dump(t->vector,f);
}

function_registry* sentence_cvector_registry() {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "sentence";
  ret->copy = &_sentence_copy;
  ret->destroy = &_sentence_destroy;
  ret->print = &_sentence_print;

  return ret;
}

sentence* sentence_create(char *string, int start, int end, int position, char *filename, char *licensename, int id, sv_vector vector) {
  sentence *temp = (sentence*)calloc(1, sizeof(sentence));
  if (temp == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    return NULL;
  }

  temp->string = (char*)malloc(sizeof(char)*(end-start)+1);
  if (temp->string == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    free(temp);
    return NULL;
  }
  strncpy(temp->string,string+start,end-start);
  temp->string[end-start] = '\0';

  temp->filename = (char*)malloc(sizeof(char)*(strlen(filename))+1);
  if (temp->filename == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    free(temp->string);
    free(temp);
    return NULL;
  }
  strcpy(temp->filename,filename);

  temp->licensename = (char*)malloc(sizeof(char)*(strlen(licensename))+1);
  if (temp->licensename == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    free(temp->filename);
    free(temp->string);
    free(temp);
    return NULL;
  }
  strcpy(temp->licensename,licensename);

  temp->id = id;

  temp->start = start;
  temp->end = end;
  temp->position = position;

  temp->vector = vector;

  return temp;
}

sentence* sentence_destroy(sentence* sent) {
  _sentence_destroy(sent);
}
