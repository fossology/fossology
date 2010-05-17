/*********************************************************************
Copyright (C) 2009, 2010 Hewlett-Packard Development Company, L.P.

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

#ifndef __MAXENT_UTILS_H__
#define __MAXENT_UTILS_H__

/* std library */
#include <stdio.h>
#include <stdlib.h>
#include <string>
#include <vector>
#include <ctype.h>

/* local includes */
#include "cvector.h"
#include "re.h"
#include "token.h"
#include "token_feature.h"
#include "tokenizer.h"

/* other libraries */
#include <maxent/maxentmodel.hpp>
#include <sparsevect.h>
#include <cvector.h>

using namespace maxent;
using namespace std;


typedef MaxentModel::context_type me_context_type;
typedef MaxentModel::outcome_type me_outcome_type;

/*!
 *
 *
 * \param feature_type_list:
 * \param l_window:
 * \param r_window:
 * \param iter:
 * \param context:
 */
unsigned long create_context(cvector* feature_type_list, int l_window, int r_window, cvector_iterator iter, me_context_type& context);

/*!
 *
 *
 * \param m:
 * \param feature_type_list:
 * \param label_list:
 * \param l_window:
 * \param r_window:
 */
void create_model(MaxentModel& m, cvector* feature_type_list, cvector* label_list, int l_window, int r_window);

/*!
 *
 *
 * \param m:
 * \param feature_type_list:
 * \param label_list:
 * \param l_window:
 * \param r_window:
 */
void label_sentences(MaxentModel& m, cvector* feature_type_list, cvector* label_list, int l_window, int r_window);

/*!
 *
 *
 * \param m:
 * \param sentence_list:
 * \param buffer:
 * \param feature_type_list:
 * \param label_list:
 * \param filename:
 * \param licensename:
 * \param id:
 */
int create_sentences(MaxentModel& m, cvector* sentence_list, char *buffer, cvector* feature_type_list, cvector* label_list, char *filename, char *licensename, int id);

#endif
