/* This file defines the doxygen home page.  Currently doxygen is only used on
 * source.
 * SPDX-FileCopyrightText: © Fossology contributors
 * 
 * SPDX-License-Identifier: GPL-2.0-only
 */

/*!
 * \mainpage Overview of FOSSology
 * \tableofcontents
 *
 * More developer information can be found at https://github.com/fossology/fossology/wiki
 *
 * \section about About
 * FOSSology is a open source license compliance software system and toolkit.
 * As a toolkit you can run license, copyright and export control scans from the command line.
 * As a system, a database and web ui are provided to give you a compliance workflow.
 * In one click you can generate an SPDX file, or a ReadMe with all the copyrights
 * notices from your software. FOSSology deduplication means that you can scan an entire distro,
 * rescan a new version, and only the changed files will get rescanned. This is a big time saver for large projects.
 * [Check out Who Uses FOSSology!](https://www.fossology.org)
 * FOSSology does not give legal advice.
 *
 * \section requirements Requirements
 * The PHP versions 5.5.9 to 7.1.x are supported to work for FOSSology.
 * FOSSology requires Postgresql as database server and apache httpd 2.4 as web server.
 * These and more dependencies are installed by `utils/fo-installdeps`.
 *
 * \section installation Installation
 * FOSSology should work with many Linux distributions.
 *
 * See https://github.com/fossology/fossology/releases for source code download of the releases.
 *
 * For installation instructions see [Github Wiki](https://github.com/fossology/fossology/wiki)
 * \subsection dockerinstallation Docker
 * FOSSology comes with a Dockerfile allowing the containerized execution
 * both as single instance or in combination with an external PostgreSQL database.
 * **Note:** It is strongly recommended to use an external database for production
 * use, since the the standalone image does not take care of data persistency.
 *
 * A pre-built Docker image is available from [Docker Hub](https://hub.docker.com/r/fossology/fossology/)
 * and can be run using following command:
 * \code docker run -p 8081:80 fossology/fossology \endcode
 *
 * The docker image can then be used using http://IP_OF_DOCKER_HOST:8081/repo user fossy passwd fossy.
 *
 * Execution with external database container can be done using Docker Compose.
 * The Docker Compose file is located under the `/install` folder can can be run using following command:
 * \code
 * cd install
 * docker-compose up
 * \endcode
 *
 * The Docker image allows configuration of it's database connection over a set of environment variables.
 * - **FOSSOLOGY_DB_HOST:** Hostname of the PostgreSQL database server.
 *     An integrated PostgreSQL instance is used if not defined or set to `localhost`.
 * - **FOSSOLOGY_DB_NAME:** Name of the PostgreSQL database. Defaults to `fossology`.
 * - **FOSSOLOGY_DB_USER:** User to be used for PostgreSQL connection. Defaults to `fossy`.
 * - **FOSSOLOGY_DB_PASSWORD:** Password to be used for PostgreSQL connection. Defaults to `fossy`.
 * \subsection installationvagrant Vagrant
 * FOSSology comes with a VagrantFile that can be used to create an isolated environment for FOSSology and its dependencies.
 *
 * **Pre-requisites:**  Vagrant >= 2.x and Virtualbox >= 5.2.x
 * **Steps:**
 * \code
 * git clone https://github.com/fossology/fossology
 * cd fossology/
 * vagrant up
 * \endcode
 *
 * The server must be ready at [http://localhost:8081/repo/](http://localhost:8081/repo/) and user can login the credentials using
 * \code
 * user: fossy
 * pass: fossy
 * \endcode
 *
 * \section support Support
 * Mailing lists, FAQs, Release Notes, and other useful info is available
 * by clicking the documentation tab on the [project website](https://www.fossology.org/).
 * We encourage all users to join the mailing list and participate in discussions.
 * There is also a #fossology IRC channel on the freenode IRC network if
 * you'd like to talk to other FOSSology users and developers.
 * See [Contact Us](https://www.fossology.org/about/contact/)
 * \section contributing Contributing
 * We really like contributions in several forms, see [CONTRIBUTING.md](CONTRIBUTING.md)
 * \section licensing Licensing
 * The original FOSSology source code and associated documentation
 * including these web pages are Copyright (C) 2007-2012 HP Development
 * Company, L.P. In the past years, other contributors added source code
 * and documentation to the project, see the NOTICES file or the referring
 * files for more information.
 *
 * Any modifications or additions to source code or documentation
 * contributed to the FOSSology project are Copyright (C) the contributor,
 * and should be noted as such in the comments section of the modified file(s).
 *
 * FOSSology is licensed under [GPL-2.0](https://tldrlegal.com/license/gnu-general-public-license-v2)
 * >    This program is free software; you can redistribute it and/or modify
 * >    it under the terms of the GNU General Public License as published by
 * >    the Free Software Foundation; version 2 of the License
 * >
 * >    This program is distributed in the hope that it will be useful,
 * >    but WITHOUT ANY WARRANTY; without even the implied warranty of
 * >    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * >    GNU General Public License for more details.
 * >
 * >    You should have received a copy of the GNU General Public License along
 * >    with this program; if not, write to the Free Software Foundation, Inc.,
 * >    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * Exception:
 *
 * All of the FOSSology source code is licensed under the terms of the GNU
 * General Public License version 2, with the following exceptions:
 * libfossdb and libfossrepo libraries are licensed under the terms of
 * the GNU Lesser General Public License version 2.1,
 * [LGPL-2.1](https://tldrlegal.com/license/gnu-lesser-general-public-license-v2.1-(lgpl-2.1)).
 * >    This library are free software; you can redistribute it and/or
 * >    modify it under the terms of the GNU Lesser General Public
 * >    License as published by the Free Software Foundation; either
 * >    version 2.1 of the License.
 * >
 * >    This library is distributed in the hope that it will be useful,
 * >    but WITHOUT ANY WARRANTY; without even the implied warranty of
 * >    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * >    Lesser General Public License for more details.
 * >
 * >    You should have received a copy of the GNU Lesser General Public
 * >    License along with this library; if not, write to the Free Software
 * >    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * >    02110-1301  USA
 *
 * Please see the file LICENSE included with this software for the full text of these licenses.
 * \section libsection FOSSology C library
 * \link src/lib/ \endlink contains fossology.a and all the component files that go into this common core C library.
 * \subsection libsectionc FOSSology library for C
 * \link src/lib/c \endlink contains components for \subpage libc
 * \subsection libsectioncpp FOSSology library for CPP
 * \link src/lib/cpp \endlink contains components for \subpage libcpp
 * \subsection libsectionphp FOSSology library for PHP
 * \link src/lib/php \endlink contains components for \subpage libphp
 *
 * \section schedsection scheduler
 * You can find all the scheduler code in src/scheduler/.  But we might want to move it
 * under modules/ since there is both a backend and UI components.
 *
 * \section modsection modules
 * The UI architecture can be found at https://github.com/fossology/fossology/wiki/UI-Architecture-Overview
 *
 * \subsection subui ui/
 * Contains UI components for the module.
 * \subsection subuitests ui_tests/
 * Contains UI test components for the module.
 * \subsection subagent agent/
 * Contains actual module source.
 * \subsection subagenttests agent_tests/
 * Contains test components for the module.
 * \subsection submodconf module.conf
 * Contains configurations required by module.
 * \subsection subscripts scripts/
 */

