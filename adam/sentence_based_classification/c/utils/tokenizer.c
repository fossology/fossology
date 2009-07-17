#include "tokenizer.h"
#include "list.h"
#include "re.h"
#include "stem.h"
#include "token.h"
#include "feature_type.h"

char sent_re[] = "<SENTENCE>(?P<text>.*?)</SENTENCE>";
char start_nonword_re[] = "^[^A-Za-z0-9]+";
char general_token_re[] = "[A-Za-z0-9]+|[^A-Za-z0-9 ]+";
char word_token_re[] = "[A-Za-z0-9][A-Za-z0-9]+";

void create_sentence_list(char* buffer, default_list **list) {
    int i,j;
    cre *re;

    i = re_compile(sent_re,RE_DOTALL,&re);

    if (i!=0) { re_print_error(i); }

    i = re_find_all(re,buffer,list,&token_create_from_string);

    re_free(re);

    if (i!=0) { re_print_error(i); }

    i = re_compile(start_nonword_re,RE_DOTALL,&re);
    
    if (i!=0) { re_print_error(i); }

    for (i = 1; i < default_list_length(list); i++) {
        token *t = NULL;
        default_list *l = NULL;
        if (default_list_get(list,i,(void**)&t)==0) {
            j = re_find_all(re,t->string,&l,&token_create_from_string);
            if (j!=0) {
                re_print_error(j);
            } else if (default_list_length(&l)>0) {
                token *t_1 = NULL;
                token *t_2 = NULL;

                default_list_get(list,i-1,(void**)&t_1);
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
    re_free(re);
}

void create_features_from_sentences(default_list **list, default_list **feature_type_list,default_list **label_list) {
    int i,j;
    cre *re;

    i = re_compile(general_token_re,RE_DOTALL,&re);
    if (i!=0) { re_print_error(i); }
    for (i = 0; i < default_list_length(list); i++) {
        token *t = NULL;
        if (default_list_get(list,i,(void**)&t)==0) {
            token *t2 = NULL;
            j = re_find_all(re,t->string,feature_type_list,&feature_type_create_from_string);
            if (j!=0) { re_print_error(j); break; }
            while (default_list_length(label_list)<default_list_length(feature_type_list)) {
                t2 = (token*)malloc(sizeof(token));
                t2->string = (char*)malloc(sizeof(char)*2);
                strcpy(t2->string,"I");
                t2->string[1] = '\0';
                default_list_append(label_list,(void**)&t2);

            }
            t2->string[0] = 'E';
        }
    }
    re_free(re);
}

