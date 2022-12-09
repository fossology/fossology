/*
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef FILES_HPP_
#define FILES_HPP_

#include <string>
#include <glib.h>

/**
 * \file
 * \brief Utility functions for file handling
 */

namespace fo
{

  /**
   * \class File
   * \brief Class to handle file related operations.
   */
  class File
  {
  public:
    File(unsigned long id, const char* fileName);
    File(unsigned long id, std::string const& fileName);

    unsigned long getId() const;
    std::string getContent(const unsigned long int maximumBytes = 1 << 20) const;
    const std::string& getFileName() const;
    bool isReadable() const;
  private:
    unsigned long id;         ///< ID of the file
    std::string fileName;     ///< Path of the file
  };

  std::string getStringFromFile(const char* filename, const unsigned long int maximumBytes = 1 << 20);
  std::string getStringFromFile(std::string const& filename, const unsigned long int maximumBytes = 1 << 20);
}

#endif /* FILES_HPP_ */
