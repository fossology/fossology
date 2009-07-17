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

typedef MaxentModel::context_type me_context_type;
typedef MaxentModel::outcome_type me_outcome_type;

void create_context(default_list **feature_type_list, int left_window, int right_window, int i, me_context_type& context) {
    int j, k, n;
    n = default_list_length(feature_type_list);
    context.clear();
    for (j=-left_window+1; j<right_window; j++) {
        if (i+j<0 || i+j>=n) {
            //printf("%d ",i+j);
        } else {
            feature_type *ft = NULL;
            default_list_get(feature_type_list, i+j, (void**)&ft);
            if (ft->word) {
                char k[100];
                sprintf(k,"word_%d='%s'",j,ft->stemmed);
                context.push_back(make_pair(string(k), 1.0));
                sprintf(k,"capped_%d='%s'",j,(ft->capped)?"true":"false");
                context.push_back(make_pair(string(k), 1.0));
                sprintf(k,"upper_%d='%s'",j,(ft->upper)?"true":"false");
                context.push_back(make_pair(string(k), 1.0));
                sprintf(k,"number_%d='%s'",j,(ft->number)?"true":"false");
                context.push_back(make_pair(string(k), 1.0));
                sprintf(k,"incnum_%d='%s'",j,(ft->incnum)?"true":"false");
                context.push_back(make_pair(string(k), 1.0));
            } else {
                float _n_ = 0.0; // \n
                float _p_ = 0.0; // .
                float _c_ = 0.0; // :
                float _s_ = 0.0; // ;
                float _e_ = 0.0; // !
                float _m_ = 0.0; // ,
                float _q_ = 0.0; // ?
                float _d_ = 0.0; // $
                float _a_ = 0.0; // @
                float _l_ = 0.0; // /
                float _rp_ = 0.0; // (
                float _lp_ = 0.0; // )
                float _rb_ = 0.0; // [
                float _lb_ = 0.0; // ]
                float _rc_ = 0.0; // {
                float _lc_ = 0.0; // }
                float _rw_ = 0.0; // <
                float _lw_ = 0.0; // >
                for (k = 0; k<strlen(ft->string); k++) {
                    if (ft->string[k] == '\n') {
                        _n_++;
                    } else if (ft->string[k] == '.') {
                        _p_++;
                    } else if (ft->string[k] == ':') {
                        _c_++;
                    } else if (ft->string[k] == ';') {
                        _s_++;
                    } else if (ft->string[k] == '!') {
                        _e_++;
                    } else if (ft->string[k] == '?') {
                        _q_++;
                    } else if (ft->string[k] == ',') {
                        _m_++;
                    } else if (ft->string[k] == '$') {
                        _d_++;
                    } else if (ft->string[k] == '@') {
                        _a_++;
                    } else if (ft->string[k] == '/') {
                        _l_++;
                    } else if (ft->string[k] == '(') {
                        _rp_++;
                    } else if (ft->string[k] == ')') {
                        _lp_++;
                    } else if (ft->string[k] == '[') {
                        _rb_++;
                    } else if (ft->string[k] == ']') {
                        _lb_++;
                    } else if (ft->string[k] == '{') {
                        _rc_++;
                    } else if (ft->string[k] == '}') {
                        _lc_++;
                    } else if (ft->string[k] == '<') {
                        _rw_++;
                    } else if (ft->string[k] == '>') {
                        _lw_++;
                    }
                }
                char k[100];
                if ( _n_ > 0.0 )  { 
                    sprintf(k,"char_%d='_n_'",j);
                    context.push_back(make_pair(string(k), _n_));
                } 
                if ( _p_ > 0.0 )  {
                    sprintf(k,"char_%d='_p_'",j);
                    context.push_back(make_pair(string(k), _p_));
                }
                if ( _c_ > 0.0 )  {
                    sprintf(k,"char_%d='_c_'",j);
                    context.push_back(make_pair(string(k), _c_));
                }
                if ( _s_ > 0.0 )  {
                    sprintf(k,"char_%d='_s_'",j);
                    context.push_back(make_pair(string(k), _s_));
                }
                if ( _e_ > 0.0 )  {
                    sprintf(k,"char_%d='_e_'",j);
                    context.push_back(make_pair(string(k), _e_));
                }
                if ( _m_ > 0.0 )  {
                    sprintf(k,"char_%d='_m_'",j);
                    context.push_back(make_pair(string(k), _m_));
                }
                if ( _q_ > 0.0 )  {
                    sprintf(k,"char_%d='_q_'",j);
                    context.push_back(make_pair(string(k), _q_));
                }
                if ( _d_ > 0.0 )  {
                    sprintf(k,"char_%d='_d_'",j);
                    context.push_back(make_pair(string(k), _d_));
                }
                if ( _a_ > 0.0 )  {
                    sprintf(k,"char_%d='_a_'",j);
                    context.push_back(make_pair(string(k), _a_));
                }
                if ( _l_ > 0.0 )  {
                    sprintf(k,"char_%d='_l_'",j);
                    context.push_back(make_pair(string(k), _l_));
                }
                if ( _rp_ > 0.0 ) {
                    sprintf(k,"char_%d='_rp_'",j);
                    context.push_back(make_pair(string(k), _rp_));
                }
                if ( _lp_ > 0.0 ) {
                    sprintf(k,"char_%d='_lp_'",j);
                    context.push_back(make_pair(string(k), _lp_));
                }
                if ( _rb_ > 0.0 ) {
                    sprintf(k,"char_%d='_rp_'",j);
                    context.push_back(make_pair(string(k), _rp_));
                }
                if ( _lb_ > 0.0 ) {
                    sprintf(k,"char_%d='_lb_'",j);
                    context.push_back(make_pair(string(k), _lb_));
                }
                if ( _rc_ > 0.0 ) {
                    sprintf(k,"char_%d='_rc_'",j);
                    context.push_back(make_pair(string(k), _rc_));
                }
                if ( _lc_ > 0.0 ) {
                    sprintf(k,"char_%d='_lc_'",j);
                    context.push_back(make_pair(string(k), _lc_));
                }
                if ( _rw_ > 0.0 ) {
                    sprintf(k,"char_%d='_rw_'",j);
                    context.push_back(make_pair(string(k), _rw_));
                }
                if ( _lw_ > 0.0 ) {
                    sprintf(k,"char_%d='_lw_'",j);
                    context.push_back(make_pair(string(k), _lw_));
                }
            }
        }
    }
}

