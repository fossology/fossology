# Spec file for fossology for building rpm packages

# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

%define srcname PBPKG

Name:           PBPKG
Version:        PBVER
Release:        PBTAGPBSUF
License:        PBLIC
Group:          PBGRP
Url:            PBURL
Source:         PBSRC
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(id -u -n)
Requires:       fossology-web fossology-scheduler fossology-ununpack fossology-copyright fossology-buckets fossology-mimetype fossology-delagent fossology-wgetagent fossology-decider fossology-spdx2 fossology-reuser
#Recommends:		fossology-decider, fossology-spdx2, fossology-reuser, fossology-ninka
BuildRequires:  postgresql-devel >= 8.1.11,glib2-devel,libxml2,gcc,make,perl,rpm-devel,pcre-devel,openssl-devel,gcc-c++,php,boost-devel,php-phar,php-mbstring,php-xml,curl,jsoncpp-devel,libgomp-devel,json-c-devel,PBBUILDDEP
Summary:        FOSSology is a license compliance analysis  tool

#
# package
#

%package common
Requires:       php >= 5.1.6,php-pear >= 5.16,php-pgsql >= 5.1.6,php-process,php-mbstring,PBDEP
Summary:        Architecture for analyzing software, common files
Group:          PBGRP

%package web
Requires:       fossology-common,fossology-db,fossology-decider
Summary:        Architecture for analyzing software, web interface
Group:          PBGRP

%package db
Requires:       postgresql >= 8.1.11,postgresql-server >= 8.1.11
Summary:        Architecture for analyzing software, database
Group:          PBGRP

%package ununpack
Requires:       fossology-common,libxml2,binutils,bzip2,cpio,mkisofs,poppler-utils,rpm,tar,unzip,gzip,p7zip-plugins,perl,file,which
PBREC
Summary:        Architecture for analyzing software, ununpack and adj2nest
Group:          PBGRP

%package scheduler
Requires:       fossology-common
Summary:        Architecture for analyzing software, scheduler
Group:          PBGRP

%package copyright
Requires:       fossology-common,pcre
Summary:        Architecture for analyzing software, copyright
Group:          PBGRP

%package buckets
Requires:       fossology-nomos,fossology-pkgagent
Summary:        Architecture for analyzing software, buckets
Group:          PBGRP

%package mimetype
Requires:       fossology-common,file-libs
Summary:        Architecture for analyzing software, mimetype
Group:          PBGRP

%package nomos
Requires:       fossology-common
Summary:        Architecture for analyzing software, nomos
Group:          PBGRP

%package pkgagent
Requires:       fossology-common,rpm
Summary:        Architecture for analyzing software, pkgagent
Group:          PBGRP

%package delagent
Requires:       fossology-common
Summary:        Architecture for analyzing software, delagent
Group:          PBGRP

%package wgetagent
Requires:       fossology-common,wget,subversion,git
Summary:        Architecture for analyzing software, wget_agent
Group:          PBGRP

%package debug
Requires:       fossology-web
Summary:        Architecture for analyzing software, debug
Group:          PBGRP

%package spdx2
Requires:       fossology-web
Summary:        SPDX and DEP5 extensions
Group:          PBGRP

%package ninka
Requires:       fossology-common
#Recommends:     ninka >=1.2
Summary:        Architecture for analyzing software, Ninka
Group:          PBGRP

%package decider
Requires:       fossology-common
Summary:        Architecture for analyzing software, decider
Group:          PBGRP

%package deciderjob
Requires:       fossology-common
Summary:        Architecture for analyzing software, deciderjob
Group:          PBGRP

%package reuser
Requires:       fossology-common
Summary:        Architecture for reusing clearing result of other uploads, reuser
Group:          PBGRP

%package monk
Requires:       fossology-common
Summary:        Architecture for reusing clearing result of other uploads, monk
Group:          PBGRP

%package monkbulk
Requires:       fossology-common
Summary:        Architecture for reusing clearing result of other uploads, monkbulk
Group:          PBGRP

%package unifiedreport
Requires:       fossology-common
Summary:        Unified report Agent
Group:          PBGRP

%package reportimport
Requires:       fossology-common
Summary:        Report Import Agent
Group:          PBGRP

#
# description
#

%description
PBDESC

%description common
This package contains the resources needed by all of the other
fossology components.

%description db
This package contains the database resources and will create a
fossology database on the system (and requires that postgresql is
running at install time). If you prefer to use a remote database,
or want to create the database yourself, do not install this package
and consult the README.Debian file included in the fossology-common
package.

%description web
This package depends on the packages for the web interface.

%description scheduler
This package contains the scheduler daemon.

%description ununpack
This package contains the ununpack and adj2nest agent programs and their resources.

