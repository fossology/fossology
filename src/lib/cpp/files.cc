/*
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "files.hpp"
#include <fstream>
#include <sys/stat.h>
#include <sstream>

namespace fo
{

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


  std::string getStringFromFile(std::string filename, const unsigned long int maximumBytes)
  {
    return getStringFromFile(filename.c_str(), maximumBytes);
  };

  std::string File::getContent(const unsigned long int maximumBytes) const
  {
    return getStringFromFile(fileName, maximumBytes);
  }

  const std::string& File::getFileName() const
  {
    return fileName;
  }

  File::File(unsigned long _id, const char* _fileName) : id(_id), fileName(_fileName)
  {
  };

  unsigned long File::getId() const
  {
    return id;
  }

  bool File::isReadable() const
  {
    struct stat statStr;
    return (stat(fileName.c_str(), &statStr) == 0);
  }
}
