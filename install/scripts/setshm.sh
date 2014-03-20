#!/bin/bash
#
# Report initial settings, make changes, report final settings !
#
echo Current Settings are:
sysctl kernel.shmmax
sysctl kernel.shmall
page_size=`getconf PAGE_SIZE`
phys_pages=`getconf _PHYS_PAGES`
shmall=`expr $phys_pages / 2`
shmmax=`expr $shmall \* $page_size`
echo New Settings are:
sysctl -w kernel.shmmax=$shmmax
sysctl -w kernel.shmall=$shmall
echo Now making settings persistent
echo kernel.shmmax = $shmmax >> /etc/sysctl.conf
echo kernel.shmall = $shmall >> /etc/sysctl.conf
