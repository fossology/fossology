/*
 * Copyright (C) 2019, Siemens AG
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef SRC_OJO_AGENT_DIRECTORYSCAN_HPP_
#define SRC_OJO_AGENT_DIRECTORYSCAN_HPP_

#include <boost/filesystem.hpp>
#include <boost/range/iterator_range.hpp>
#include <iostream>

#include "OjoUtils.hpp"
#include "OjoAgent.hpp"

void scanDirectory(const bool json, const std::string &directoryPath);

#endif /* SRC_OJO_AGENT_DIRECTORYSCAN_HPP_ */
