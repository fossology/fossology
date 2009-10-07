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


#ifndef __TOKENIZER_H__
#define __TOKENIZER_H__

#if defined(__cplusplus)
extern "C" {
#endif

#include <default_list.h>
#include "re.h"
#include "token.h"
#include "token_feature.h"

void create_sentence_list(char* buffer, default_list list);
void create_features_from_sentences(default_list list, default_list feature_type_list,default_list label_list);
void create_features_from_buffer(char *buffer, default_list feature_type_list);

#if defined(__cplusplus)
}
#endif

#endif