void create_model(MaxentModel& m, default_list **feature_type_list, default_list **label_list, int left_window, int right_window) {
    int i, j, k, n;
    me_context_type context;
    me_outcome_type outcome;

    n = default_list_length(feature_type_list);
    for (i = 0; i<n; i++) {
        token *t = NULL;
        default_list_get(label_list, i, (void**)&t);
        create_context(feature_type_list,left_window,right_window,i,context);
        m.add_event(context, t->string);
    }
}

void label_sentences(MaxentModel& m, default_list **feature_type_list, default_list **label_list, int left_window, int right_window) {
    int i,n;
    me_context_type context;
    feature_type *ft = NULL;
    
    n = default_list_length(feature_type_list);
    for (i = 0; i<n; i++) {
        default_list_get(feature_type_list,i,(void**)&ft);
        create_context(feature_type_list,left_window,right_window,i,context);
        printf("%1.3f:  %s\n",m.eval(context,"E"), ft->string);
    }

}
    
int main(int argc, char **argv) {
    char * buffer;
    char * test_buffer;
    FILE *pFile;
    long lSize;
    size_t result;
    int i,j;
    default_list *sentence_list = NULL;
    default_list *feature_type_list = NULL;
    default_list *label_list = NULL;


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
    
    pFile = fopen(argv[2], "rb");
    if (pFile==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    fseek(pFile, 0, SEEK_END);
    lSize = ftell(pFile);
    rewind(pFile);

    test_buffer = (char*)malloc(sizeof(char)*lSize);
    if (test_buffer == NULL) {
        fputs("Memory error.\n",stderr);
        exit(2);
    }

    result = fread(test_buffer, 1, lSize, pFile);
    if (result != lSize) {
        fputs("Reading error",stderr); exit(3);
    }

    fclose(pFile);

    create_sentence_list(buffer,&sentence_list);
    create_features_from_sentences(&sentence_list,&feature_type_list, &label_list);

    int left_window = 3;
    int right_window = 3;

    MaxentModel m;
    m.begin_add_event();
    create_model(m, &feature_type_list, &label_list, left_window, right_window);
    m.end_add_event();
    m.train(1000, "lbfgs");

    //default_list_free(&label_list, &token_free);
    default_list_free(&feature_type_list, &feature_type_free);

    feature_type_list = NULL;
    create_features_from_buffer(test_buffer,&feature_type_list);
    label_sentences(m, &feature_type_list, &label_list, left_window, right_window);

    free(buffer);
    free(test_buffer);
    default_list_free(&sentence_list, &token_free);
    default_list_free(&label_list, &token_free);
    default_list_free(&feature_type_list, &feature_type_free);
    return(0);
}
