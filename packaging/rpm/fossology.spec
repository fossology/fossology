#
# $Id$
#

Name:           fossology
Version:        2.0.0-devel
Release:        1.el6
License:        GPLv2
Group:          Applications/Engineering
Url:            http://www.fossology.org
Source:         http://sourceforge.net/projects/fossology/files/fossology/%{name}-%{version}.tar.gz
#PBPATCHSRC
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(id -u -n)
Requires:       fossology-web fossology-db fossology-scheduler fossology-ununpack fossology-copyright fossology-buckets fossology-mimetype fossology-delagent fossology-wgetagent
BuildRequires:  postgresql-devel >= 8.1.11 libxml2 gcc make perl rpm-devel pcre-devel perl-Text-Template subversion file
Summary:        FOSSology is a licenses exploration tool

%package common
Requires:       php >= 5.1.6 php-pear >= 5.16 php-pgsql >= 5.1.6 php-process
Summary:        Architecture for analyzing software, common files 
Group:          Applications/Engineering

%package web
Requires:       fossology-common httpd
Summary:        Architecture for analyzing software, web interface
Group:          Applications/Engineering

%package db
Requires:       postgresql >= 8.1.11 postgresql-server >= 8.1.11
Summary:        Architecture for analyzing software, database
Group:          Applications/Engineering

%package ununpack
Requires:       libxml2 binutils bzip2 cpio mkisofs poppler-utils rpm tar unzip gzip p7zip-plugins perl file which
Summary:        Architecture for analyzing software, ununpack and adj2nest
Group:          Applications/Engineering

%package scheduler
Requires:       fossology-common
Summary:        Architecture for analyzing software, scheduler
Group:          Applications/Engineering

%package copyright
Requires:       fossology-common pcre
Summary:        Architecture for analyzing software, copyright
Group:          Applications/Engineering

%package buckets
Requires:       fossology-nomos fossology-pkgagent
Summary:        Architecture for analyzing software, buckets
Group:          Applications/Engineering

%package mimetype
Requires:       fossology-common file-libs
Summary:        Architecture for analyzing software, mimetype
Group:          Applications/Engineering

%package nomos
Requires:       fossology-common
Summary:        Architecture for analyzing software, nomos
Group:          Applications/Engineering

%package pkgagent
Requires:       fossology-common rpm
Summary:        Architecture for analyzing software, pkgagent
Group:          Applications/Engineering

%package delagent
Requires:       fossology-common
Summary:        Architecture for analyzing software, delagent
Group:          Applications/Engineering

%package wgetagent
Requires:       fossology-common wget
Summary:        Architecture for analyzing software, wget_agent
Group:          Applications/Engineering

%description
An open and modular architecture for analyzing software. Currently specializing on license detection.

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

%prep
%setup -q

%build
make SYSCONFDIR=%{_sysconfdir}/fossology PREFIX=%{_usr} LOCALSTATEDIR=%{_var}

%install
%{__rm} -rf $RPM_BUILD_ROOT
make DESTDIR=$RPM_BUILD_ROOT PREFIX=%{_usr} SYSCONFDIR=%{_sysconfdir}/fossology LOCALSTATEDIR=%{_var} LIBDIR=%{_libdir} install

%clean
%{__rm} -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root)
#%doc ChangeLog
%doc COPYING COPYING.LGPL HACKING README install/INSTALL install/INSTALL.multi LICENSE

%files common
%defattr(-,root,root)
%config(noreplace) %{_sysconfdir}/cron.d/*
%config(noreplace) %{_sysconfdir}/fossology/Db.conf
%config(noreplace) %{_sysconfdir}/fossology/fossology.conf
%config(noreplace) %{_sysconfdir}/fossology/VERSION
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_usr}/lib/fossology
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/debug
%{_usr}/lib/fossology/*
%{_datadir}/fossology/lib/*
%{_datadir}/fossology/debug/*
%{_bindir}/*
%{_includedir}/*
%{_mandir}/man1/*

%files db
%defattr(-,root,root)
%dir %{_usr}/lib/fossology
%{_usr}/lib/fossology/*

%files web
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/www
%{_datadir}/fossology/www/*

%files scheduler
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/scheduler
%{_sysconfdir}/init.d/*
%{_datadir}/fossology/scheduler/*

%files ununpack
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/ununpack
%{_sysconfdir}/fossology/mods-enabled/adj2nest
%{_datadir}/fossology/ununpack/*
%{_datadir}/fossology/adj2nest/*
%{_bindir}/departition

%files wgetagent
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/wget_agent
%{_datadir}/fossology/wget_agent/*

%files copyright
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/copyright
%{_datadir}/fossology/copyright/*

%files buckets
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/buckets
%{_datadir}/fossology/buckets/*

%files nomos
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/nomos
%{_datadir}/fossology/nomos/*

%files mimetype
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/mimetype
%{_datadir}/fossology/mimetype/*

%files pkgagent
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/pkgagent
%{_datadir}/fossology/pkgagent/*

%files delagent
%defattr(-,root,root)
%dir %{_sysconfdir}/fossology/mods-enabled
%dir %{_datadir}/fossology
%{_sysconfdir}/fossology/mods-enabled/delagent
%{_datadir}/fossology/delagent/*

%post db
# Check postgresql is running
LANGUAGE=C /etc/init.d/postgresql status 2>&1 | grep -q stop
if [ $? -eq 0 ]; then
	/etc/init.d/postgresql initdb
	/etc/init.d/postgresql start
fi
chkconfig --add postgresql

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
perl -pi -e 's|(host\s+all\s+all\s+127.0.0.1/32\s+ident\s+sameuser)|#$1|' /var/lib/pgsql/data/pg_hba.conf
perl -pi -e 's|(host\s+all\s+all\s+::1/128\s+ident)|#$1|' /var/lib/pgsql/data/pg_hba.conf
fi

# Now restart again postgresql
# We have do it here in order to let postgresql configure itself correctly
# in case it wasn't already installed
/etc/init.d/postgresql restart
%{_usr}/lib/fossology/dbcreate

%post web
# Adjust PHP config (described in detail in section 2.1.5)
grep -qw allow_call_time_pass_reference /etc/php.ini
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*allow_call_time_pass_reference.*=.*/allow_call_time_pass_reference = On/" /etc/php.ini
else
	echo "allow_call_time_pass_reference = On" >> /etc/php.ini
fi

# Add apache config for fossology (described in detail in section 2.1.6) - done in install
# Run the postinstall script
/usr/lib/fossology/fo-postinstall --web

# Adds user httpd to fossy group
#useradd -G fossy httpd
#perl -pi -e 's/^fossy:x:([0-9]+):/fossy:x:$1:httpd/' /etc/group

# httpd is also assumed to run locally
LANGUAGE=C /etc/init.d/httpd status 2>&1 | grep -q stop
if [ $? -eq 0 ]; then
	/etc/init.d/httpd start
else
	/etc/init.d/httpd reload
fi
chkconfig --add httpd

%post scheduler
# Run the postinstall script
/usr/lib/fossology/fo-postinstall --scheduler

%post
chkconfig --add fossology
/etc/init.d/fossology start

%preun
# If FOSSology is running, stop it before removing.
/etc/init.d/fossology stop
chkconfig --del fossology 2>&1 > /dev/null

# We should do some cleanup here (fossy account ...)
#/usr/lib/fossology/fo-cleanold

%changelog

