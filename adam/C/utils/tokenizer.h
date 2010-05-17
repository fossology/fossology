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


#ifndef TOKENIZER_H_INCLUDE
#define TOKENIZER_H_INCLUDE

#if defined(__cplusplus)
extern "C" {
#endif

/* local includes */
#include "cvector.h"
#include "re.h"
#include "token.h"
#include "token_feature.h"

/*!
 * \brief appends sentences to the end of the list
 *
 * This function take a buffer of <sentence>***</sentence>
 * and will take each "***" and appends it to the list that
 * was passed to this function.
 *
 * \param buffer: the buffer that needs to be analyzed
 * \param list: the list that this will be appenned to
 */
void create_sentence_list(char* buffer, cvector* list);

/*!
 * \brief creates individual words from the sentences
 *
 * takes a list of sentences and splits it into idividual tokens
 *
 * \param list: the list of sentences to grab the features from
 * \param feature_type_list: the destination of all the features
 * \param label_list: TODO figure out what this is doing
 */
void create_features_from_sentences(cvector* list, cvector* feature_type_list,cvector* label_list);

/*!
 * \brief creates indivular word features from a buffer
 *
 * takes a buffer and splits it into individual tokens and appends
 * them to the list that was passed to the function
 *
 * \param buffer: the source of the features
 * \param feature_type_list: the destination of the features
 */
void create_features_from_buffer(char *buffer, cvector* feature_type_list);

#if defined(__cplusplus)
}
#endif

#endif /* TOKENIZER_H_INCLUDE */
