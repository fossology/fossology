/***************************************************************
 Copyright (C) 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

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

 ***************************************************************/

/**
 * \file
 * \brief Handle JSON outputs
 */

#ifndef _JSON_WRITER_H_
#define _JSON_WRITER_H_

/**
 * \brief Write the scan output as a JSON
 */
void writeJson();

/**
 * \brief Unescape the path separator from JSON
 *
 * Unescape the path separator from JSON string to make it readable.
 *
 * `"\/folder\/folder2\/file" => "/folder/folder2/file"
 * @param json String to unescape
 * @return The JSON with unescaped path separator.
 */
char *unescapePathSeparator(const char* json);

/**
 * Initialize the semaphore and boolean to store flag for comma in JSON
 */
void initializeJson();

/**
 * Destory the semaphore the comma flag for JSON
 */
void destroyJson();


#endif /* _JSON_WRITER_H_ */
