/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
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
