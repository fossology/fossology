#!/usr/bin/env bash

YesOpt=$1

DISTRO=$(lsb_release --id --short)
CODENAME=$(lsb_release --codename --short)

echo "*** Installing $DISTRO runtime dependencies ***"
case "$DISTRO" in
  Debian|Ubuntu|Linux\ Mint|Pop\!_OS|Elementary\ OS|Zorin\ OS)
    apt-get update
    apt-get $YesOpt install --no-install-recommends \
      apache2 php-pear libjsoncpp1 libboost-filesystem1.62.0 libxml2 libzstd1 \
      binutils cabextract cpio sleuthkit genisoimage poppler-utils unrar-free \
      unzip p7zip-full p7zip wget subversion git dpkg-dev php-uuid \
      libgcrypt20 libcrypt1 libmagic1 rpm bzip2 cabextract cpio p7zip-full \
      poppler-utils tar unzip gzip sleuthkit unrar zstd glib2.0-0 \
      python3 python3-pip
    ;;
  Fedora|RHEL|CentOS|Rocky|AlmaLinux)
    yum $YesOpt install postgresql-server httpd php php-pear php-pgsql php-process \
      php-xml php-mbstring smtpdaemon jsoncpp boost-libs libxml2 zstd binutils \
      mailx sleuthkit boost libicu libgcrypt rpm tar unzip gzip p7zip-plugins \
      bzip2 cpio genisoimage poppler-utils glib2 python3 python3-pip
    ;;
  Arch|Manjaro|Endeavour\ OS)
    pacman -Sy --noconfirm apache php php-pear jsoncpp boost-libs libxml2 zstd \
      binutils cabextract cpio sleuthkit genisoimage poppler unrar unzip p7zip \
      wget subversion git php-uuid postgresql libgcrypt libmagic rpm python3 \
      python-pip bzip2 tar gzip sleuthkit
    ;;
  Alpine)
    apk add apache2 php7-pear jsoncpp boost-libs libxml2 libzstd binutils \
      cabextract cpio sleuthkit genisoimage poppler-utils unrar unzip p7zip \
      wget git php7-uuid libgcrypt libmagic rpm bzip2 tar gzip sleuthkit \
      python3 py3-pip
    ;;
  Darwin)
    brew install httpd php jsoncpp boost libxml2 zstd binutils cabextract \
      sleuthkit poppler p7zip wget git libgcrypt libmagic rpm bzip2 tar gzip \
      sleuthkit python3 pip
    ;;
  *)
    echo "ERROR: Unsupported distribution $DISTRO"
    exit 1
    ;;
esac

# Free Space of Disk
case "$DISTRO" in
  Debian|Ubuntu|Linux\ Mint|Pop\!_OS|Elementary\ OS|Zorin\ OS)
    apt-get clean
    apt-get autoremove -y
    ;;
  Fedora|RHEL|CentOS|Rocky|AlmaLinux)
    yum clean all
    ;;
  Arch|Manjaro|Endeavour\ OS)
    pacman -Scc --noconfirm
    ;;
  Alpine)
    apk cache clean
    ;;
  Darwin)
    brew cleanup
    ;;
  *)
    echo "WARNING: No cleanup procedure defined for $DISTRO"
    ;;
esac

