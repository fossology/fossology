<!-- SPDX-FileCopyrightText: © Fossology contributors

     SPDX-License-Identifier: GPL-2.0-only
-->
# FOSSology

[![Gitpod ready-to-code](https://img.shields.io/badge/Gitpod-ready--to--code-blue?logo=gitpod)](https://gitpod.io/#https://github.com/fossology/fossology)
[![GPL-2.0](https://img.shields.io/github/license/fossology/fossology)](LICENSE)
[![CII Best Practices](https://bestpractices.coreinfrastructure.org/projects/2395/badge)](https://bestpractices.coreinfrastructure.org/projects/2395)
[![Coverage Status](https://coveralls.io/repos/github/fossology/fossology/badge.svg?branch=master)](https://coveralls.io/github/fossology/fossology?branch=master)
[![Slack Channel](https://img.shields.io/badge/slack-fossology-blue.svg?longCache=true&logo=slack)](https://join.slack.com/t/fossology/shared_invite/enQtNzI0OTEzMTk0MjYzLTYyZWQxNDc0N2JiZGU2YmI3YmI1NjE4NDVjOGYxMTVjNGY3Y2MzZmM1OGZmMWI5NTRjMzJlNjExZGU2N2I5NGY)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/fossology/fossology)](https://github.com/fossology/fossology/releases/latest)
[![YouTube Channel](https://img.shields.io/badge/youtube-FOSSology-red.svg?&logo=youtube&link=https://www.youtube.com/channel/UCZGPJnQZVnEPQWxOuNamLpw)](https://www.youtube.com/channel/UCZGPJnQZVnEPQWxOuNamLpw)
[![REUSE status](https://api.reuse.software/badge/github.com/fossology/fossology)](https://api.reuse.software/info/github.com/fossology/fossology)

## About

FOSSology is an open source license compliance software system and toolkit. As a toolkit, you can run license, copyright, and export control scans from the command line. As a system, a database and web UI are provided to give you a compliance workflow. In one click you can generate an SPDX file or a ReadMe with all the copyrights notices from your software. FOSSology deduplication means that you can scan an entire distro, rescan a new version, and only the changed files will get rescanned. This is a big time saver for large projects.

[Check out Who Uses FOSSology!](https://www.fossology.org)

FOSSology does not give legal advice.
https://fossology.org/

## Requirements

The PHP versions 7.3 and later are supported to work for FOSSology. FOSSology requires Postgresql as the database server and apache httpd 2.6 as the web server. These and more dependencies are installed by `utils/fo-installdeps`.

To install Python dependencies, run `install/fo-install-pythondeps`.

## Installation

FOSSology should work with many Linux distributions.

See https://github.com/fossology/fossology/releases for source code download of the releases.

For installation instructions see [Install from Source](https://github.com/fossology/fossology/wiki/Install-from-Source)
page in [Github Wiki](https://github.com/fossology/fossology/wiki)

## Docker

FOSSology comes with a Dockerfile allowing the containerized execution
both as a single instance or in combination with an external PostgreSQL database.
**Note:** It is strongly recommended to use an external database for production
use since the standalone image does not take care of data persistency.

A pre-built Docker image is available from [Docker Hub](https://hub.docker.com/r/fossology/fossology/) and can be run using the following command:

```sh
docker run -p 8081:80 fossology/fossology
```

The docker image can then be used using http://IP_OF_DOCKER_HOST:8081/repo user fossy password fossy.


If you want to run Fossology with an external database container, you can use Docker Compose, via the following command: 

```sh
docker-compose up
```

Docker Compose is a tool that allows you to define and run multi-container applications using a YAML file. FOSSology provides a docker-compose.yml file that defines three services: scheduler, web, and db. 

The scheduler service runs the FOSSology scheduler daemon, which handles the analysis tasks. The web service runs the FOSSology web server, which provides the web interface. The db service runs a PostgreSQL database server, which stores the FOSSology data. 

The  `docker-compose up` command starts all the three services at once.

The FOSSology web service allows you to configure its database connection using some environment variables. These variables are defined in the docker-compose.yml file under the environment key.

- **FOSSOLOGY_DB_HOST:** Hostname of the PostgreSQL database server.
  An integrated PostgreSQL instance is used if not defined or set to `localhost`.
- **FOSSOLOGY_DB_NAME:** Name of the PostgreSQL database. Defaults to `fossology`.
- **FOSSOLOGY_DB_USER:** User to be used for PostgreSQL connection. Defaults to `fossy`.
- **FOSSOLOGY_DB_PASSWORD:** Password to be used for PostgreSQL connection. Defaults to `fossy`.

You can change them if you want to use a different database server or credentials. 

### Notes on PostgreSQL volume mounts (Docker)

When running FOSSology using Docker, the PostgreSQL data directory should follow the mount path recommended by the official Postgres container documentation:

https://hub.docker.com/_/postgres

Avoid using version-specific internal paths such as:

```
/var/lib/postgresql/16/data
```

These directories may not be persisted as expected when containers are recreated. Always reply on the directory structure defined by the Postgres image.
 
## Vagrant

FOSSology comes with a VagrantFile that can be used to create an isolated environment for FOSSology and its dependencies.

**Pre-requisites:** Vagrant >= 2.x and Virtualbox >= 5.2.x

**Steps:**

```
git clone https://github.com/fossology/fossology
cd fossology/
vagrant up
```

The server must be ready at [http://localhost:8081/repo/](http://localhost:8081/repo/). The login credentials are:

```
user: fossy
pass: fossy
```

## Test Instance

For trying out FOSSology quickly, a test instance is also available at [https://fossology.osuosl.org/](https://fossology.osuosl.org/). **This instance can be deleted or reinstalled at any time, thus it is not suitable for serving as your productive version**. The login credentials are as follows:

```
Username: fossy
Password: fossy
```

**Note:** The test instance is not up to date with the latest release. The instance will reset every night at 2 am UTC and all the user uploaded data will be lost.

## Quick dev prototype with GitPod.io
FOSSology is ready to be coded on GitPod.io. To use it, you would need to setup
an account. You can directly use the following button to launch the project on
GitPod.io:
[![Link to Gitpod](https://gitpod.io/button/open-in-gitpod.svg "Open in Gitpod")](https://gitpod.io/#https://github.com/fossology/fossology)

Once in, you should see 2 terminals, one running FOSSology scheduler and one
running the installation.

#### Handy scripts/aliases
For the ease of usability, following aliases/scripts have been defined and can
be used:
- `conffoss`: This will reconfigure cmake with all variables
- `buildfoss`: This will build the FOSSology using cmake
- `installfoss`: This will install FOSSology
- `fossrun`: Run the FOSSology scheduler
- `pg_stop`: Stop PostgreSQL server
- `pg_start`: Start PostgreSQL server

## Documentation

We are currently migrating our documentation to Github. At this stage, you can find general documentation at:
https://www.fossology.org/get-started/basic-workflow/
and developer docs on [Github Wiki](https://github.com/fossology/fossology/wiki) and https://fossology.github.io/

## Support

Mailing lists, FAQs, Release Notes, and other useful info are available
by clicking the documentation tab on the project website. We encourage
all users to join the mailing list and participate in discussions. You can also join FOSSOlogy's official space on Slack: [Join Slack](https://join.slack.com/t/fossology/shared_invite/enQtNzI0OTEzMTk0MjYzLTYyZWQxNDc0N2JiZGU2YmI3YmI1NjE4NDVjOGYxMTVjNGY3Y2MzZmM1OGZmMWI5NTRjMzJlNjExZGU2N2I5NGY)

If you'd like to talk to other FOSSology users and developers.
See [Contact Us](https://www.fossology.org/about/contact/)

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
the GNU Lesser General Public License version 2.1, [LGPL-2.1](<https://tldrlegal.com/license/gnu-lesser-general-public-license-v2.1-(lgpl-2.1)>).

    This library is free software; you can redistribute it and/or
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

Please see the LICENSE file included with this software for the full texts of
these licenses.
