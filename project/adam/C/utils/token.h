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

#ifndef __TOKEN__H_
#define __TOKEN__H_

#if defined(__cplusplus)
extern "C" {
#endif

/* local includes */
#include <cvector.h>

/*!
 * \brief the token data type
 *
 * the struct that hold the string, where it is in the license that
 * it belongs to and the length of the string
 */
typedef struct token {
    char *string;
    int start;
    int end;
    int length;
} token;

/*!
 * \brief creates a vector function registry for a cvector of tokens
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* token_cvector_registry();

/*!
 * \brief takes a string and creates a token from it
 *
 * this function will create the token struct that belongs to a certain
 * string. This will allocate memory for the string in the token and will
 * set the start, end and length
 *
 * \param string: the string that will be stored in this token
 * \param start: the start location of the string in the source file
 * \param end: the location of the end of the string in the source file
 */
void* token_create_from_string(char *string, int start, int end);

#if defined(__cplusplus)
}
#endif

#endif
