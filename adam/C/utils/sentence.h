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

#ifndef __SENTENCE_TYPE__H_
#define __SENTENCE_TYPE__H_

/* local includes */
#include <cvector.h>

/* other library includes */
#include <sparsevect.h>

#if defined(__cplusplus)
extern "C" {
#endif

// Our simple datatype
typedef struct sentence {
    char *string;
    int start;
    int end;
    int position;
    char *filename;
    char *licensename;
    int id;
    sv_vector vector;
} sentence;


/*!
 * \brief creates a vector function registry for a cvector of tokens
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* sentence_cvector_registry();

/*!
 * \brief takes a string and creates a sentence from it
 *
 * this function will create the sentence struct that belongs to a certain
 * string. This will allocate any necessary memory and create the different
 * aspects of the sentence.
 *
 * \param string: the string that will be stored in this sentece
 * \param start: the start location of the string in the source file
 * \param end: the location of the end of the string in the source file
 * \param position: the position in the file of this sentence
 * \param filename: the name of the file that this sentence came from
 * \param licensename: the name of the license that this sentence belongs to
 * \param id: TODO figure this out
 * \param sv_vector vector: TODO figure this out
 */
sentence* sentence_create(char *string, int start, int end, int position, char *filename, char *licensename, int id, sv_vector vector);

/*!
 * \brief public destructor for the sentence datatype
 *
 * this function will free the memory allocated by the sentence_create function.
 *
 * \param sent: the sentence that should be destructed
 */
sentence* sentence_destroy(sentence* sent);

#if defined(__cplusplus)
}
#endif

#endif
