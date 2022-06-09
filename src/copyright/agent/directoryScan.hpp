/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
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
