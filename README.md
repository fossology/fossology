# FOSSology

[![Travis-CI Build Status](https://travis-ci.org/fossology/fossology.png)](https://travis-ci.org/fossology/fossology/)
[![Stories in Ready](https://badge.waffle.io/fossology/fossology.svg?label=ready&title=Ready)](http://waffle.io/fossology/fossology)
[![Coverage Status](https://coveralls.io/repos/github/fossology/fossology/badge.svg?branch=master)](https://coveralls.io/github/fossology/fossology?branch=master)

## About
FOSSology is a open source license compliance software system and toolkit.  As a toolkit you can run license, copyright
and export control scans from the command line.  As a system, a database and web ui are provided to give you a compliance
workflow.  In one click you can generate an SPDX file, or a ReadMe with all the copyrights notices from your software.
FOSSology deduplication means that you can scan an entire distro, rescan a new version, and only the changed files will 
get rescanned.  This is a big time saver for large projects.

[Check out Who Uses FOSSology!](http://www.fossology.org/projects/fossology/wiki/WhoUsesFOSSology)

FOSSology does not give legal advice.
http://fossology.org/

## Installation
FOSSology is available for  multiple versions of Linux.  There are 
installation packages for Debian, RHEL/CentOS, Ubuntu, and Fedora, and a source tarball available from the fossology.org site.  See 

  http://fossology.org/releases
  
For installation instructions see http://www.fossology.org/projects/fossology/wiki

## Docker
FOSSology comes with a Dockerfile allowing the containerized execution
both as single instance or in combination with an external PostgreSQL database.
**Note:** It is strongly recommended to use an external database for production
use, since the the standalone image does not take care of data persistency.

A pre-built Docker image is available from [Docker Hub](https://hub.docker.com/r/fossology/fossology/) and can be run using following command:
``` sh
docker run -p 8081:8081 fossology/fossology
```

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
See [Contact Us](http://www.fossology.org/projects/fossology/wiki/Contact_Us)

## Contributing

We really like contributions in several forms, see [CONTRIBUTING.md](CONTRIBUTING.md)

## License
FOSSology is licensed under [GPL-2.0](https://tldrlegal.com/license/gnu-general-public-license-v2)

## Screenshots
![Concluding a license](/examples/Concludeb.jpg)

![Browsing](/examples/Browseb.jpg)

![LicenseBrowser](/examples/LicenseBrowserb.jpg)
