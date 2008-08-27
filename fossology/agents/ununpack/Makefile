# ununpack Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf
CC=gcc
CFLAGS=-Wall -O2 -DUNMAGIC='"$(AGENTDATADIR)/UnMagic"' $(CFLAGS2)
#CFLAGS=-Wall -g -lefence -DUNMAGIC='"$(AGENTDATADIR)/UnMagic"' $(CFLAGS2)
EXE= departition checksum ununpack
LIB=-lpq -L../../$(BUILDLIB) -lfossdb -lfossrepo
INC=-I../../$(BUILDINC)
UUHDR= ununpack.h ununpack-iso.h ununpack-disk.h ununpack-ar.h metahandle.h md5.h sha1.h
UUSRC= ununpack.c \
       ununpack-iso.c \
       ununpack-disk.c \
       ununpack-ar.c \
       metahandle.c \
       checksum.c \
       md5.c sha1.c 

all: $(EXE)


InstallationCreate:
	@for i in $(EXE) ; do echo "$(CP) $$i ../../install/$(AGENTDIR)"; $(CP) $$i ../../install$(AGENTDIR) ; done
	$(CP) UnMagic.mime ../../install/$(AGENTDATADIR)

install: all
	@for i in $(EXE) ; do echo "$(CP) $$i $(AGENTDIR)"; $(CP) $$i $(AGENTDIR) ; done
	$(CP) UnMagic.mime $(AGENTDATADIR)
	# configure unpack directory for scheduler
	if [ ! -d /home/repository ] ; then useradd -m -c "Repository" repository ; fi
	if [ ! -d /home/repository/ununpack ] ; then $(MKDIR) /home/repository/ununpack ; fi
	chown repository:osrb /home/repository/ununpack
	chmod 770 /home/repository/ununpack
	if [ -f /usr/local/bin/ununpack ] ; then $(RM) /usr/local/bin/ununpack ; fi
	if [ -f /usr/local/bin/departition ] ; then $(RM) /usr/local/bin/departition ; fi
	ln -s $(AGENTDIR)/ununpack /usr/local/bin/ununpack
	ln -s $(AGENTDIR)/departition /usr/local/bin/departition

metahandle.h: metahandle.tab metahandle.pl
	if [ ! -x metahandle.pl ] ; then chmod u+x metahandle.pl; fi
	./metahandle.pl

metahandle.c: metahandle.tab metahandle.pl
	if [ ! -x metahandle.pl ] ; then chmod u+x metahandle.pl; fi
	./metahandle.pl

uninstall:
	@for i in $(EXE) ; do echo "$(RM) $(AGENTDIR)/$$i"; $(RM) $(AGENTDIR)/$$i ; done
	$(RM) $(DATADIR)/UnMagic.mime
	$(RM) /usr/local/bin/departition /usr/local/bin/ununpack

checksum: checksum.c md5.c md5.h sha1.c sha1.h
	$(CC) $(CFLAGS) -DMAIN -o checksum checksum.c md5.c sha1.c

departition: departition.c
	$(CC) $(CFLAGS) -o departition departition.c

ununpack: $(UUSRC) $(UUHDR)
	$(CC) $(CFLAGS) $(INC) -o ununpack $(UUSRC) $(LIB) -lmagic

clean:
	$(RM) *.o core $(EXE) metahandle.h metahandle.c

getsrc:
	apt-get install libmagic-dev upx-ucl mkisofs unzip xpdf-utils unrar-free cabextract rpm sleuthkit
	ln /usr/bin/unrar-free /usr/bin/unrar

