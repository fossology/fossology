/*
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "files.hpp"
#include <fstream>
#include <sys/stat.h>
#include <sstream>

/**
 * \file
 * \brief Utility functions for file handling
 */

/**
 * \namespace fo
 * \brief `fo` namespace holds the FOSSology library functions.
 */
namespace fo
{

  /**
   * \brief Reads the content of a file and return it as a string.
   *
   * Read the content of the file defined by the filename. Function also limits
   * the length of the file content by using maximumBytes.
   * \param filename     Path of the file to read.
   * \param maximumBytes Maximum length to read (set -1 to read full length).
   * \return The file content limited by maximumBytes as string.
   * \todo respect limit of maximumBytes
   */
  std::string getStringFromFile(const char* filename, const unsigned long int maximumBytes)
  {


    std::ifstream inStream(filename, std::ios::in | std::ios::binary);
    if (inStream)
    {
      std::string contents;
      inStream.seekg(0, std::ios::end);
      if (!(inStream.rdstate() & std::ifstream::failbit))
      {
        const unsigned long int endPos = inStream.tellg();
        contents.resize((maximumBytes > 0 && (endPos > maximumBytes)) ? maximumBytes : endPos);
        inStream.seekg(0, std::ios::beg);
        inStream.read(&contents[0], contents.size());
      }
      else
      {
        // TODO respect limit of maximumBytes
        inStream.clear(std::ifstream::goodbit);

        std::stringstream ss;
        ss << inStream.rdbuf();

        return ss.str();
      }
      inStream.close();
      return (contents);
    }
    throw(errno);
  }

  /**
   * \overload std::string fo::getStringFromFile(std::string const& filename, const unsigned long int maximumBytes)
   */
  std::string getStringFromFile(std::string const& filename, const unsigned long int maximumBytes)
  {
    return getStringFromFile(filename.c_str(), maximumBytes);
  };

  /**
   * \brief Get the content of the file limited by maximumBytes
   * \param maximumBytes Limit of file content to read (set -1 to read full
   * content).
   * \return Content of the file as a string.
   * \sa fo::getStringFromFile()
   */
  std::string File::getContent(const unsigned long int maximumBytes) const
  {
    return getStringFromFile(fileName, maximumBytes);
  }

  /**
   * Get the current file path
   * \return File path
   */
  const std::string& File::getFileName() const
  {
    return fileName;
  }

  /**
   * Constructor for File class
   * \param _id       ID of the file
   * \param _fileName Path of the file
   */
  File::File(unsigned long _id, const char* _fileName) : id(_id), fileName(_fileName)
  {
  }

  /**
   * \overload File::File(unsigned long id, std::string const& fileName)
   */
  File::File(unsigned long id, std::string const& fileName) : id(id), fileName(fileName)
  {
  }

  /**
   * Get the ID of the file
   * \return ID of the file
   */
  unsigned long File::getId() const
  {
    return id;
  }

  /**
   * Check if the file is accessible and readable
   * \return True if the the file is readable, false otherwise
   */
  bool File::isReadable() const
  {
    struct stat statStr;
    return (stat(fileName.c_str(), &statStr) == 0);
  }
}
