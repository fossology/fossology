# FOSSology Makefile - agents/copyright_analysis
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
DEPS=$(TOP)/Makefile.deps

all: model.dat copyright

copyright-build: copyright.py $(PYTHONLIB)
	cat copyright.py > copyright-build
	echo -n "    sys.path.append('" >> copyright-build
	echo -n $(PYTHONLIBPATH) >> copyright-build
	echo    "')" >> copyright-build
	echo    "    import libfosspython" >> copyright-build
	echo    "    import copyright_library as library" >> copyright-build
	echo    "    main()" >> copyright-build
	chmod a+x copyright-build

copyright: copyright.py $(PYTHONLIB)
	cat copyright.py > copyright
	echo -n "    sys.path.append('" >> copyright
	echo -n $(DESTDIR)$(LIBDIR) >> copyright
	echo    "/python_fossology/')" >> copyright
	echo    "    import libfosspython" >> copyright
	echo    "    import copyright_library as library" >> copyright
	echo    "    main()" >> copyright
	chmod a+x copyright

model.dat: copyright-build copyright_library.py data.txt
	./copyright-build --model model.dat --training data.txt
	
install: all
	$(INSTALL_PROGRAM) copyright $(DESTDIR)$(AGENTDIR)/copyright
	mkdir -p $(DESTDIR)$(LIBDIR)/python_fossology/
	$(INSTALL_PROGRAM) copyright_library.py $(DESTDIR)$(LIBDIR)/python_fossology/copyright_library.py
	$(INSTALL_PROGRAM) model.dat $(DATADIR)/model.dat
	make -C ui-plugin install

uninstall: 
	rm -rf $(DESTDIR)$(AGENTDIR)/copyright
	rm -rf $(DESTDIR)$(LIBDIR)/python_fossology/copyright_library.py
	rmdir $(DESTDIR)$(LIBDIR)/python_fossology/
	rm -rf $(DATADIR)/model.dat
	make -C ui-plugin uninstall

test: all
	@echo "*** No tests available for agents/copyright_analysis ***"

clean:
	rm -f copyright copyright-build model.dat training_files

include $(DEPS)

.PHONY: all install uninstall clean test
