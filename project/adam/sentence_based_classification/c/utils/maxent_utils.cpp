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

#include "maxent_utils.h"
#include <default_list.h>
#include "token.h"
#include "token_feature.h"

void create_context(default_list feature_type_list, int left_window, int right_window, int i, me_context_type& context) {
    int j, k, n;
    n = default_list_length(feature_type_list);
    context.clear();
    for (j=-left_window+1; j<right_window; j++) {
        if (i+j<0 || i+j>=n) {
            //printf("%d ",i+j);
        } else {
            token_feature *ft = (token_feature *)default_list_get(feature_type_list, i+j);
            char key[1024];
            //char *ptr = ft->string;
            //sprintf(key,"token_%d='",j);
            //for (ptr = ft->string; *ptr != '\0'; ptr++) {
            //    sprintf(key,"%s0%xx",key,*ptr);
            //}
            //sprintf(key,"%s'",key);
            //if (strlen(key)>1023) {
            //    fprintf(stderr,"Error: string overflow at %s:%s\n" __FILE__, __LINE__);
            //}
            //context.push_back(make_pair(string(key), 1.0));
            sprintf(key,"capped_%d",j);
            if (ft->capped==TRUE) {
                context.push_back(make_pair(string(key), 1.0));
            } else {
                context.push_back(make_pair(string(key), 0.0));
            }
            sprintf(key,"upper_%d",j);
            if (ft->upper==TRUE) {
                context.push_back(make_pair(string(key), 1.0));
            } else {
                context.push_back(make_pair(string(key), 0.0));
            }
            sprintf(key,"number_%d",j);
            if (ft->number==TRUE) {
                context.push_back(make_pair(string(key), 1.0));
            } else {
                context.push_back(make_pair(string(key), 0.0));
            }
            sprintf(key,"incnum_%d",j);
            if (ft->incnum==TRUE) {
                context.push_back(make_pair(string(key), 1.0));
            } else {
                context.push_back(make_pair(string(key), 0.0));
            }
            sprintf(key,"word_%d",j);
            if (ft->word==TRUE) {
                context.push_back(make_pair(string(key), 1.0));
                sprintf(key,"stem_%d='%s'",j,ft->stemmed);
                context.push_back(make_pair(string(key), 1.0));
            } else {
                context.push_back(make_pair(string(key), 0.0));
                sprintf(key,"stem_%d=''",j);
                context.push_back(make_pair(string(key), 1.0));
            }
            //for (k = 0; k < FT_CHAR_MAP_LEN; k++) {
            //    sprintf(key,"char_%d='_%02d_'",j,k);
            //    context.push_back(make_pair(string(key), (float)ft->char_vector[k]));
            //}
        }
    }
}

void create_model(MaxentModel& m, default_list feature_type_list, default_list label_list, int left_window, int right_window) {
    int i, j, k, n;
    me_context_type context;
    me_outcome_type outcome;

    n = default_list_length(feature_type_list);
    for (i = 0; i<n; i++) {
        char *c = (char *)default_list_get(label_list, i);
        token_feature *tf = (token_feature *)default_list_get(feature_type_list,i);
        create_context(feature_type_list,left_window,right_window,i,context);
        m.add_event(context, c);
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
        
        if (tf->word == FALSE) {
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

