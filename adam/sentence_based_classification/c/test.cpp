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

int main(int argc, char **argv) {
    char *buffer;
    int i,j;
    default_list *sentence_list = NULL;
    default_list *feature_type_list = NULL;
    default_list *label_list = NULL;
    token *t = NULL;
    feature_type *ft = NULL;
    int left_window = 3;
    int right_window = 3;

    MaxentModel m;
    m.load("SentenceModel.dat");
    buffer = NULL;
    sentence_list = NULL;
    feature_type_list = NULL;
    label_list = NULL;
    openfile(argv[1],&buffer);
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