/*!
 * \page agentlist Agents in FOSSology
 * \tableofcontents
 * -# \subpage adj2nest
 * -# \subpage buckets
 * -# \subpage compatibility
 * -# \subpage copyright
 * -# \subpage decider
 * -# \subpage deciderjob
 * -# \subpage delagent
 * -# \subpage maintagent
 * -# \subpage mimetype
 * -# \subpage nomos
 * -# \subpage ojo
 * -# \subpage pkgagent
 * -# \subpage readmeoss
 * -# \subpage reuser
 * -# \subpage scheduler
 * -# \subpage spdx2
 * -# \subpage unifiedreport
 * -# \subpage ununpack
 * -# \subpage wget_agent
 * -# \subpage softwareHeritage
 *
 * \page exampleagents Example agents to begin with
 * \tableofcontents
 * How to create new agent: [GitHub wiki](https://github.com/fossology/fossology/wiki/How-To-Create-An-Agent)
 * \section demoagents Demo agent sources
 * -# \subpage demomod
 * -# \subpage wcagent
 * -# \subpage regexscan
 *
 * \page libraries Libraries provided by FOSSology
 * \tableofcontents
 * FOSSology also provides you with libraries which can be used in developing a
 * new agent for FOSSology.
 *
 * These libraries provides with some common utility functionalities.
 * -# \subpage libc
 * -# \subpage libcpp
 * -# \subpage libphp
 */

/* General directory definitions */
/**
 * \dir Functional
 * \brief Contains functional test cases
 * \dir Unit
 * \brief Contains unit test cases
 * \dir agent
 * \brief Contains agent's source code
 * \dir agent_tests
 * \brief Contains agent's test cases
 * \dir ui
 * \brief Contains UI modules of the agent
 * \dir ui_tests
 * \brief Contains test cases for UI modules
 */