%description wgetagent
This package contains the wget agent agent programs and their resources.

%description buckets
This package contains the buckets agent programs and their resources.

%description nomos
This package contains the nomos agent programs and their resources.

%description copyright
This package contains the copyright agent programs and their resources.

%description mimetype
This package contains the mimetype agent programs and their resources.

%description pkgagent
This package contains the pkgagent agent programs and their resources.

%description delagent
This package contains the delagent agent programs and their resources.

%description debug
This package contains the debug UI.

%description spdx2
This package contains the SPDX v2 agent programs and their resources.

%description ninka
This package contains the ninka wrapper agent programs and their resources.

%description decider
This package contains the decider agent programs and their resources.

%description deciderjob
This package contains the deciderjob agent programs and their resources.

%description reuser
This package contains the reuser agent programs and their resources.

%description monk
This package contains the monk agent programs and their resources.

%description monkbulk
This package contains the monkbulk agent programs and their resources.

%description unifiedreport
This package contains the unified report agent programs and their resources.

%description reportimport
This package contains the report import agent programs and their resources.

#
# prep
#

%prep
%setup -q -n %{name}-%{version}PBEXTDIR
#PBPATCHCMD

mkdir -p $RPM_BUILD_DIR/composer/
utils/install_composer.sh $RPM_BUILD_DIR/composer/
# make DESTDIR=$RPM_BUILD_ROOT PREFIX=%{_usr} SYSCONFDIR=%{_sysconfdir}/fossology LOCALSTATEDIR=%{_var} LIBDIR=%{_libdir} composer_install

#
# build
#

%build
make SYSCONFDIR=%{_sysconfdir}/fossology PREFIX=%{_usr} LOCALSTATEDIR=%{_var}
#make %{?_smp_mflags} SYSCONFDIR=%{_sysconfdir}
make SYSCONFDIR=%{_sysconfdir}/fossology PREFIX=%{_usr} LOCALSTATEDIR=%{_var} -C src/nomos/agent/ -f Makefile.sa

#
# install
#

%install
export COMPOSER_PHAR=$RPM_BUILD_DIR/composer/composer
make DESTDIR=$RPM_BUILD_ROOT PREFIX=%{_usr} SYSCONFDIR=%{_sysconfdir}/fossology LOCALSTATEDIR=%{_var} LIBDIR=%{_libdir} install_offline
make DESTDIR=$RPM_BUILD_ROOT PREFIX=%{_usr} SYSCONFDIR=%{_sysconfdir}/fossology LOCALSTATEDIR=%{_var} LIBDIR=%{_libdir} -C install/ -f Makefile install
make DESTDIR=$RPM_BUILD_ROOT PREFIX=%{_usr} SYSCONFDIR=%{_sysconfdir}/fossology LOCALSTATEDIR=%{_var} LIBDIR=%{_libdir} -C src/nomos/agent/ -f Makefile.sa install
make DESTDIR=$RPM_BUILD_ROOT PREFIX=%{_usr} SYSCONFDIR=%{_sysconfdir}/fossology LOCALSTATEDIR=%{_var} LIBDIR=%{_libdir} composer_install

# emulating writing composer file, TODO see if that is really needed now
cp src/composer.json $RPM_BUILD_ROOT%{_usr}/share/PBPROJ
cp src/composer.lock $RPM_BUILD_ROOT%{_usr}/share/PBPROJ
#cp $RPM_BUILD_ROOT%fossology/src/vendor $RPM_BUILD_ROOT%{_usr}/share/PBPROJ

#mkdir -p $RPM_BUILD_ROOT/%{_sysconfdir}/httpd/conf.d
#cat > $RPM_BUILD_ROOT/%{_sysconfdir}/httpd/conf.d/PBPROJ.conf << EOF
#Alias /repo/ /usr/share/PBPROJ/www/
#<Directory "/usr/share/PBPROJ/www">
#	AllowOverride None
#	Options FollowSymLinks MultiViews
#	Order allow,deny
#	Allow from all
#	# uncomment to turn on php error reporting
#	#php_flag display_errors on
#	#php_value error_reporting 2039
#</Directory>
#EOF
cp utils/fo-cleanold $RPM_BUILD_ROOT/%{_usr}/lib/PBPROJ/
cp install/scripts/php-conf-fix.sh $RPM_BUILD_ROOT/%{_usr}/lib/PBPROJ/

# manually add the version file
cp VERSION $RPM_BUILD_ROOT%{_sysconfdir}/PBPROJ/

#rm -f $RPM_BUILD_ROOT/%{_sysconfdir}/default/PBPROJ

#
# clean
#

%clean
%{__rm} -rf $RPM_BUILD_ROOT

#
# files
#

