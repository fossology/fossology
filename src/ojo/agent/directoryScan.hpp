/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
