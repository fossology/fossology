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

void print_usage(char *name) {
    fprintf(stderr, "Usage: %s [options] file\n",name);
	fprintf(stderr, "   This application uses an existing MaxEnt model file to automatically label the sentence breaks in a file. The only arguments are the model file and a single file to be labelled. The labeled file will be output to stdout.\n");
    fprintf(stderr, "   -m model ::  MaxEnt model to use for labeling.\n");
}

int main(int argc, char **argv) {
    unsigned char *buffer;
    int i,j;
    default_list *sentence_list = NULL;
    default_list *feature_type_list = NULL;
    default_list *label_list = NULL;
    token *t = NULL;
    feature_type *ft = NULL;
    int left_window = 3;
    int right_window = 3;
    char *model_file;
    int c;

    opterr = 0;
    while ((c = getopt(argc, argv, "m:h")) != -1) {
        switch (c) {
            case 'm':
                model_file = optarg;

                FILE *file;
                file = fopen(model_file, "rb");
                if (file==NULL) {
                    fprintf(stderr, "File provided to -m parameter does not exists.\n");
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

    if (optind>=argc) {
        print_usage(argv[0]);
        fprintf(stderr, "\nNot file provided for labelling...\n");
        exit(-1);
    }

    MaxentModel m;
    m.load(model_file);
    buffer = NULL;
    sentence_list = NULL;
    feature_type_list = NULL;
    label_list = NULL;
    openfile((unsigned char*)argv[optind],&buffer);
    create_features_from_buffer(buffer,&feature_type_list);
    label_sentences(m,&feature_type_list,&label_list,left_window,right_window);

    printf("<SENTENCE>");
    for (i = 0; i<default_list_length(&feature_type_list); i++) {
        default_list_get(&label_list,i,(void**)&t);
        default_list_get(&feature_type_list,i,(void**)&ft);

        printf("%s ",ft->string);

        if (strcmp("E",t->string)==0) {
            printf("</SENTENCE><SENTENCE>");
        }
    }
    printf("</SENTENCE>");

    free(buffer);
    default_list_free(&sentence_list,&token_free);
    default_list_free(&feature_type_list,&feature_type_free);
    default_list_free(&label_list,&token_free);
    return(0);
}
