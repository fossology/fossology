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
#include "hash.h"

void classify_file(char *filename, default_list database_list, MaxentModel m) {
    char *buffer;
    default_list feature_type_list = NULL;
    default_list label_list = NULL;
    default_list sentence_list = NULL;
    default_list file_list = NULL;
    char *t = NULL;
    token_feature *ft = NULL;
    sentence *st = NULL;
    sentence *a = NULL;
    sentence *b = NULL;
    int i, j, k;

    buffer = NULL;
    feature_type_list = default_list_create(default_list_type_token_feature());
    label_list = default_list_create(default_list_type_string());
    file_list = default_list_create(default_list_type_sentence());
    
    readtomax(filename,&buffer,32768);
    create_features_from_buffer(buffer,feature_type_list);
    label_sentences(m,feature_type_list,label_list,left_window,right_window);

    create_sentences(m, file_list, buffer, feature_type_list, label_list, filename, "", 0);

    free(buffer);
    default_list_destroy(feature_type_list);
    default_list_destroy(label_list);

    double score[default_list_length(file_list)];
    int score_index[default_list_length(file_list)];
    int vector[default_list_length(database_list)][default_list_length(file_list)];
    double match_percent[default_list_length(database_list)][default_list_length(file_list)];
    int match_index[default_list_length(database_list)][default_list_length(file_list)];
    for (i = 0; i < default_list_length(database_list); i++) {
        sentence_list = (default_list)default_list_get(database_list,i);

        double matrix[default_list_length(file_list)+1][default_list_length(sentence_list)+1];
        double cosine[default_list_length(file_list)][default_list_length(sentence_list)];
        for (k = 0; k < default_list_length(file_list)+1; k++) {
            for (j = 0; j < default_list_length(sentence_list)+1; j++) {
                matrix[k][j] = 0;
            }
        }
        for (j = 0; j < default_list_length(sentence_list); j++) {
            a = (sentence *)default_list_get(sentence_list,j);
            double thresh = 1.0 - 2.0 / ((double)sv_nonzeros(a->vector));
            if (thresh < 0.75) {
                thresh = 0.75;
            }
            thresh = 0.5;
            for (k = 0; k < default_list_length(file_list); k++) {
                b = (sentence *)default_list_get(file_list,k);
                cosine[k][j] = sv_inner(a->vector,b->vector);
                if (cosine[k][j] > thresh) {
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
        for (k = 0; k < default_list_length(file_list); k++) {
            vector[i][k] = 0;
            for (j = 0; j < default_list_length(sentence_list); j++) {
                if (matrix[k+1][j+1] > vector[i][k]) {
                    vector[i][k] = matrix[k+1][j+1];
                    match_percent[i][k] = cosine[k][j];
                    match_index[i][k] = j;
                }
            }
        }
        double m = 0;
        for (k = 0; k < default_list_length(file_list); k++) {
            if (vector[i][k] == m) {
                vector[i][k] = 0;
                match_percent[i][k] = 0.0;
            } else if (k > 0 && vector[i][k-1] == 0) {
                m = vector[i][k];
                vector[i][k] = 1;
            } else {
                m = vector[i][k];
                if (k > 0) {
                    vector[i][k] = vector[i][k-1] + 1;
                    match_percent[i][k] += match_percent[i][k-1];
                }
            }
        }
        for (k = default_list_length(file_list)-2; k > -1; k--) {
            if (vector[i][k] != 0 && vector[i][k+1] > vector[i][k]) {
                vector[i][k] = vector[i][k+1];
                match_percent[i][k] = match_percent[i][k+1];
            }
        }
        for (k = 0; k < default_list_length(file_list); k++) {
            if (vector[i][k] == 1) {
                vector[i][k] = 0;
                match_percent[i][k] = 0.0;
            }
            //printf("%02.2f ", match_percent[i][k]/10.0);
        }
        //printf("\n");
    }

    for (i = 0; i < default_list_length(file_list); i++) {
        score[i] = 0;
        for (j = 0; j < default_list_length(database_list); j++) {
            // if (vector[i][j] > score[i]) {
            //     score[i] = vector[i][j];
            // }
            if (match_percent[j][i] > score[i]) {
                score[i] = match_percent[j][i];
                score_index[i] = j;
            }
        }
    }

    //printf("\n");
    for (i = 0; i < default_list_length(file_list); i++) {
        //printf("%02.2f ", score[i]/10.0);
    }
    //printf("\n");
    
    for (i = 1; i < default_list_length(file_list)-1; i++) {
        if (i == 1) {
            if (score[0] < score[1]) {
                score[0] = 0;
            }
        }
        if (i == default_list_length(file_list)-2) {
            if (score[i+1] < score[i]) {
                score[i+1] = 0;
            }
        }
        if (score[i-1] == 0 && score[i] < score[i+1]) {
            score[i] = 0;
        } else if (score[i+1] == 0 && score[i] < score[i-1]) {
            score[i] = 0;
        }
    }

    //printf("\n");
    for (i = 0; i < default_list_length(file_list); i++) {
        //printf("%02.2f ", score[i]/10.0);
    }
    //printf("\n");
    for (i = 0; i < default_list_length(file_list); i++) {
        if (score[i] == 0) {
            score_index[i] = 0;
        }
        //printf("%04d ", score_index[i]);
    }
    //printf("\n");

    int prev_index = -1;
    int start_byte = 0;
    int end_byte = 0;
    for (i = 0; i < default_list_length(file_list); i++) {
        if (score_index[i] != 0) {
            st = (sentence *)default_list_get(file_list,i);
            if (prev_index == -1) {
                start_byte = st->start;
                end_byte = st->end;
            } else if (prev_index != score_index[i]) {
                printf("%d [%05d, %05d]\n", prev_index, start_byte, end_byte);
                start_byte = st->start;
                end_byte = st->end;
            } else if (prev_index == score_index[i]) {
                end_byte = st->end;
            }
            prev_index = score_index[i];
        } else {
            if (prev_index > -1) {
                printf("%d [%05d, %05d]\n", prev_index, start_byte, end_byte);
            }
            prev_index = -1;
            start_byte = 0;
            end_byte = 0;
        }
    }
    if (prev_index != -1) {
        printf("%d [%05d, %05d]\n", prev_index, start_byte, end_byte);
    }
}

int main(int argc, char **argv) {
    FILE *file;
    default_list database_list = NULL;

    MaxentModel m;
    m.load("maxent.dat");

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

    classify_file(argv[1],database_list,m);

    return(0);
}
