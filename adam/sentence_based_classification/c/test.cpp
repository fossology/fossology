#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string>
#include <vector>
#include <ctype.h>
#include "list.h"
#include "re.h"
#include "stem.h"
#include "token.h"
#include "feature_type.h"
#include <maxent/maxentmodel.hpp>

using namespace maxent;
using namespace std;

int main(int argc, char **argv) {
    char sent_re[] = "<SENTENCE>(?P<text>.*?)</SENTENCE>";
    char start_nonword_re[] = "^[^A-Za-z0-9]+";
    char general_token_re[] = "[A-Za-z0-9]+|[^A-Za-z0-9 ]+";
    char word_token_re[] = "[A-Za-z0-9][A-Za-z0-9]+";
    char * buffer;
    FILE *pFile;
    long lSize;
    size_t result;
    int i,j;
    default_list *list = NULL;
    default_list *feature_type_list = NULL;
    default_list *label_list = NULL;
    cre *re;

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

    i = re_compile(sent_re,RE_DOTALL,&re);

    if (i!=0) {
        re_print_error(i);
    }

    i = re_find_all(re,buffer,&list,&token_create_from_string);

    if (i!=0) {
        re_print_error(i);
    }

    i = re_compile(start_nonword_re,RE_DOTALL,&re);
    
    if (i!=0) {
        re_print_error(i);
    }

    for (i = 1; i < default_list_length(&list); i++) {
        token *t = NULL;
        default_list *l = NULL;
        if (default_list_get(&list,i,(void**)&t)==0) {
            j = re_find_all(re,t->string,&l,&token_create_from_string);
            if (j!=0) {
                re_print_error(j);
            } else if (default_list_length(&l)>0) {
                token *t_1 = NULL;
                token *t_2 = NULL;

                default_list_get(&list,i-1,(void**)&t_1);
                default_list_get(&l,0,(void**)&t_2);

                // get the previous token and append the begging of this token
                // to the end of it.
                char *new_string = (char*)malloc(sizeof(char)*(strlen(t_1->string)+strlen(t_2->string)+1));
                new_string[0] = '\0';
                strcat(new_string,t_1->string);
                strcat(new_string,t_2->string);
                new_string[strlen(t_1->string)+strlen(t_2->string)] = '\0';
                free(t_1->string);
                t_1->string = new_string;

                new_string = (char*)malloc(sizeof(char)*(strlen(t->string)-strlen(t_2->string)+1));
                strcpy(new_string,t->string+strlen(t_2->string));
                new_string[strlen(t->string)-strlen(t_2->string)] = '\0';
                free(t->string);
                t->string = new_string;

                default_list_free(&l,&token_free);
            }
        }
    }
    
    i = re_compile(general_token_re,RE_DOTALL,&re);
    if (i!=0) { re_print_error(i); }
    for (i = 0; i < default_list_length(&list); i++) {
        token *t = NULL;
        if (default_list_get(&list,i,(void**)&t)==0) {
            token *t2 = NULL;
            j = re_find_all(re,t->string,&feature_type_list,&feature_type_create_from_string);
            if (j!=0) { re_print_error(j); break; }
            while (default_list_length(&label_list)<default_list_length(&feature_type_list)) {
                t2 = (token*)malloc(sizeof(token));
                t2->string = (char*)malloc(sizeof(char)*2);
                strcpy(t2->string,"I");
                default_list_append(&label_list,(void**)&t2);

            }
            t2->string[0] = 'E';
        }
    }

    default_list_print(&feature_type_list,&feature_type_print);
    // for (i = 0; i < default_list_length(&stem_list); i++) {
    //     token *t1 = NULL;
    //     token *t2 = NULL;
    //     default_list_get(&stem_list,i,(void**)&t1);
    //     default_list_get(&label_list,i,(void**)&t2);
    //     printf("'%s' -> %s\n", t1->string, t2->string);
    // }

    re_free(re);
    free(buffer);
    default_list_free(&list, &token_free);
    return(0);
}
