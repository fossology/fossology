#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string>
#include <vector>
#include <ctype.h>
#include "tokenizer.h"
#include "list.h"
#include "re.h"
#include "stem.h"
#include "token.h"
#include "feature_type.h"
#include <maxent/maxentmodel.hpp>

using namespace maxent;
using namespace std;
    
int main(int argc, char **argv) {
    char * buffer;
    FILE *pFile;
    long lSize;
    size_t result;
    int i,j;
    default_list *sentence_list = NULL;
    default_list *feature_type_list = NULL;
    default_list *label_list = NULL;

    std::vector<pair<std::string, float> > context;

    pFile = fopen(argv[1], "rb");
    if (pFile==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    fseek(pFile, 0, SEEK_END);
    lSize = ftell(pFile);
    rewind(pFile);

    buffer = (char*)malloc(sizeof(char)*lSize);
    if (buffer == NULL) {
        fputs("Memory error.\n",stderr);
        exit(2);
    }

    result = fread(buffer, 1, lSize, pFile);
    if (result != lSize) {
        fputs("Reading error",stderr); exit(3);
    }

    fclose(pFile);

    create_sentence_list(buffer,&sentence_list);
    create_features_from_sentences(&sentence_list,&feature_type_list, &label_list);

    //MaxentModel m;
    //m.begin_add_event();
    //create_model(feature_type_list, left_window, right_window);
    //m.end_add_event();
    //m.trian(1000, "lbfgs",0);

    free(buffer);
    default_list_free(&sentence_list, &token_free);
    default_list_free(&label_list, &token_free);
    default_list_free(&feature_type_list, &feature_type_free);
    return(0);
}
