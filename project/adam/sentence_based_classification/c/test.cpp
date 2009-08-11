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
#include "tokenizer.h"
#include "list.h"
#include "re.h"
#include "token.h"
#include "feature_type.h"
#include <maxent/maxentmodel.hpp>
#include "maxent_utils.h"
#include "file_utils.h"
extern "C" {
#include <limits.h>
#include <sparsevect.h>
#include "sentence_type.h"
}

static unsigned long sdbm(unsigned char *str) {
    unsigned long hash = 0;
    int c;

    while (c = *str++)
        hash = c + (hash << 6) + (hash << 16) - hash;

    return hash;
}

int main(int argc, char **argv) {
    unsigned char *buffer;
    int i,j;
    default_list *feature_type_list = NULL;
    default_list *label_list = NULL;
    default_list *database_list = NULL;
    default_list *file_list = NULL;
    token *t = NULL;
    feature_type *ft = NULL;
    int left_window = 3;
    int right_window = 3;
    FILE *file;
    sv_vector *vect;
    MaxentModel m;
    int start;
    unsigned char *filename = (unsigned char*)argv[0];
    sentence_type *st_a = NULL;
    sentence_type *st_b = NULL;

    m.load("SentenceModel.dat");

    printf("Load database...\n");

    
    file = fopen("database.dat", "r");
    if (file==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }
    
    deafult_list_load(&database_list,file,&sentence_type_load);

    fclose(file);

    printf("Database file loaded.\n");

    
    vect = (sv_vector*)malloc(sizeof(sv_vector));
    *vect = sv_new(ULONG_MAX);
    buffer = NULL;
    feature_type_list = NULL;
    label_list = NULL;
    openfile(filename,&buffer);
    create_features_from_buffer(buffer,&feature_type_list);
    label_sentences(m,&feature_type_list,&label_list,left_window,right_window);
    
    default_list_get(&feature_type_list,0,(void**)&ft);
    start = ft->start;
    for (i = 0; i<default_list_length(&feature_type_list); i++) {
        double v = 0;
        unsigned long int index = 0;
        default_list_get(&feature_type_list,i,(void**)&ft);
        default_list_get(&label_list,i,(void**)&t);
        index = sdbm((unsigned char*)ft->stemmed);
        v = sv_get_element_value(*vect,index);
        sv_set_element(*vect,index,v+1.0);
        if (strcmp(t->string, "E")==0 || i == default_list_length(&feature_type_list)-1) {
            printf(".");
            sentence_type *st = (sentence_type*)sentence_type_create(buffer,start,ft->end,i,filename,filename,vect);

            default_list_append(&file_list,(void**)&st);

            vect = (sv_vector*)malloc(sizeof(sv_vector));
            *vect = sv_new(ULONG_MAX);
            start = ft->end;
        }
    }
    free(buffer);
    default_list_free(&feature_type_list,&feature_type_free);
    default_list_free(&label_list,&token_free);
    printf("done.\n");

    for (i = 0; i<default_list_length(&database_list); i++) {
        for (j=0; j<default_list_length(&file_list); j++) {
            default_list_get(&database_list,i,(void**)&st_a);
            default_list_get(&file_list,j,(void**)&st_b);
            
            //printf("%f\n", sv_inner(*st_a->vector,*st_b->vector));
            sv_inner(*st_a->vector,*st_b->vector);
        }
    }

    default_list_free(&database_list,&sentence_type_free);
    default_list_free(&file_list,&sentence_type_free);
    return(0);
}
