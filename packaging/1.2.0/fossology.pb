#
# Project Builder configuration file
# For project fossology
#
# $Id$
#

#
# What is the project URL
#
#pburl fossology = svn://svn.fossology.org/fossology/devel
#pburl fossology = svn://svn+ssh.fossology.org/fossology/devel
#pburl fossology = cvs://cvs.fossology.org/fossology/devel
pburl fossology = svn+https://fossology.svn.sourceforge.net/svnroot/fossology/tags/1.2.0/
#pburl fossology = ftp://ftp.fossology.org/src/fossology-devel.tar.gz
#pburl fossology = file:///src/fossology-devel.tar.gz
#pburl fossology = dir:///src/fossology-devel

# Repository
pbrepo fossology = ftp://bl480c-1.test
#pbml fossology = fossology-announce@lists.fossology.org
#pbsmtp fossology = localhost

# Check whether project is well formed 
# when downloading from ftp/http/...
# (containing already a directory with the project-version name)
#pbwf fossology = 1

#
# Packager label
#
pbpackager fossology = Dong Ma <vincent@fossology.org>
#

# For delivery to a machine by SSH (potentially the FTP server)
# Needs hostname, account and directory
#
sshhost fossology = bl480c-1.test
sshlogin fossology = build
sshdir fossology = /home/build
sshport fossology = 22

#
# For Virtual machines management
# Naming convention to follow: distribution name (as per ProjectBuilder::Distribution)
# followed by '-' and by release number
# followed by '-' and by architecture
# a .vmtype extension will be added to the resulting string
# a QEMU rhel-3-i286 here means that the VM will be named rhel-3-i386.qemu
#
#vmlist fossology = mandrake-10.1-i386,mandrake-10.2-i386,mandriva-2006.0-i386,mandriva-2007.0-i386,mandriva-2007.1-i386,mandriva-2008.0-i386,redhat-7.3-i386,redhat-9-i386,fedora-4-i386,fedora-5-i386,fedora-6-i386,fedora-7-i386,fedora-8-i386,rhel-3-i386,rhel-4-i386,rhel-5-i386,suse-10.0-i386,suse-10.1-i386,suse-10.2-i386,suse-10.3-i386,sles-9-i386,sles-10-i386,gentoo-nover-i386,debian-3.1-i386,debian-4.0-i386,ubuntu-6.06-i386,ubuntu-7.04-i386,ubuntu-7.10-i386,mandriva-2007.0-x86_64,mandriva-2007.1-x86_64,mandriva-2008.0-x86_64,fedora-6-x86_64,fedora-7-x86_64,fedora-8-x86_64,rhel-4-x86_64,rhel-5-x86_64,suse-10.2-x86_64,suse-10.3-x86_64,sles-10-x86_64,gentoo-nover-x86_64,debian-4.0-x86_64,ubuntu-7.04-x86_64,ubuntu-7.10-x86_64

#
# Valid values for vmtype are
# qemu, (vmware, xen, ... TBD)
#vmtype fossology = qemu

# Hash for VM stuff on vmtype
#vmntp default = pool.ntp.org

# We suppose we can commmunicate with the VM through SSH
#vmhost fossology = localhost
#vmlogin fossology = pb
#vmport fossology = 2222

# Timeout to wait when VM is launched/stopped
#vmtmout default = 120

# per VMs needed paramaters
#vmopt fossology = -m 2000 -daemonize -no-acpi
#vmpath fossology = /home/qemu
#vmsize fossology = 8G

#vmlist default = redhat-5-i386,redhat-5-x86_64,fedora-10-i386,fedora-10-x86_64,fedora-11-x86_64,fedora-11-i386
# 
# For Virtual environment management
# Naming convention to follow: distribution name (as per ProjectBuilder::Distribution)
# followed by '-' and by release number
# followed by '-' and by architecture
# a .vetype extension will be added to the resulting string
# a chroot rhel-3-i286 here means that the VE will be named rhel-3-i386.chroot
#
#velist fossology = fedora-7-i386

# VE params
#vetype fossology = chroot
#ventp default = pool.ntp.org
#velogin fossology = pb
#vepath fossology = /var/lib/mock
#veconf fossology = /etc/mock
#verebuild fossology = false

#
# Global version/tag for the project
#
projver fossology = 1.2.0
projtag fossology = 1
# Hash of valid version names
version fossology = trunk

# Is it a test version or a production version
testver fossology = false

# Additional repository to add at build time
# addrepo centos-5-x86_64 = http://packages.sw.be/rpmforge-release/rpmforge-release-0.3.6-1.el5.rf.x86_64.rpm,ftp://ftp.project-builder.org/test/centos/5/pb.repo
# addrepo centos-4-x86_64 = http://packages.sw.be/rpmforge-release/rpmforge-release-0.3.6-1.el4.rf.x86_64.rpm,ftp://ftp.project-builder.org/test/centos/4/pb.repo

# Adapt to your needs:
# Optional if you need to overwrite the global values above
#
#pkgver fossology = stable
#pkgtag fossology = 3
# Hash of default package/package directory
defpkgdir fossology = .
# Hash of additional package/package directory
#extpkgdir minor-pkg = dir-minor-pkg

# List of files per pkg on which to apply filters
# Files are mentioned relatively to pbroot/defpkgdir
#filteredfiles fossology = Makefile.PL,configure.in,install.sh,fossology.8
#supfiles fossology = fossology.init

# For perl modules, names are different depending on distro
# Here perl-xxx for RPMs, libxxx-perl for debs, ...
# So the package name is indeed virtual
#namingtype fossology = perl

addrepo rhel-5-i386 = http://packages.sw.be/rpmforge-release/rpmforge-release-0.3.6-1.el5.rf.i386.rpm,ftp://ftp.project-builder.org/test/centos/5/pb.repo
addrepo rhel-5-x86_64 = http://packages.sw.be/rpmforge-release/rpmforge-release-0.3.6-1.el5.rf.x86_64.rpm,ftp://ftp.project-builder.org/test/centos/5/pb.repo
addrepo centos-4-i386 = http://packages.sw.be/rpmforge-release/rpmforge-release-0.3.6-1.el4.rf.i386.rpm,ftp://ftp.project-builder.org/test/centos/4/pb.repo
addrepo centos-4-x86_64 = http://packages.sw.be/rpmforge-release/rpmforge-release-0.3.6-1.el4.rf.x86_64.rpm,ftp://ftp.project-builder.org/test/centos/4/pb.repo
