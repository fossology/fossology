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
Requires:       postgresql php php-pear php-pgsql libxml2 binutils bzip2 cpio mkisofs poppler-utils rpm tar unzip gzip httpd which PBDEP
BuildRequires:  postgresql-devel libxml2 gcc make perl PBBUILDDEP
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
# Adjust the kernel shmmax (described in detail in section 2.1.3)
echo 512000000 > /proc/sys/kernel/shmmax
grep -q kernel.shmmax /etc/sysctl.conf
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*kernel.shmmax\s*=.*/kernel.shmmax=512000000/" /etc/sysctl.conf
else
	echo "kernel.shmmax=512000000" >> /etc/sysctl.conf
fi

# Adjust postgresql config (described in detail in section 2.1.4) for Fossology
PGCONF=/var/lib/pgsql/data/postgresql.conf
if [ ! -f $PGCONF ]; then
	cp /usr/share/pgsql/postgresql.conf.sample $PGCONF
fi
grep -qw listen_addresses $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*listen_addresses.*=.*/listen_addresses = '*'/" $PGCONF
else
	echo "listen_addresses = '*'" >> $PGCONF
fi
grep -qw max_connections $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*max_connections.*=.*/max_connections = 50/" $PGCONF
else
	echo "max_connections = 50" >> $PGCONF
fi
grep -qw shared_buffers $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*shared_buffers.*=.*/shared_buffers = 32768/" $PGCONF
else
	echo "shared_buffers = 32768" >> $PGCONF
fi
grep -qw work_mem $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*work_mem.*=.*/work_mem = 10240/" $PGCONF
else
	echo "work_mem = 10240" >> $PGCONF
fi
# min max_fsm_relations*16, 6 bytes each
grep -qw max_fsm_pages $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*max_fsm_pages.*=.*/max_fsm_pages = 100000/" $PGCONF
else
	echo "max_fsm_pages = 100000" >> $PGCONF
fi
grep -qw fsync $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*fsync.*=.*/fsync = off/" $PGCONF
else
	echo "fsync = off" >> $PGCONF
fi
# recover from partial page writes
grep -qw full_page_writes $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*full_page_writes.*=.*/full_page_writes = off/" $PGCONF
else
	echo "full_page_writes = off" >> $PGCONF
fi
grep -qw commit_delay $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*commit_delay.*=.*/commit_delay = 1000/" $PGCONF
else
	echo "commit_delay = 1000" >> $PGCONF
fi
grep -qw effective_cache_size $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*effective_cache_size.*=.*/effective_cache_size = 25000/" $PGCONF
else
	echo "effective_cache_size = 25000" >> $PGCONF
fi
# -1 is disabled, 0 logs all statements
grep -qw log_min_duration_statement $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*log_min_duration_statement.*=.*/log_min_duration_statement = -1/" $PGCONF
else
	echo "log_min_duration_statement = -1" >> $PGCONF
fi
# prepend a time stamp to all log entries
grep -qw log_line_prefix $PGCONF
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*log_line_prefix.*=.*/log_line_prefix = '%t %h %c'/" $PGCONF
else
	echo "log_line_prefix = '%t %h %c'" >> $PGCONF
fi

cat >> /var/lib/pgsql/data/pg_hba.conf << EOF
# Added for FOSSology connection
# Local connections
local   all         all                               md5
# IPv4 local connections:
host    all         all         127.0.0.1/32          md5
host    all         all         127.0.0.1/32          md5
# IPv6 local connections:
host    all         all         ::1/128               ident sameuser

EOF
perl -pi -e 's|(host\s+all\s+all\s+127.0.0.1/32\s+ident\s+sameuser)|#$1|' /var/lib/pgsql/data/pg_hba.conf

# Check postgresql is running
LANGUAGE=C /etc/init.d/postgresql status 2>&1 | grep -q stop
if [ $? -eq 0 ]; then
	/etc/init.d/postgresql start
else
	# Reload doesn't seem to work here
	/etc/init.d/postgresql restart
fi
chkconfig --add postgresql

# To avoid fossology setup to fail, leave it time to start
sleep 2 

# Adjust PHP config (described in detail in section 2.1.5)
grep -qw memory_limit PBPHPINI
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*memory_limit.*=.*/memory_limit = 702M/" PBPHPINI
else
	echo "memory_limit = 702M" >> PBPHPINI
fi
grep -qw post_max_size PBPHPINI
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*post_max_size.*=.*/post_max_size = 702M/" PBPHPINI
else
	echo "post_max_size = 702M" >> PBPHPINI
fi
grep -qw upload_max_filesize PBPHPINI
if [ $? -eq 0 ]; then
	perl -pi -e "s/^[#\s]*upload_max_filesize.*=.*/upload_max_filesize = 702M/" PBPHPINI
else
	echo "upload_max_filesize = 702M" >> PBPHPINI
fi
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
perl -pi -e 's/^fossy:x:([0-9]+):/fossy:x:$1:httpd/' /etc/group

# httpd
LANGUAGE=C /etc/init.d/httpd status 2>&1 | grep -q stop
if [ $? -eq 0 ]; then
	/etc/init.d/httpd start
else
	/etc/init.d/httpd reload
fi
chkconfig --add httpd

# Create logfile to avoid issues later on
touch %{_var}/log/PBPROJ
# Handle logfile owner correctly
chown fossy:fossy %{_var}/log/PBPROJ

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
