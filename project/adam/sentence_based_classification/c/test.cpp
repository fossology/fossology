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
#include "default_list.h"
#include "re.h"
#include "token.h"
#include "token_feature.h"
#include <maxent/maxentmodel.hpp>
#include "maxent_utils.h"
#include "file_utils.h"
#include <limits.h>
#include <sparsevect.h>
#include "sentence.h"
#include <math.h>
#include "config.h"

static unsigned long sdbm(char *str) {
    unsigned long hash = 0;
    int c;

    while (c = *str++)
        hash = c + (hash << 6) + (hash << 16) - hash;

    return hash;
}

int main(int argc, char **argv) {
    char *buffer;
    FILE *file;
    default_list database_list = NULL;
    default_list feature_type_list = NULL;
    default_list label_list = NULL;
    default_list sentence_list = NULL;
    default_list file_list = NULL;
    char *t = NULL;
    token_feature *ft = NULL;
    sentence *st = NULL;
    sentence *a = NULL;
    sentence *b = NULL;
    sv_vector vect;
    int i, j, k;
    char *filename = argv[1];

    MaxentModel m;
    m.load("maxent.dat");

    printf("Load database...\n");

    file = fopen("database.dat", "r");
    if (file==NULL) {
        fputs("File error. Could not read Database.dat\n", stderr);
        exit(1);
    }
    
    // register the types before we need them in the load function.
    default_list_type_default_list();
    default_list_type_token_feature();
    default_list_type_string();
    default_list_type_sentence();

    database_list = default_list_load(file);

    fclose(file);

    vect = sv_new(ULONG_MAX);
    buffer = NULL;
    feature_type_list = default_list_create(default_list_type_token_feature());
    label_list = default_list_create(default_list_type_string());
    file_list = default_list_create(default_list_type_sentence());
    openfile(filename,&buffer);
    create_features_from_buffer(buffer,feature_type_list);
    label_sentences(m,feature_type_list,label_list,left_window,right_window);

    ft = (token_feature *)default_list_get(feature_type_list,0);
    int start = ft->start;
    for (i = 0; i<default_list_length(feature_type_list); i++) {
        double v = 0;
        unsigned long int index = 0;
        ft = (token_feature *)default_list_get(feature_type_list,i);
        t = (char *)default_list_get(label_list,i);
        index = sdbm(ft->stemmed);
        v = sv_get_element_value(vect,index);
        sv_set_element(vect,index,v+1.0);
        if (strcmp(t, "E")==0 || i == default_list_length(feature_type_list)-1) {
            printf(".");
            double norm = 1.0/sqrt(sv_inner(vect,vect));
            vect = sv_scalar_mult(vect,norm);
            st = sentence_create(buffer,start,ft->end,i,filename,filename,vect);

            default_list_append(file_list,st);

            vect = sv_new(ULONG_MAX);
            start = ft->end;
        }
    }
    free(buffer);
    default_list_destroy(feature_type_list);
    default_list_destroy(label_list);
    printf("done.\n");

    double database_score[default_list_length(database_list)];
    for (k = 0; k < default_list_length(database_list); k++) {
        database_score[k] = 0.0;
    }
    double vector_score[default_list_length(file_list)];
    default_list vector_index[default_list_length(file_list)];
    default_list vector_sents[default_list_length(file_list)];
    default_list vector_cosin[default_list_length(file_list)];
    for (k = 0; k < default_list_length(file_list); k++) {
        vector_score[k] = 0;
        vector_index[k] = default_list_create(default_list_type_int());
        vector_sents[k] = default_list_create(default_list_type_int());
        vector_cosin[k] = default_list_create(default_list_type_double());
    }
    double score[default_list_length(database_list)];
    for (i = 0; i < default_list_length(database_list); i++) {
        sentence_list = (default_list)default_list_get(database_list,i);
        double matrix[default_list_length(file_list)+1][default_list_length(sentence_list)+1];
        double cosine[default_list_length(file_list)][default_list_length(sentence_list)];
        for (k = 0; k < default_list_length(file_list)+1; k++) {
            matrix[k][0] = 0;
        }
        for (k = 0; k < default_list_length(sentence_list)+1; k++) {
            matrix[0][k] = 0;
        }
        for (j = 0; j < default_list_length(sentence_list); j++) {
            a = (sentence *)default_list_get(sentence_list,j);
            for (k = 0; k < default_list_length(file_list); k++) {
                b = (sentence *)default_list_get(file_list,k);
                cosine[k][j] = sv_inner(a->vector,b->vector);
                if (cosine[k][j]>0.5) {
                    matrix[k+1][j+1] = matrix[k][j] + 1;
                } else {
                    if (matrix[k][j+1] > matrix[k+1][j]) {
                        matrix[k+1][j+1] = matrix[k][j+1];
                    } else {
                        matrix[k+1][j+1] = matrix[k+1][j];
                    }
                }
            }
        }
        double vector[default_list_length(file_list)];
        int index[default_list_length(file_list)];
        for (k = 0; k < default_list_length(file_list); k++) {
            double m = 0.0;
            for (j = 0; j < default_list_length(sentence_list); j++) {
                if (matrix[k+1][j+1] > m) {
                    m = matrix[k+1][j+1];
                    index[k] = j;
                }
            }
            vector[k] = m;
        }
        double m = 0.0;
        for (k = 0; k < default_list_length(file_list); k++) {
            if (vector[k] == m) {
                vector[k] = 0.0;
            } else {
                m = vector[k];
            }
        }
        for (k = 0; k < default_list_length(file_list); k++) {
            if (vector[k] != 0.0) {
                vector[k] = m;
            }
        }
        for (k = 0; k < default_list_length(file_list); k++) {
            if (vector[k] > vector_score[k]) {
                vector_score[k] = vector[k];
                default_list_destroy(vector_index[k]);
                default_list_destroy(vector_sents[k]);
                default_list_destroy(vector_cosin[k]);
                vector_index[k] = default_list_create(default_list_type_int());
                vector_sents[k] = default_list_create(default_list_type_int());
                vector_cosin[k] = default_list_create(default_list_type_double());
                default_list_append(vector_index[k],(void *)&i);
                default_list_append(vector_sents[k],(void *)&index[k]);
                default_list_append(vector_cosin[k],(void *)&cosine[k][index[k]]);
            } else if (vector[k] != 0 && vector[k] == vector_score[k]) {
                default_list_append(vector_index[k],(void *)&i);
                default_list_append(vector_sents[k],(void *)&index[k]);
                default_list_append(vector_cosin[k],(void *)&cosine[k][index[k]]);
            }
        }
        score[i] = matrix[default_list_length(file_list)][default_list_length(sentence_list)];
    }

    for (k = 0; k < default_list_length(file_list); k++) {
        int *index = NULL;
        double *cosine = NULL;
        for (j = 0; j<default_list_length(vector_index[k]); j++) {
            index = (int *)default_list_get(vector_index[k],j);
            cosine = (double *)default_list_get(vector_cosin[k],j);
            database_score[*index] += *cosine;
        }
    }

    for (k = 0; k < default_list_length(file_list); k++) {
        int *index = NULL;
        int *index2 = NULL;
        double *cosine = NULL;
        printf("%02d : %1.0f, ", k, vector_score[k]);
        st = (sentence *)default_list_get(file_list,k);
        printf("[%05d, %05d] ", st->start, st->end);
        int best_index = -1;
        double best_match = 0.0;
        for (j = 0; j<default_list_length(vector_index[k]); j++) {
            index = (int *)default_list_get(vector_index[k],j);
            index2 = (int *)default_list_get(vector_sents[k],j);
            if (database_score[*index]>best_match) {
                best_match = database_score[*index];
                best_index = *index;
            }
        }
        printf("%02d (%1.2f) ", best_index, best_match);
        printf("\n");
    }

    default_list_destroy(database_list);
    return(0);
}
