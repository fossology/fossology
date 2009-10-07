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
#include <math.h>
#include "sentence.h"
#include "hash.h"
#include "config.h"

void print_usage(char *name) {
    fprintf(stderr, "Usage: %s [options]\n",name);
    fprintf(stderr, "   Creates a sentence model for classifying licenses.\n");
    fprintf(stderr, "   -f path ::  Read the paths of the training files from a file.\n");
    fprintf(stderr, "   -m path ::  Path to the MaxEnt sentence ending model.\n");
    fprintf(stderr, "   -o path ::  Save sentence model at the specified path.\n");
}

int main(int argc, char **argv) {
    char *buffer;
    int i,j;
    default_list feature_type_list = NULL;
    default_list label_list = NULL;
    default_list sentence_list = NULL;
    default_list database_list = default_list_create(default_list_type_default_list());
    char *t = NULL;
    token_feature *ft = NULL;
    sentence *st = NULL;
    char *training_files = NULL;
    char *model_file = NULL;
    char *maxent_model_file = NULL;
    int c;

    opterr = 0;
    while ((c = getopt(argc, argv, "o:f:m:")) != -1) {
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
            case '?':
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

    char *filename = NULL;
    i = 0;
    while (readline(pFile,&filename)!=EOF) {
        printf("Starting on %s", filename);
        buffer = NULL;
        feature_type_list = default_list_create(default_list_type_token_feature());
        label_list = default_list_create(default_list_type_string());
        sentence_list = default_list_create(default_list_type_sentence());
        openfile(filename,&buffer);
        create_features_from_buffer(buffer,feature_type_list);
        label_sentences(m,feature_type_list,label_list,left_window,right_window);
    
        create_sentences(m, sentence_list, buffer, feature_type_list, label_list, filename, "", i);
        
        default_list_append(database_list,sentence_list);
        
        free(buffer);
        default_list_destroy(feature_type_list);
        default_list_destroy(label_list);
        default_list_destroy(sentence_list);

        printf("done.\n");
        i++;
    }

    int num = default_list_length(database_list);

    FILE *file;
    file = fopen(model_file, "w");
    if (file==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    default_list_dump(database_list,file);

    fclose(file);

    default_list_destroy(database_list);
    return(0);
}
