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

#ifndef __MAXENT_UTILS_H__
#define __MAXENT_UTILS_H__

using namespace maxent;
using namespace std;

typedef MaxentModel::context_type me_context_type;
typedef MaxentModel::outcome_type me_outcome_type;

void create_context(default_list **feature_type_list, int left_window, int right_window, int i, me_context_type& context);
void create_model(MaxentModel& m, default_list **feature_type_list, default_list **label_list, int left_window, int right_window);
void label_sentences(MaxentModel& m, default_list **feature_type_list, default_list **label_list, int left_window, int right_window);

#endif
