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

#include <string.h>
#include "maxent_utils.h"
#include <default_list.h>
#include "token.h"
#include "token_feature.h"
#include "repr.h"
#include <limits.h>
#include "hash.h"
#include <math.h>
#include "sentence.h"

unsigned long create_context(default_list feature_type_list, int left_window, int right_window, int i, me_context_type& context) {
    int j, k, n;
    string hash_string;
    n = default_list_length(feature_type_list);
    context.clear();
    for (j=-left_window+1; j<right_window; j++) {
        if (i+j<0 || i+j>=n) {
            //printf("%d ",i+j);
        } else {
            token_feature *ft = (token_feature *)default_list_get(feature_type_list, i+j);
            char key[1024];
            char rstr[1024];
            
            for (k = 0; k < FT_CHAR_MAP_LEN; k++) {
                sprintf(key,"char_%d[%03d]",j,k);
                context.push_back(make_pair(string(key), (float)ft->char_vector[k]));
                sprintf(key,"char_%d[%03d]=%d ",j,k,ft->char_vector[k]);
                hash_string.append(key);
            }
            if (ft->word==TRUE) {
                sprintf(key,"word_%d",j);
                context.push_back(make_pair(string(key), 1.0));
                sprintf(key,"word_%d=1 ",j);
                hash_string.append(key);
            }
            if (ft->number==TRUE) {
                sprintf(key,"number_%d",j);
                context.push_back(make_pair(string(key), 1.0));
                sprintf(key,"number_%d=1 ",j);
                hash_string.append(key);
            }
            //char key[1024];
            //char rstr[1024];
            //repr_string(rstr,ft->string);
            //sprintf(key,"token_%d='%s'",j,rstr);
            //context.push_back(make_pair(string(key), 1.0));
            
            //sprintf(key,"word_%d",j);
            //if (ft->word==TRUE) {
            //    context.push_back(make_pair(string(key), 1.0));
            //    sprintf(key,"stem_%d='%s'",j,ft->stemmed);
            //    context.push_back(make_pair(string(key), 1.0));
            //    sprintf(key,"capped_%d",j);
            //    if (ft->capped==TRUE) {
            //        context.push_back(make_pair(string(key), 1.0));
            //    } else {
            //        context.push_back(make_pair(string(key), 0.0));
            //    }
            //    sprintf(key,"upper_%d",j);
            //    if (ft->upper==TRUE) {
            //        context.push_back(make_pair(string(key), 1.0));
            //    } else {
            //        context.push_back(make_pair(string(key), 0.0));
            //    }
            //    sprintf(key,"number_%d",j);
            //    if (ft->number==TRUE) {
            //        context.push_back(make_pair(string(key), 1.0));
            //    } else {
            //        context.push_back(make_pair(string(key), 0.0));
            //    }
            //    sprintf(key,"incnum_%d",j);
            //    if (ft->incnum==TRUE) {
            //        context.push_back(make_pair(string(key), 1.0));
            //    } else {
            //        context.push_back(make_pair(string(key), 0.0));
            //    }
            //} else {
            //    context.push_back(make_pair(string(key), 0.0));
            //    //sprintf(key,"stem_%d=''",j);
            //    //context.push_back(make_pair(string(key), 1.0));
            //    for (k = 1; k < FT_CHAR_MAP_LEN; k++) {
            //        sprintf(key,"char_%d='_%03d_'",j,k);
            //        context.push_back(make_pair(string(key), (float)ft->char_vector[k]));
            //    }
            //}
        }
    }
    // cout << hash_string << "\n" << sdbm_string(hash_string) << endl;
    return sdbm_string(hash_string);
}

void create_model(MaxentModel& m, default_list feature_type_list, default_list label_list, int left_window, int right_window) {
    int i, j, k, n;
    me_context_type context;
    me_outcome_type outcome;

    sv_vector vect_E = sv_new(ULONG_MAX);
    sv_vector vect_I = sv_new(ULONG_MAX);

    n = default_list_length(feature_type_list);
    for (i = 0; i<n; i++) {
        char *c = (char *)default_list_get(label_list, i);
        token_feature *tf = (token_feature *)default_list_get(feature_type_list,i);
        if (tf->word != TRUE && tf->char_vector[0] != tf->length) {
            unsigned long index = create_context(feature_type_list,left_window,right_window,i,context);
            if (strcmp("E",c) == 0) {
                double v = sv_get_element_value(vect_E,index);
                sv_set_element(vect_E,index,v+1.0);
            } else {
                double v = sv_get_element_value(vect_I,index);
                sv_set_element(vect_I,index,v+1.0);
            }
        }
    }

    for (i = 0; i<n; i++) {
        char *c = (char *)default_list_get(label_list, i);
        token_feature *tf = (token_feature *)default_list_get(feature_type_list,i);
        if (tf->word != TRUE && tf->char_vector[0] != tf->length) {
            unsigned long index = create_context(feature_type_list,left_window,right_window,i,context);
            if (strcmp("E",c) == 0) {
                double v = sv_get_element_value(vect_E,index);
                if (v > 0) {
                    m.add_event(context,c,v);
                }
                sv_set_element(vect_E,index,0);
            } else {
                double v = sv_get_element_value(vect_I,index);
                if (v > 0) {
                    m.add_event(context,c,v);
                }
                sv_set_element(vect_I,index,0);
            }
        }
    }
}

void label_sentences(MaxentModel& m, default_list feature_type_list, default_list label_list, int left_window, int right_window) {
    int i,n;
    char E[2] = "E";
    char I[2] = "I";
    me_context_type context;
    
    n = default_list_length(feature_type_list);
    for (i = 0; i<n; i++) {
        token_feature *tf = (token_feature *)default_list_get(feature_type_list,i);
        float p = 0.0;
        
        if (tf->word != TRUE && tf->char_vector[0] != tf->length) {
            create_context(feature_type_list,left_window,right_window,i,context);
            p = m.eval(context,"E");
            if (p>=0.4) {
                default_list_append(label_list,E);
            } else {
                default_list_append(label_list,I);
            }
        } else {
            default_list_append(label_list,I);
        }
    }
}

int create_sentences(MaxentModel& m, default_list sentence_list, char *buffer, default_list feature_type_list, default_list label_list, char *filename, char *licensename, int id) {
    token_feature *ft = NULL;
    char *t = NULL;
    sv_vector vect;
    sentence *st = NULL;
    int i;

    vect = sv_new(ULONG_MAX);
    ft = (token_feature *)default_list_get(feature_type_list,0);
    int start = ft->start;
    for (i = 0; i<default_list_length(feature_type_list); i++) {
        ft = (token_feature *)default_list_get(feature_type_list,i);
        t = (char *)default_list_get(label_list,i);
        if (ft->word == TRUE) {
            double v = 0;
            unsigned long int index = 0;
            index = sdbm(ft->stemmed);
            v = sv_get_element_value(vect,index);
            sv_set_element(vect,index,v+1.0);
        }
        if (strcmp(t, "E")==0 || i == default_list_length(feature_type_list)-1) {
            if (i < default_list_length(feature_type_list)-1 && sv_nonzeros(vect)<2) {

            } else {
                double norm = sqrt(sv_inner(vect,vect));
                if (norm == 0) {
                    continue;
                }
                vect = sv_scalar_mult(vect,1.0/norm);
                st = sentence_create(buffer,start,ft->end,i,filename,licensename,id,vect);

                default_list_append(sentence_list,st);

                vect = sv_new(ULONG_MAX);
                start = ft->end;
            }
        }
    }
    return 0;
}

