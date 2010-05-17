/*********************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 **********************************************************************/

/* std library */
#include <string.h>

/* local includes */
#include <cvector.h>

/* **************************************************************************** */
/* **** Private Functions ***************************************************** */
/* **************************************************************************** */

void _cvector_resize(cvector* vec) {
  /* local variables */
  int i;

  /* vec will double the size and copy over the data */
  vec->capacity = vec->capacity*2;

  /* allocate the new array to hold the data */
  void** newdata = (void**)calloc(vec->capacity, sizeof(void*));

  /* copy all of the old data over from the old array */
  for(i = 0; i < vec->size; i++) {
    newdata[i] = vec->data[i];
  }

  /* fill the rest of the array with NULL */
  for(i = vec->size; i < vec->capacity; i++) {
    newdata[i] = NULL;
  }

  /* delete the old data and set the pointer to the new data */
  free(vec->data);
  vec->data = newdata;
}

/* **************************************************************************** */
/* **** Constructor Destructor ************************************************ */
/* **************************************************************************** */

void cvector_init(cvector* vec, function_registry* memory_manager) {
  vec->size = 0;
  vec->capacity = 1;
  vec->data = (void**)calloc(1, sizeof(void*));
  vec->memory = memory_manager;
}

void cvector_copy(cvector* dst, cvector* src) {
  /* locals */
  cvector_iterator iter = NULL;

  /* initialize the new cvector */
  cvector_init(dst, cvector_registry_copy(src));

  /* loop over the old cvector and copy everything over */
  for(iter = cvector_begin(src); iter != cvector_end(src); iter++) {
    cvector_push_back(dst, *iter);
  }
}

void cvector_destroy(cvector* vec) {
  int i;

  /* loop through  every datum and call the delete on it */
  for(i = 0; i < vec->size; i++) {
    vec->memory->destroy(vec->data[i]);
  }

  /* free the data in the cvector and free the functions*/
  free(vec->data);
  free(vec->memory);

  vec->size = 0;
  vec->capacity = 0;
  vec->data = NULL;
  vec->memory = NULL;
}

/* **************************************************************************** */
/* **** Insertion Functions *************************************************** */
/* **************************************************************************** */

void cvector_push_back(cvector* vec, void* datum) {
  /* test if the cvector needs resizing */
  if(vec->size == vec->capacity) {
    _cvector_resize(vec);
  }

  /* there is enough room for a new element,  */
  /* increase the size and store the element  */
  /* using the copy function                  */
  if(datum != NULL) {
    vec->data[vec->size++] = vec->memory->copy(datum);
  } else {
    vec->data[vec->size++] = NULL;
  }
}

void cvector_insert(cvector* vec, cvector_iterator iter, void* datum) {
  /* since vec function does bounds checking, do so */
  if(iter < cvector_begin(vec) || iter >= cvector_end(vec)) {
    fprintf(stderr, "cvector.c: ERROR: array index access out of bounds.\n");
    fprintf(stderr, "cvector.c: ERROR: iterator is outside of array.");
    exit(-1);
  }

  /* test if the cvector needs to be bigger */
  if(vec->size == vec->capacity) {
    _cvector_resize(vec);
  }

  /* do the actual insert function */
  vec->size++;
  while(iter != cvector_end(vec)) {
    void* temp = *iter;
    *iter = datum;
    datum = temp;
    iter++;
  }
}

/* **************************************************************************** */
/* **** Removal Functions ***************************************************** */
/* **************************************************************************** */

cvector_iterator cvector_remove(cvector* vec, cvector_iterator iter) {
  /* save the input iterator so that it can be returned */
  cvector_iterator old_pos = iter;

  /* since vec function does bounds checking, do so */
  if(iter < cvector_begin(vec) || iter >= cvector_end(vec)) {
    fprintf(stderr, "cvector.c: ERROR: array index access out of bounds.\n");
    fprintf(stderr, "cvector.c: ERROR: iterator is outside of array.");
    exit(-1);
  }

  /* delete the element */
  vec->memory->destroy(*iter);

  /* do the actual remove function */
  vec->size--;
  while(iter != cvector_end(vec)) {
    *iter = *(iter+1);
    iter++;
  }
  *iter = NULL;

  return old_pos;
}

/* **************************************************************************** */
/* **** Access Functions ****************************************************** */
/* **************************************************************************** */

void* cvector_get(cvector* vec, int index) {
  return vec->data[index];
}

