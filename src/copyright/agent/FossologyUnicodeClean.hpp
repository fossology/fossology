/*
 * Copyright (C) 2019, Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
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
 * Remove non UTF-8 characters form a file or stream.
 */

#ifndef UTILS_BACKUP_FOSSOLOGYDBMIGRATE_HPP_
#define UTILS_BACKUP_FOSSOLOGYDBMIGRATE_HPP_

#include <iostream>
#include <fstream>
#include <vector>
#include <boost/program_options.hpp>

#include "libfossUtils.hpp"

/**
 * Maximum number of buffered lines to store
 */
#define MAX_BUFFER_LEN 1024

/**
 * Class to remove non UTF-8 characters from file or steam and dump to another
 * file or stream
 */
class FossologyUnicodeClean
{
  private:
    std::ifstream sourceFile;
    std::ofstream destinationFile;
    std::vector<icu::UnicodeString> buffer;
    size_t bufferSize;
    bool stopRead;

    const std::string dirtyRead();
    void write(const icu::UnicodeString &output);
    void flush();
  public:
    FossologyUnicodeClean(std::string &source, std::string &destination);
    virtual ~FossologyUnicodeClean();
    void startConvert();
};

#endif /* UTILS_BACKUP_FOSSOLOGYDBMIGRATE_HPP_ */
