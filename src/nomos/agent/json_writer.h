/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

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