%files
%defattr(-,root,root)
# %doc ChangeLog # mcj todo not existing anymore, right?
%doc install/INSTALL install/INSTALL.multi NOTICES* *.md

%files common
%defattr(-,root,root)
%config(noreplace) %{_sysconfdir}/cron.d/*
%config(noreplace) %{_sysconfdir}/PBPROJ/*
%dir %{_sysconfdir}/PBPROJ/conf
%config(noreplace) %{_sysconfdir}/PBPROJ/conf/*
%dir %{_sysconfdir}/PBPROJ/mods-enabled
%dir %{_usr}/lib/PBPROJ
%dir %{_datadir}/PBPROJ
%{_usr}/lib/PBPROJ/*
%{_datadir}/PBPROJ/lib/*
%{_datadir}/PBPROJ/VERSION
%{_bindir}/*
%{_includedir}/*
%{_mandir}/man1/*
%{_sysconfdir}/PBPROJ/mods-enabled/maintagent
%{_datadir}/PBPROJ/maintagent/*
%{_datadir}/PBPROJ/composer.json
%{_datadir}/PBPROJ/composer.lock
%{_datadir}/PBPROJ/vendor/*
%{_datadir}/PBPROJ/keyword/*
%{_datadir}/PBPROJ/ojo/*

%files unifiedreport
%{_sysconfdir}/PBPROJ/mods-enabled/unifiedreport
%{_datadir}/PBPROJ/unifiedreport/*
%{_datadir}/PBPROJ/unifiedreport/ui/*
%{_datadir}/PBPROJ/unifiedreport/agent/*

%files reportimport
%{_sysconfdir}/PBPROJ/mods-enabled/reportImport
%{_datadir}/PBPROJ/reportImport/*

%files db
%defattr(-,root,root)
%dir %{_usr}/lib/PBPROJ
%{_usr}/lib/PBPROJ/*

%files web
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/www
%{_sysconfdir}/PBPROJ/mods-enabled/www
%{_datadir}/PBPROJ/www/*
%{_sysconfdir}/PBPROJ/mods-enabled/www-page
%{_sysconfdir}/PBPROJ/mods-enabled/www-async
%{_sysconfdir}/PBPROJ/mods-enabled/readmeoss
%{_datadir}/PBPROJ/readmeoss/*

%files ninka
%defattr(-,root,root)
%{_sysconfdir}/PBPROJ/mods-enabled/ninka
%{_datadir}/PBPROJ/ninka/*

%files decider
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/decider
%{_sysconfdir}/PBPROJ/mods-enabled/decider
%{_datadir}/PBPROJ/decider/*

%files deciderjob
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/deciderjob
%{_sysconfdir}/PBPROJ/mods-enabled/deciderjob
%{_datadir}/PBPROJ/deciderjob/*

%files reuser
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/reuser
%{_sysconfdir}/PBPROJ/mods-enabled/reuser
%{_datadir}/PBPROJ/reuser/*

%files monk
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/monk
%{_sysconfdir}/PBPROJ/mods-enabled/monk
%{_datadir}/PBPROJ/monk/*

%files monkbulk
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/monkbulk
%{_sysconfdir}/PBPROJ/mods-enabled/monkbulk
%{_datadir}/PBPROJ/monkbulk/*

%files scheduler
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/scheduler
%{_sysconfdir}/PBPROJ/mods-enabled/scheduler
%{_sysconfdir}/init.d/*
%{_datadir}/PBPROJ/scheduler/*

%files ununpack
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/ununpack
%{_sysconfdir}/PBPROJ/mods-enabled/ununpack
%{_sysconfdir}/PBPROJ/mods-enabled/adj2nest
%{_datadir}/PBPROJ/ununpack/*
%{_datadir}/PBPROJ/adj2nest/*
%{_bindir}/departition

%files wgetagent
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/wget_agent
%{_sysconfdir}/PBPROJ/mods-enabled/wget_agent
%{_datadir}/PBPROJ/wget_agent/*

%files copyright
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/copyright
%{_sysconfdir}/PBPROJ/mods-enabled/copyright
%{_sysconfdir}/PBPROJ/mods-enabled/ecc
%{_datadir}/PBPROJ/copyright/*
%{_datadir}/PBPROJ/ecc/*

%files buckets
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/buckets
%{_sysconfdir}/PBPROJ/mods-enabled/buckets
%{_datadir}/PBPROJ/buckets/*

%files nomos
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/nomos
%{_sysconfdir}/PBPROJ/mods-enabled/nomos
%{_datadir}/PBPROJ/nomos/*

%files mimetype
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/mimetype
%{_sysconfdir}/PBPROJ/mods-enabled/mimetype
%{_datadir}/PBPROJ/mimetype/*

%files pkgagent
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/pkgagent
%{_sysconfdir}/PBPROJ/mods-enabled/pkgagent
%{_datadir}/PBPROJ/pkgagent/*

%files delagent
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/delagent
%{_sysconfdir}/PBPROJ/mods-enabled/delagent
%{_datadir}/PBPROJ/delagent/*

%files debug
%defattr(-,root,root)
%dir %{_datadir}/PBPROJ/debug
%{_sysconfdir}/PBPROJ/mods-enabled/debug
%{_datadir}/PBPROJ/debug/*

%files spdx2
%defattr(-,root,root)
%{_sysconfdir}/PBPROJ/mods-enabled/spdx2
%{_sysconfdir}/PBPROJ/mods-enabled/dep5
%{_sysconfdir}/PBPROJ/mods-enabled/spdx2tv
%{_sysconfdir}/PBPROJ/mods-enabled/spdx2csv
%{_datadir}/PBPROJ/spdx2/*
%{_datadir}/PBPROJ/dep5/*
%{_datadir}/PBPROJ/spdx2tv/*
%{_datadir}/PBPROJ/spdx2csv/*

#
# post
#

%post common
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --common

%post db
# Check postgresql is running
LANGUAGE=C service postgresql status 2>&1 | grep -q PBSTOP
if [ $? -eq 0 ]; then
	PBFEDORAD
	service postgresql start
fi
#chkconfig --add postgresql

grep -q FOSSology /var/lib/pgsql/data/pg_hba.conf
if [ $? -ne 0 ]; then
	# We suppose that we use the local postgresql installed on the same machine.
	cat >> /var/lib/pgsql/data/pg_hba.conf << EOF
# Added for FOSSology connection
# Local connections
local   all         all                               md5
# IPv4 local connections:
host    all         all         127.0.0.1/32          md5
host	all	    all		::1/128		      md5
EOF
PBPGHBA
PBPGHBB
perl -pi -e 's|local\s+all\s+all\s+peer|local all postgres peer|' /var/lib/pgsql/data/pg_hba.conf
fi

# Now restart again postgresql
# We have do it here in order to let postgresql configure itself correctly
# in case it wasn't already installed
service postgresql restart
/usr/lib/PBPROJ/dbcreate

%post web
# Link apache config for fossology
ln -s %{_sysconfdir}/PBPROJ/conf/fo-apache.conf  %{_sysconfdir}/httpd/conf.d/PBPROJ.conf
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --web-only

# fix php config for fixing the time zone, TODO does not work reliably with cent
/usr/lib/PBPROJ/php-conf-fix.sh --overwrite

# httpd is also assumed to run locally
LANGUAGE=C service httpd status 2>&1 | grep -q PBSTOP
if [ $? -eq 0 ]; then
	service httpd start
else
	service httpd reload
fi
#chkconfig --add httpd

%post scheduler
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --scheduler-only

%post
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

#
# post: agent independency ops
#

%post ununpack
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post copyright
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post buckets
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post mimetype
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post nomos
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post pkgagent
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post delagent
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post wgetagent
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post spdx2
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post decider
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post deciderjob
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post reuser
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post monk
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent

%post monkbulk
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall --agent


chkconfig --add PBPROJ
/etc/init.d/PBPROJ start

#
# preun
#

%preun
if [ $1 -eq 0 ]; then
  # If FOSSology is running, stop it before removing.
  /etc/init.d/PBPROJ stop
  chkconfig --del PBPROJ 2>&1 > /dev/null

  # We should do some cleanup here (fossy account ...)
  /usr/lib/PBPROJ/fo-cleanold
fi

#
# postun
#

%postun scheduler
if [ $1 -eq 0 ]; then
  # cleanup logs
  if [ -e /var/log/PBPROJ ]; then
    rm -rf /var/log/PBPROJ || echo "ERROR: could not remove /var/log/fossology"
  fi
fi

%postun common
if [ $1 -eq 0 ]; then
  echo "removing FOSSology user..."
  userdel --system fossy || true
  echo "removing FOSSology group..."
  groupdel fossy || true
  # remove the conf directory
  echo "removing the conf directory..."
  if [ -e /etc/PBPROJ ]; then
    rm -rf /etc/PBPROJ || echo "ERROR: could not remove FOSSology conf directory"
  fi
  # remove the data directory
  echo "removing the data directory..."
  if [ -e /usr/share/PBPROJ ]; then
    rm -rf /usr/share/PBPROJ || echo "ERROR: could not remove FOSSology data directory"
  fi
  # remove the repository directory
  echo "removing the FOSSology repository..."
  if [ -e /srv/PBPROJ ]; then
    rm -rf /srv/PBPROJ || echo "ERROR: could not remove FOSSology repository"
  fi
fi

#
# changelog
#

%changelog
PBLOG

#
# end
#
