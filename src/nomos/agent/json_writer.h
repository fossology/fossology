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
 * \brief Write the scan output to a temp file
 *
 * The function writes the output of a scan result to a temp file in append
 * mode which is read by parseTempJson() to create a single JSON output.
 */
void writeToTemp();

/**
 * \brief Write the scan result as JSON to STDOUT
 */
void writeToStdOut();

/**
 * \brief Read temp file and print a JSON to STDOUT
 *
 * Reads the temp file created by writeToTemp(), parse it and create a JSON.
 * Then writes this JSON to STDOUT.
 */
void parseTempJson();

/**
 * \brief Unescape the path separator from JSON
 *
 * Unescape the path separator from JSON string to make it readable.
 *
 * `"\/folder\/folder2\/file" => "/folder/folder2/file"
 * @param json String to unescape
 * @return The JSON with unescaped path separator.
 */
char *unescapePathSeparator(char* json);

#endif /* _JSON_WRITER_H_ */
