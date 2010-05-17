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

/* std library */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* local includes */
#include "token.h"

/* these functions should never be used outside of a function registry */
/* as a result, they are private to the token.c file and are not in    */
/* h file to prevent other files from using them					   */
void* _token_copy(void* to_copy) {
  /* local variables */
  token* rhs = (token*)to_copy;

  /* allocate space for the copied token */
  token* t = (token*)calloc(1, sizeof(token));
  if (t == NULL) {
    fprintf(stderr, "Memory error at line %d in file %s.\n",
        __LINE__, __FILE__);
    return NULL;
  }

  /* allocate space for the string in the copied token */
  t->string = (char*)calloc(strlen(rhs->string) + 1, sizeof(char));

  /* copy the data from the old token to the new one */
  strcpy(t->string, rhs->string);
  t->start = rhs->start;
  t->end = rhs->end;
  t->length = rhs->length;

  return t;
}

void  _token_destroy(void* to_delete) {
  free(((token*)to_delete)->string);
  free(to_delete);
}

void  _token_print(void* to_print, FILE* pfile) {
  fprintf(pfile, "%s", ((token*)to_print)->string);
  fprintf(pfile, "%d", ((token*)to_print)->start);
  fprintf(pfile, "%d", ((token*)to_print)->end);
  fprintf(pfile, "%d", ((token*)to_print)->length);
}

function_registry* token_cvector_registry() {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "token";
  ret->copy = &_token_copy;
  ret->destroy = &_token_destroy;
  ret->print = &_token_print;

  return ret;
}

void* token_create_from_string(char *string, int start, int end) {
  int i = 0;

  if (end<=start) {
    return NULL;
  }

  // TODO see if I can get rid of this allocation
  token *t = (token*)calloc(1,sizeof(token));

  // TODO see if I can get rid of tshis allocation
  t->string = malloc((end-start)+1);
  strncpy(t->string,string+start,end-start);
  t->string[end-start] = '\0';

  t->start = start;
  t->end = end;
  t->length = end-start;

  return t;
}


