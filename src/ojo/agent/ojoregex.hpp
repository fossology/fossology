/*
 SPDX-FileCopyrightText: © 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * The list of regex used in the agent.
 *
 * Each regex is stored as a macro.
 */
#ifndef SRC_OJO_AGENT_OJOREGEX_HPP_
#define SRC_OJO_AGENT_OJOREGEX_HPP_

/**
 * @def SPDX_LICENSE_LIST
 * @brief Regex to filter the list of licenses.
 *
 * -# The regex first finds occurance of `spdx-license-identifier` in the text
 * -# Throw the text `spdx-license-identifier`
 * -# Captures a valid SPDX-style license token sequence. The shared SPDX
 *    expression parser validates the captured expression semantics.
 */
#define SPDX_LICENSE_TOKEN "\\(?[A-Za-z0-9\\.\\+\\-:]{2,}[A-Za-z0-9\\+]\\)?"
#define SPDX_LICENSE_OPERATOR "(?:AND|OR|WITH)"
#define SPDX_LICENSE_LIST "spdx-licen[cs]e(?:id|[- ]identifier):[ \\t]*\\K(" SPDX_LICENSE_TOKEN "(?:[ \\t]+" SPDX_LICENSE_OPERATOR "[ \\t]+" SPDX_LICENSE_TOKEN ")*)"
/**
 * @def SPDX_LICENSE_NAMES
 * @brief Regex to filter license names from list of license list
 *
 * -# License names will consist of words, digits, dots and hyphens.
 * -# Length of license names greater than 2 (based on
 * https://github.com/spdx/license-list-data/tree/master/html)
 * -# License name should end with a word, digit or +
 */
#define SPDX_LICENSE_NAMES "(?: and | or | with )?\\(?([\\w\\d\\.\\+\\-:]{1,}[\\w\\d\\+])\\)?"
/**
 * @def SPDX_DUAL_LICENSE
 * @brief Regex to check if Dual-license
 *
 * -# Check if the license string contains or, with or and.
 */
#define SPDX_DUAL_LICENSE "(?: (and|or|with)? )"

#endif /* SRC_OJO_AGENT_OJOREGEX_HPP_ */
