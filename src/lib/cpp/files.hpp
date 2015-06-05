/*
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#ifndef FILES_HPP_
#define FILES_HPP_

#include <string>
#include <glib.h>

namespace fo
{

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
    unsigned long id;
    std::string fileName;
  };

  std::string getStringFromFile(const char* filename, const unsigned long int maximumBytes = 1 << 20);
  std::string getStringFromFile(std::string const& filename, const unsigned long int maximumBytes = 1 << 20);
}

#endif /* FILES_HPP_ */
