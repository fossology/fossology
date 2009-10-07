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
#include <string.h>
#include <ctype.h>
#include <default_list.h>
#include "sentence.h"

/*
   Functions for an integer list
*/
int default_list_type_sentence(void) {
    default_list_type_sentence_init();
    return default_list_type_id_by_name("sentence");
}

int default_list_type_sentence_init(void) {
    if (default_list_type_id_by_name("sentence") < 0) {
        default_list_register_type(
            "sentence",
            &default_list_type_function_sentence_create,
            &default_list_type_function_sentence_copy,
            &default_list_type_function_sentence_destroy,
            &default_list_type_function_sentence_print,
            &default_list_type_function_sentence_dump,
            &default_list_type_function_sentence_load);
    }
}

void* default_list_type_function_sentence_create(void *v) {
    sentence *temp = malloc(sizeof(sentence));
    sentence *s = v;
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

        temp->vector = sv_copy(s->vector);

        temp->start = s->start;
        temp->end = s->end;
        temp->position = s->position;
    } else {
        temp->string = NULL;
        temp->filename = NULL;
        temp->licensename = NULL;
        temp->id = 0;
        temp->vector = NULL;
        temp->start = 0;
        temp->end = 0;
        temp->position = 0;
    }
    return (void *)temp;
}

void* default_list_type_function_sentence_copy(void *v) {
    sentence *temp = malloc(sizeof(sentence));
    sentence *s = v;
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

        temp->vector = sv_copy(s->vector);

        temp->start = s->start;
        temp->end = s->end;
        temp->position = s->position;
    } else {
        temp = NULL;
    }
    return (void *)temp;
}

void default_list_type_function_sentence_destroy(void *v) {
    sentence *s = v;
    free(s->licensename);
    free(s->filename);
    free(s->string);
    sv_delete(s->vector);
    free(s);
}

void default_list_type_function_sentence_print(void *v, FILE *f) {
    sentence *s = v;
    fprintf(f, "'%s'", s->string);
}

int default_list_type_function_sentence_dump(void *v, FILE *f) {
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
    return 0;
}

void* default_list_type_function_sentence_load(FILE *f) {
    sentence *temp = malloc(sizeof(sentence));
    int len = 0;
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    fread(&len,sizeof(int),1,f);
    temp->string = malloc(len);
    if (temp->string == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        free(temp);
        return NULL;
    }
    fread(temp->string,1,len,f);

    fread(&temp->start,sizeof(int),1,f);
    fread(&temp->end,sizeof(int),1,f);
    fread(&temp->position,sizeof(int),1,f);

    fread(&len,sizeof(int),1,f);
    temp->filename = malloc(len);
    if (temp->filename == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        free(temp->string);
        free(temp);
        return NULL;
    }
    fread(temp->filename,1,len,f);
    fread(&len,sizeof(int),1,f);
    temp->licensename = malloc(len);
    if (temp->licensename == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        free(temp->filename);
        free(temp->string);
        free(temp);
        return NULL;
    }
    fread(temp->licensename,1,len,f);
    fread(&temp->id,sizeof(int),1,f);

    temp->vector = sv_load(f);

    return (void *)temp;
}

sentence* sentence_create(char *string, int start, int end, int position, char *filename, char *licensename, int id, sv_vector vector) {
    sentence *temp = (sentence*)malloc(sizeof(sentence));
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
