/*
 * Copyright (C) 2019, Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
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

#ifndef DIRECTORYSCAN_HPP_
#define DIRECTORYSCAN_HPP_

#include <boost/filesystem.hpp>
#include <boost/range/iterator_range.hpp>
#include <iostream>

#include "copyrightUtils.hpp"

void scanDirectory(const CopyrightState& state, const bool json,
    const std::string directoryPath);

#endif /* DIRECTORYSCAN_HPP_ */
