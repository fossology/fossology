# FOSSology

[![Travis-CI Build Status](https://travis-ci.org/fossology/fossology.png)](https://travis-ci.org/fossology/fossology/)
[![Stories in Ready](https://badge.waffle.io/fossology/fossology.svg?label=ready&title=Ready)](http://waffle.io/fossology/fossology)
[![Coverage Status](https://coveralls.io/repos/github/fossology/fossology/badge.svg?branch=master)](https://coveralls.io/github/fossology/fossology?branch=master)

## About

FOSSology is a open source license compliance software system and toolkit.  As a toolkit you can run license, copyright and export control scans from the command line.  As a system, a database and web ui are provided to give you a compliance workflow. In one click you can generate an SPDX file, or a ReadMe with all the copyrights notices from your software. FOSSology deduplication means that you can scan an entire distro, rescan a new version, and only the changed files will get rescanned. This is a big time saver for large projects.

[Check out Who Uses FOSSology!](http://www.fossology.org/projects/fossology/wiki/WhoUsesFOSSology)

FOSSology does not give legal advice.
http://fossology.org/

## Requirements

The PHP versions 5.5.9 to 5.6.x are supported to work for FOSSology. FOSSology requires Postgresql as database server and apache httpd 2.4 as web server. These and more dependencies are installed by `utils/fo-installdeps`.

## Installation

FOSSology should work with many Linux distributions.

See https://github.com/fossology/fossology/releases for source code download of the releases.

For installation instructions see [Github Wiki](https://github.com/fossology/fossology/wiki)

## Docker

FOSSology comes with a Dockerfile allowing the containerized execution
both as single instance or in combination with an external PostgreSQL database.
**Note:** It is strongly recommended to use an external database for production
use, since the the standalone image does not take care of data persistency.

A pre-built Docker image is available from [Docker Hub](https://hub.docker.com/r/fossology/fossology/) and can be run using following command:
``` sh
docker run -p 8081:80 fossology/fossology
```

The docker image can then be used using http://IP_OF_DOCKER_HOST:8081/repo user fossy passwd fossy.

Execution with external database container can be done using Docker Compose.
The Docker Compose file is located under the `/install` folder can can be run using following command:
``` sh
cd install
docker-compose up
```

The Docker image allows configuration of it's database connection over a set of environment variables.

- **FOSSOLOGY_DB_HOST:** Hostname of the PostgreSQL database server.
  An integrated PostgreSQL instance is used if not defined or set to `localhost`.
- **FOSSOLOGY_DB_NAME:** Name of the PostgreSQL database. Defaults to `fossology`.
- **FOSSOLOGY_DB_USER:** User to be used for PostgreSQL connection. Defaults to `fossy`.
- **FOSSOLOGY_DB_PASSWORD:** Password to be used for PostgreSQL connection. Defaults to `fossy`.

## Documentation

We are currently migrating our documentation to github.  At this stage you can find general documentation at:
http://www.fossology.org/projects/fossology/wiki/User_Documentation
and developer docs here on [github](https://github.com/fossology/fossology/wiki)

## Support

Mailing lists, FAQs, Release Notes, and other useful info is available
by clicking the documentation tab on the project website. We encourage
all users to join the mailing list and participate in discussions.
There is also a #fossology IRC channel on the freenode IRC network if
you'd like to talk to other FOSSology users and developers.
See [Contact Us](https://www.fossology.org/get-started)

## Contributing

We really like contributions in several forms, see [CONTRIBUTING.md](CONTRIBUTING.md)

## Licensing

The original FOSSology source code and associated documentation
including these web pages are Copyright (C) 2007-2012 HP Development
Company, L.P. In the past years, other contributors added source code 
and documentation to the project, see the NOTICES file or the referring
files for more information.

Any modifications or additions to source code or documentation
contributed to the FOSSology project are Copyright (C) the contributor,
and should be noted as such in the comments section of the modified file(s).

FOSSology is licensed under [GPL-2.0](https://tldrlegal.com/license/gnu-general-public-license-v2)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License along
    with this program; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

Exception:

All of the FOSSology source code is licensed under the terms of the GNU
General Public License version 2, with the following exceptions:

libfossdb and libfossrepo libraries are licensed under the terms of
the GNU Lesser General Public License version 2.1, [LGPL-2.1](https://tldrlegal.com/license/gnu-lesser-general-public-license-v2.1-(lgpl-2.1)).

    This library are free software; you can redistribute it and/or
    modify it under the terms of the GNU Lesser General Public
    License as published by the Free Software Foundation; either
    version 2.1 of the License.

    This library is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
    Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public
    License along with this library; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
    02110-1301  USA

Please see the files COPYING and COPYING.LGPL included with this
software for the full text of these licenses.

## Screenshots

![Browsing](examples/Browseb.jpg)

![LicenseBrowser](examples/LicenseBrowserb.jpg)

![Concluding a license](examples/Concludeb.jpg)
