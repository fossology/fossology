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

#include <stdio.h>
#include <stdlib.h>
#include <ctype.h>

#ifndef __FILE_UTILS_H__
#define __FILE_UTILS_H__

#if defined(__cplusplus)
extern "C" {
#endif

/*!
 * Opens and reads the file specified into the char array given. It is
 * important to note that this function will reset the pointer that the
 * charr array was previously pointing to. Therefore, if this pointer owned
 * any memory prior to calling this function, that memory will be lost
 *
 * \param filename: the name of the file that should be read from
 * \param buffer: a pointer to the char array that the contents of the file
 *                  will be stored in
 */
void openfile(char *filename, char **buffer);

/*!
 * Opens and reads the file specified into the char array given. This
 * function works exactly like openfile, however it is possible to provide
 * a maximum size that it will not read beyond.
 *
 * \param filename: the name of the file that should be read from
 * \param buffer: a pointer to the char array to store the file into
 * \param max: the maximum number of bytes that should be read from the file
 */
void readtomax(char *filename, char **buffer, size_t max);

#if defined(__cplusplus)
}
#endif

#endif
