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
#include <math.h>
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
#include <time.h>
#include <math.h>
}

void print_usage(char *name) {
    fprintf(stderr, "Usage: %s [options]\n",name);
    fprintf(stderr, "   Creates a sentence model for classifying licenses.\n");
    fprintf(stderr, "   -f path ::  Read the paths of the training files from a file.\n");
    fprintf(stderr, "   -m path ::  Path to the MaxEnt sentence ending model.\n");
    fprintf(stderr, "   -o path ::  Save sentence model at the specified path.\n");
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
    token *t = NULL;
    feature_type *ft = NULL;
    sentence_type *st = NULL;
    int left_window = 3;
    int right_window = 3;
    sv_vector *vect;
    vect = (sv_vector*)malloc(sizeof(sv_vector));
    *vect = sv_new(ULONG_MAX);
    char *training_files = NULL;
    char *model_file = NULL;
    char *maxent_model_file = NULL;
    int c;

    opterr = 0;
    while ((c = getopt(argc, argv, "o:f:m:h")) != -1) {
        switch (c) {
            case 'f':
                training_files = optarg;

                FILE *file;
                file = fopen(training_files, "rb");
                if (file==NULL) {
                    fprintf(stderr, "File provided to -f parameter does not exists.\n");
                    exit(1);
                }

                break;
            case 'o':
                model_file = optarg;
                break;
            case 'm':
                maxent_model_file = optarg;
                break;
            case 'h':
                print_usage(argv[0]);
                exit(0);
            case '?':
                print_usage(argv[0]);
                if (optopt == 'f' || optopt == 'o' || optopt == 'm') {
                    fprintf(stderr, "Option -%c requires an argument.\n", optopt);
                } else if (isprint(optopt)) {
                    fprintf(stderr, "Unknown option `-%c'.\n", optopt);
                } else {
                    fprintf(stderr, "Unknown option character `\\x%x'.\n",optopt);
                }
                exit(-1);
            default:
                print_usage(argv[0]);
                exit(-1);
        }
    }

    if (maxent_model_file == NULL || model_file == NULL || training_files == NULL) {
        print_usage(argv[0]);
        exit(-1);
    }

    MaxentModel m;
    m.load(maxent_model_file);

    FILE *pFile;
    pFile = fopen(training_files, "rb");
    if (pFile==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    unsigned char *filename = NULL;
    while (readline(pFile,&filename)!=EOF) {
        printf("Starting on %s", filename);
        buffer = NULL;
        feature_type_list = NULL;
        label_list = NULL;
        openfile(filename,&buffer);
        create_features_from_buffer(buffer,&feature_type_list);
        label_sentences(m,&feature_type_list,&label_list,left_window,right_window);
    
        default_list_get(&feature_type_list,0,(void**)&ft);
        int start = ft->start;
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
                double norm = sqrt(sv_inner(*vect,*vect));
                *vect = sv_scalar_mult(*vect,norm);

                st = (sentence_type*)sentence_type_create(buffer,start,ft->end,i,filename,filename,vect);

                default_list_append(&database_list,(void**)&st);

                vect = (sv_vector*)malloc(sizeof(sv_vector));
                *vect = sv_new(ULONG_MAX);
                start = ft->end;
            }
        }
        free(buffer);
        default_list_free(&feature_type_list,&feature_type_free);
        default_list_free(&label_list,&token_free);
        printf("done.\n");
    }


    /* make our leaders */
    default_list *leader_list = NULL;
    int n = default_list_length(&database_list);
    int num_leaders = (int)log((float)n);
    srand(time(NULL));
    
    for (i=0; i<num_leaders; i++) {
        int *temp = (int*)malloc(sizeof(int));
        *temp = rand()%n;
        default_list_append(&leader_list,(void**)&temp);
    }




/* save the model */
    
    FILE *file;
    file = fopen(model_file, "w");
    if (file==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    default_list_dump(&database_list,file,&sentence_type_dump);

    fclose(file);

    default_list_free(&database_list,&sentence_type_free);
    return(0);
}
