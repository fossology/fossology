#
# $Id$
#

Name:           PBREALPKG
Version:        PBVER
Release:        PBTAGPBSUF
License:        PBLIC
Group:          PBGRP
Url:            PBURL
Source:         PBREPO/PBSRC
#PBPATCHSRC
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(id -u -n)
Requires:       postgresql >= 8.1.11 php >= 5.1.6 php-pear >= 5.16 php-pgsql >= 5.1.6 libxml2 binutils bzip2 cpio mkisofs poppler-utils rpm tar unzip gzip p7zip httpd which PBDEP
BuildRequires:  postgresql-devel >= 8.1.11 libxml2 gcc make perl rpm-devel Pyrex python python-devel PBBUILDDEP
Summary:        FOSSology is a licenses exploration tool
Summary(fr):    FOSSology est un outil d'exploration de licenses

%package devel
Summary:        Devel part of FOSSology (a licenses exploration tool)
Summary(fr):    Partie dedévelopment de FOSSology, outil d'exploration de licenses
Group:          PBGRP

%description
PBDESC

%description -l fr
FOSSology est un outil d'exploration de licenses

%description devel
Devel part.
PBDESC

%description -l fr devel
Partie développement de FOSSology, outil d'exploration de licenses

%prep
%setup -q
#PBPATCHCMD

%build
make SYSCONFDIR=%{_sysconfdir} PREFIX=%{_usr} LOCALSTATEDIR=%{_var}
#make %{?_smp_mflags} SYSCONFDIR=%{_sysconfdir}

%install
%{__rm} -rf $RPM_BUILD_ROOT
make DESTDIR=$RPM_BUILD_ROOT PREFIX=%{_usr} SYSCONFDIR=%{_sysconfdir} LOCALSTATEDIR=%{_var} LIBDIR=%{_libdir} install
mkdir -p $RPM_BUILD_ROOT/%{_sysconfdir}/httpd/conf.d
cat > $RPM_BUILD_ROOT/%{_sysconfdir}/httpd/conf.d/PBPROJ.conf << EOF
Alias /repo/ /usr/share/PBPROJ/www/
<Directory "/usr/share/PBPROJ/www">
	AllowOverride None
	Options FollowSymLinks MultiViews
	Order allow,deny
	Allow from all
	# uncomment to turn on php error reporting 
	#php_flag display_errors on
	#php_value error_reporting 2039
</Directory>
EOF
cp utils/fo-cleanold $RPM_BUILD_ROOT/%{_usr}/lib/PBPROJ/

#rm -f $RPM_BUILD_ROOT/%{_sysconfdir}/default/PBPROJ

%clean
%{__rm} -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root)
%doc ChangeLog
%doc COPYING COPYING.LGPL HACKING README INSTALL INSTALL.multi LICENSE 
#AUTHORS NEWS
%config(noreplace) %{_sysconfdir}/httpd/conf.d/*.conf
%config(noreplace) %{_sysconfdir}/cron.d/*
%config(noreplace) %{_sysconfdir}/PBPROJ/*
%dir %{_sysconfdir}/PBPROJ
%dir %{_usr}/lib/PBPROJ
%dir %{_datadir}/PBPROJ
%{_sysconfdir}/init.d/*
%{_usr}/lib/PBPROJ/*
%{_datadir}/PBPROJ/*
%{_bindir}/*
%{_mandir}/man1/*

%files devel
%{_includedir}/*
%{_libdir}/*.a

%post
# If upgrade from 1.1, rename 1.1 Scheduler.conf file
if [ $1 -eq  2 ]; then
	# If FOSSology is running, stop it before upgrade
	/etc/init.d/PBPROJ stop
	if [ -f "%{_sysconfdir}/PBPROJ/Scheduler.conf" -o -L "%{_sysconfdir}/PBPROJ/Scheduler.conf" ]; then
		echo "NOTE: found old Scheduler.conf, saving to Scheduler.conf.fo_1.1;"
		echo "  Create new default Scheduler.conf;"
		echo "  Please check that is it correct for your enviroment or"
		echo "  Create a different one with mkschedconf."
		cp %{_sysconfdir}/PBPROJ/Scheduler.conf %{_sysconfdir}/PBPROJ/Scheduler.conf.fo_1.1
		rm -f %{_sysconfdir}/PBPROJ/Scheduler.conf
	fi
fi

# Check postgresql is running
LANGUAGE=C /etc/init.d/postgresql status 2>&1 | grep -q stop
if [ $? -eq 0 ]; then
	/etc/init.d/postgresql start
fi
chkconfig --add postgresql

# We suppose that we use the local postgresql installed on the same machine.
cat >> /var/lib/pgsql/data/pg_hba.conf << EOF
# Added for FOSSology connection
# Local connections
local   all         all                               md5
# IPv4 local connections:
host    all         all         127.0.0.1/32          md5

EOF
perl -pi -e 's|(host\s+all\s+all\s+127.0.0.1/32\s+ident\s+sameuser)|#$1|' /var/lib/pgsql/data/pg_hba.conf

# Now restart again postgresql
# We have do it here in order to let postgresql configure itself correctly
# in case it wasn't already installed
/etc/init.d/postgresql restart

# Adjust PHP config (described in detail in section 2.1.5)
grep -qw allow_call_time_pass_reference PBPHPINI
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*allow_call_time_pass_reference.*=.*/allow_call_time_pass_reference = On/" PBPHPINI
else
	echo "allow_call_time_pass_reference = On" >> PBPHPINI
fi

# Add apache config for fossology (described in detail in section 2.1.6) - done in install
# Run the postinstall script
/usr/lib/PBPROJ/fo-postinstall

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

# Create logfile to avoid issues later on
#touch %{_var}/log/PBPROJ
# Handle logfile owner correctly
#chown fossy:fossy %{_var}/log/PBPROJ
# Test that things are installed correctly
/usr/lib/PBPROJ/fossology-scheduler -t
if [ $? -ne 0 ]; then
	exit -1
fi

chkconfig --add PBPROJ
/etc/init.d/PBPROJ start

%preun
# If FOSSology is running, stop it before removing.
/etc/init.d/PBPROJ stop
chkconfig --del PBPROJ 2>&1 > /dev/null

# We should do some cleanup here (fossy account ...)
/usr/lib/PBPROJ/fo-cleanold

%changelog
PBLOG
