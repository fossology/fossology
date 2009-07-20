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
#include "feature_type.h"

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
            if (ft->word==TRUE) {
                char key[100];
                sprintf(key,"word_%d='%s'",j,ft->stemmed);
                context.push_back(make_pair(string(key), 1.0));
                sprintf(key,"capped_%d='%s'",j,(ft->capped==TRUE)?"true":"false");
                context.push_back(make_pair(string(key), 1.0));
                sprintf(key,"upper_%d='%s'",j,(ft->upper==TRUE)?"true":"false");
                context.push_back(make_pair(string(key), 1.0));
                sprintf(key,"number_%d='%s'",j,(ft->number==TRUE)?"true":"false");
                context.push_back(make_pair(string(key), 1.0));
                sprintf(key,"incnum_%d='%s'",j,(ft->incnum==TRUE)?"true":"false");
                context.push_back(make_pair(string(key), 1.0));
            } else {
                char key[100];
                for (k = 0; k < FT_CHAR_MAP_LEN; k++) {
                    if (ft->char_vector[k]>0) {
                        sprintf(key,"char_%d='_%02d_'",j,k);
                        context.push_back(make_pair(string(key), (float)ft->char_vector[k]));
                    }
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
    
    n = default_list_length(feature_type_list);
    for (i = 0; i<n; i++) {
        float p = 0.0;
        token *t = (token*)malloc(sizeof(token));
        t->string = (char*)malloc(sizeof(char)*2);
        t->string[1] = '\0';
        
        create_context(feature_type_list,left_window,right_window,i,context);
        p = m.eval(context,"E");
        if (p>=0.5) {
            t->string[0] = 'E';
        } else {
            t->string[0] = 'I';
        }
        default_list_append(label_list,(void**)&t);
    }
}

