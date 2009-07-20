#include "list.h"
#include "re.h"
#include "stem.h"
#include "token.h"
#include "feature_type.h"


#ifndef __TOKENIZER_H__
#define __TOKENIZER_H__

#if defined(__cplusplus)
extern "C" {
#endif

void create_sentence_list(char* buffer, default_list **list);
void create_features_from_sentences(default_list **list, default_list **feature_type_list,default_list **label_list);
void create_features_from_buffer(char *buffer, default_list **feature_type_list);

#if defined(__cplusplus)
}
#endif

#endif
