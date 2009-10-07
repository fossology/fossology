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
#include "config.h"

void print_usage(char *name) {
    fprintf(stderr, "Usage: %s [options] file\n",name);
	fprintf(stderr, "   This application uses an existing MaxEnt model file to automatically label the sentence breaks in a file. The only arguments are the model file and a single file to be labelled. The labeled file will be output to stdout.\n");
    fprintf(stderr, "   -m model ::  MaxEnt model to use for labeling.\n");
}

int main(int argc, char **argv) {
    char *buffer;
    int i,j;
    char *t = NULL;
    token_feature *tf = NULL;
    char model_file[255];
    int c;

    while ((c = getopt(argc, argv, "m:h")) != -1) {
        switch (c) {
            case 'm':
                strcpy(model_file,optarg);

                FILE *file;
                file = fopen(model_file, "rb");
                if (file==NULL) {
                    fprintf(stderr, "File provided to -m parameter does not exists.\n\t'%s'\n", model_file);
                    exit(1);
                }

                break;
            case 'h':
                print_usage(argv[0]);
                exit(0);
            case '?':
                print_usage(argv[0]);
                if (optopt == 'm') {
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

    printf("optind = %d\n", optind);

    if (optind>=argc) {
        print_usage(argv[0]);
        fprintf(stderr, "No file provided for labelling...\n");
        exit(-1);
    }

    MaxentModel m;
    m.load(model_file);
    buffer = NULL;
    openfile(argv[optind],&buffer);
    default_list sentence_list = default_list_create(default_list_type_token());
    default_list feature_type_list = default_list_create(default_list_type_token_feature());
    default_list label_list = default_list_create(default_list_type_string());
    create_features_from_buffer(buffer,feature_type_list);
    label_sentences(m,feature_type_list,label_list,left_window,right_window);

    printf("<SENTENCE>");
    int start = 0;
    for (i = 0; i<default_list_length(feature_type_list); i++) {
        t = (char *)default_list_get(label_list,i);
        tf = (token_feature *)default_list_get(feature_type_list,i);

        if (strcmp("E",t)==0) {
            char *temp = (char *)malloc(tf->end - start + 1);
            strncpy(temp, (char *)buffer+start, tf->end - start);
            temp[tf->end - start] = '\0';
            printf("%s</SENTENCE><SENTENCE>", temp);
            free(temp);
            start = tf->end;
        }
    }
    printf("</SENTENCE>\n");

    free(buffer);
    default_list_destroy(feature_type_list);
    default_list_destroy(label_list);
    return(0);
}
