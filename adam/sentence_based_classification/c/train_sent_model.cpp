/*********************************************************************
  train_sent_model.cpp: Takes a collection of files with labeled sentence
    boundaries and trains a MaxEnt model.
  
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
#include <unistd.h>
#include <default_list.h>
#include "token.h"
#include "token_feature.h"
#include "tokenizer.h"
#include "re.h"
#include "file_utils.h"
#include <maxent/maxentmodel.hpp>
#include "maxent_utils.h"
#include "config.h"

void print_usage(char *name) {
    fprintf(stderr, "Usage: %s [options]\n",name);
    fprintf(stderr, "   Creates a MaxEnt model for locating sentence breaks.\n");
    fprintf(stderr, "   -f path ::  Read the paths of the training files from a file.\n");
    fprintf(stderr, "   -o path ::  Save MaxEnt model at the specified path.\n");
    fprintf(stderr, "               Defaults to './maxent.model'.\n");
}

int main(int argc, char *argv[]) {
    char *buffer;
    int i,j,c;
    char *training_files = NULL;
    char *model_file = NULL;

    opterr = 0;
    while ((c = getopt(argc, argv, "o:f:h")) != -1) {
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
            case 'h':
                print_usage(argv[0]);
                exit(0);
            case '?':
                print_usage(argv[0]);
                if (optopt == 'f' || optopt == 'o') {
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

    FILE *pFile;
    pFile = fopen(training_files, "rb");
    if (pFile==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    // Create a maxent model object and tell it that we will start to add
    // training data.
    MaxentModel m;
    m.begin_add_event();
    char *filename = NULL;
    while (readline(pFile,&filename)!=EOF) {
        default_list sentence_list = default_list_create(default_list_type_token());
        default_list feature_type_list = default_list_create(default_list_type_token_feature());
        default_list label_list = default_list_create(default_list_type_string());
        buffer = NULL;
        printf("Starting on %s...\n", filename);
        
        openfile(filename,&buffer);
        create_sentence_list(buffer,sentence_list);
        create_features_from_sentences(sentence_list,feature_type_list, label_list);
        // This function adds the training data to the model.
        create_model(m, feature_type_list, label_list, left_window, right_window);
        free(buffer);
        free(filename);
        default_list_destroy(sentence_list);
        default_list_destroy(feature_type_list);
        default_list_destroy(label_list);
    }
    m.end_add_event();

    printf("Training MaxEnt model...\n");
    m.train(1000, "lbfgs");
    m.save(model_file);

    return(0);
}