void* cvector_at(cvector* vec, int index) {
  /* since vec function does bounds checking, do so */
  if(index < 0 || index >= vec->size) {
    fprintf(stderr, "cvector.c: ERROR: array index access out of bounds.\n");
    fprintf(stderr, "cvector.c: ERROR: size: %d, accessed index: %d.", vec->size, index);
    exit(-1);
  }
  return vec->data[index];
}

cvector_iterator cvector_begin(cvector* vec) {
  return vec->data;
}

cvector_iterator cvector_end(cvector* vec) {
  return vec->data + vec->size;
}

/* **************************************************************************** */
/* **** Print Functions ******************************************************* */
/* **************************************************************************** */

void cvector_print(cvector* vec, FILE* pfile) {
  /* local variables */
  int length = strlen(vec->memory->name);
  cvector_iterator iter;
  /* write the name and size of the list to the file */
  fwrite(&length, sizeof(int), 1, pfile);
  fwrite(vec->memory->name, sizeof(char), strlen(vec->memory->name), pfile);
  fwrite(&vec->size, sizeof(int), 1, pfile);
  /* loop through the data and print every element */
  for(iter = cvector_begin(vec); iter != cvector_end(vec); iter++) {
    vec->memory->print(*iter, pfile);
  }
}

/* **************************************************************************** */
/* **** Memory Functions (Private) ******************************************** */
/* **************************************************************************** */

/* integer memory management functions */
void* _int_copy(void* to_copy) { int* i = (int*)calloc(1, sizeof(int)); *i = *(int*)to_copy; return i; }
void  _int_destroy(void* to_delete) { free(to_delete); }
void  _int_print(void* to_print, FILE* pfile) { fprintf(pfile, "%d\t", *((int*)to_print)); }

/* character memory management functions */
void* _char_copy(void* to_copy) { char* c = (char*)calloc(1, sizeof(char)); *c = *(char*)to_copy; return c; }
void  _char_destroy(void* to_delete) { free(to_delete); }
void  _char_print(void* to_print, FILE* pfile) { fprintf(pfile, "%c", *((char*)to_print)); }

/* double memory management functions */
void* _double_copy(void* to_copy) { double* d = (double*)calloc(1, sizeof(double)); *d = *(double*)to_copy; return d; }
void  _double_destroy(void* to_delete) { free(to_delete); }
void  _double_print(void* to_print, FILE* pfile) { fprintf(pfile, "%f", *((double*)to_print)); }

/* string memory management functions */
void* _string_copy(void* to_copy) { char* s = (char*)calloc(strlen((char*)to_copy), sizeof(double)); strcpy(s, (char*)to_copy); return s; }
void  _string_destroy(void* to_delete) { free(to_delete); }
void  _string_print(void* to_print, FILE* pfile) { fprintf(pfile, "%s", (char*)to_print); }

/* string memory management functions */
void* _cvector_copy(void* to_copy) { cvector* v = (cvector*)calloc(1, sizeof(cvector)); cvector_copy(v, (cvector*)to_copy); return v; }
void  _cvector_destroy(void* to_delete) { cvector_destroy((cvector*)to_delete); free(to_delete); }
void  _cvector_print(void* to_print, FILE* pfile) { cvector_print((cvector*)to_print, pfile); }

/* **************************************************************************** */
/* **** Memory Functions (Public) ********************************************* */
/* **************************************************************************** */

function_registry* cvector_registry_copy(cvector* vec) {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = vec->memory->name;
  ret->copy = vec->memory->copy;
  ret->destroy = vec->memory->destroy;
  ret->print = vec->memory->print;

  return ret;
}

function_registry* int_cvector_registry() {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "integer";
  ret->copy = &_int_copy;
  ret->destroy = &_int_destroy;
  ret->print = &_int_print;

  return ret;
}

function_registry* char_cvector_registry() {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "character";
  ret->copy = &_char_copy;
  ret->destroy = &_char_destroy;
  ret->print = &_char_print;

  return ret;
}

function_registry* double_cvector_registry() {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "float";
  ret->copy = &_double_copy;
  ret->destroy = &_double_destroy;
  ret->print = &_double_print;

  return ret;
}

function_registry* string_cvector_registry() {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "string";
  ret->copy = &_string_copy;
  ret->destroy = &_string_destroy;
  ret->print = &_string_print;

  return ret;
}

function_registry* cvector_cvector_registry() {
  function_registry* ret = (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "cvector";
  ret->copy = &_cvector_copy;
  ret->destroy = &_cvector_destroy;
  ret->print = &_cvector_print;

  return ret;
}



