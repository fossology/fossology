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

#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string.h>
#include <ctype.h>
#include "sentence_type.h"

void sentence_type_free(void *v) {
    sentence_type *t;
    t = (sentence_type*)v;
    free(t->string);
    free(t->filename);
    free(t->licensename);
    sv_delete(*t->vector);
    free(t->vector);
    free(t);
}

void* sentence_type_create(unsigned char *string, int start, int end, int position, unsigned char *filename, unsigned char *licensename, sv_vector *vector) {
    sentence_type *t = (sentence_type*)malloc(sizeof(sentence_type));

    t->string = (unsigned char*)malloc(sizeof(unsigned char)*(end-start)+1);
    strncpy(t->string,string+start,end-start);
    t->string[end-start] = '\0';
    
    t->filename = (unsigned char*)malloc(sizeof(unsigned char)*(strlen(filename))+1);
    strcpy(t->filename,filename);
    t->filename[strlen(filename)] = '\0';
    
    t->licensename = (unsigned char*)malloc(sizeof(unsigned char)*(strlen(licensename))+1);
    strcpy(t->licensename,licensename);
    t->licensename[strlen(licensename)] = '\0';
    
    t->start = start;
    t->end = end;
    t->position = position;

    t->vector = vector;

    return t;
}

void sentence_type_print(void *v) {
    sentence_type *t;
    t = (sentence_type*)v;
    printf("'%s'\n",t->string);
    sv_print(*t->vector);
}

void sentence_type_dump(void *v, FILE *file) {
    sentence_type *t;
    int temp;
    t = (sentence_type*)v;

    temp = strlen(t->string)+1;
    fwrite(&temp, sizeof(int),1,file);
    fwrite(t->string, sizeof(unsigned char),temp,file);
    fwrite(&t->start, sizeof(int),1,file);
    fwrite(&t->end, sizeof(int),1,file);
    fwrite(&t->position, sizeof(int),1,file);
    temp = strlen(t->filename)+1;
    fwrite(&temp, sizeof(int),1,file);
    fwrite(t->filename, sizeof(unsigned char),strlen(t->filename)+1,file);
    temp = strlen(t->licensename)+1;
    fwrite(&temp, sizeof(int),1,file);
    fwrite(t->licensename, sizeof(unsigned char),strlen(t->licensename)+1,file);
    sv_dump(*t->vector,file);
}

void* sentence_type_load(FILE *file) {
    sentence_type *t = (sentence_type*)malloc(sizeof(sentence_type));
    int temp;
    sv_vector *vect = (sv_vector*)malloc(sizeof(sv_vector));

    fread(&temp, sizeof(int), 1, file);
    t->string = malloc(sizeof(unsigned char)*temp);
    fread(t->string, sizeof(unsigned char),temp,file);
    fread(&t->start, sizeof(int),1,file);
    fread(&t->end, sizeof(int),1,file);
    fread(&t->position, sizeof(int),1,file);
    fread(&temp, sizeof(int), 1, file);
    t->filename = malloc(sizeof(unsigned char)*temp);
    fread(t->filename, sizeof(unsigned char),temp,file);
    fread(&temp, sizeof(int), 1, file);
    t->licensename = malloc(sizeof(unsigned char)*temp);
    fread(t->licensename, sizeof(unsigned char),temp,file);
    
    *vect = sv_load(file);
    t->vector = vect;

    return t;
}
