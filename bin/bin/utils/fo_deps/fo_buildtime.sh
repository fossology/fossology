#!/usr/bin/env bash

YesOpt=$1

DISTRO=$(lsb_release --id --short)
CODENAME=$(lsb_release --codename --short)

echo "*** Installing $DISTRO buildtime dependencies ***"
case "$DISTRO" in
  Debian|Ubuntu|Linux\ Mint|Pop\!_OS|Elementary\ OS|Zorin\ OS)
    apt-get update
    apt-get $YesOpt install --no-install-recommends \
      libjsoncpp-dev libboost-system-dev libboost-filesystem-dev \
      libmxml-dev curl libxml2-dev libcunit1-dev libicu-dev \
      build-essential libtext-template-perl subversion rpm librpm-dev \
      libmagic-dev libglib2.0 libboost-regex-dev libzstd-dev \
      libboost-program-options-dev libpq-dev composer patch devscripts \
      libdistro-info-perl libcppunit-dev libomp-dev cmake ninja-build \
      libgcrypt20-dev libcrypt-dev librpm-dev libglib2.0-dev
    ;;
  Fedora|RHEL|CentOS|Rocky|AlmaLinux)
    yum $YesOpt groupinstall "Development Tools"
    yum $YesOpt install perl-Text-Template subversion postgresql-devel file-devel \
      jsoncpp-devel boost-devel libxml2 libicu-devel libpq-devel patch \
      libomp-devel cmake ninja-build libgcrypt-devel rpm-devel glib2-devel
    ;;
  Arch|Manjaro|Endeavour\ OS)
    pacman -Sy --noconfirm base-devel jsoncpp boost curl libxml2 cunit icu \
      boost-libs postgresql cmake ninja libgcrypt glib2
    ;;
  Alpine)
    apk add build-base jsoncpp-dev boost-dev libmxml-dev curl libxml2-dev \
      libcunit-dev libicu-dev postgresql-dev cmake ninja libgcrypt-dev glib-dev
    ;;
  Darwin)
    brew install jsoncpp boost libmxml curl libxml2 cunit icu4c postgresql cmake \
      ninja libgcrypt glib
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

