/*
 * Copyright (C) 2020, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
 * -# Matches at most 5 identifiers each with length greater than 3 (based on
 * https://github.com/spdx/license-list-data/tree/master/html)
 */
#define SPDX_LICENSE_LIST "spdx-licen[cs]e(?:id|[- ]identifier): \\K((?:(?: (?:and|or|with) )?\\(?(?:[\\w\\d\\.\\+\\-]{3,})\\)?){1,5})"
/**
 * @def SPDX_LICENSE_NAMES
 * @brief Regex to filter license names from list of license list
 *
 * -# License names will consist of words, digits, dots and hyphens.
 * -# Length of license names greater than 3 (based on
 * https://github.com/spdx/license-list-data/tree/master/html)
 */
#define SPDX_LICENSE_NAMES "(?: and | or | with )?\\(?([\\w\\d\\.\\+\\-]{3,})\\)?"
/**
 * @def SPDX_DUAL_LICENSE
 * @brief Regex to check if Dual-license
 *
 * -# Check if the license string contains or, with or and.
 */
#define SPDX_DUAL_LICENSE "(?: (and|or|with)? )"

#endif /* SRC_OJO_AGENT_OJOREGEX_HPP_ */
