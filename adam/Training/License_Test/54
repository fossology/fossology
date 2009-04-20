dnl ###########################################################################
dnl # Configure paths for Gaim
dnl # Gary Kramlich 2005
dnl #
dnl # Based off of glib-2.0.m4 by Owen Taylor
dnl ###########################################################################

dnl ###########################################################################
dnl # AM_PATH_GAIM([MINIMUM-VERSION, [ACTION-IF-FOUND [, ACTION-IF-NOT-FOUND]]])
dnl #
dnl # Test for gaim and define GAIM_CFLAGS, GAIM_LIBS, GAIM_DATADIR, and
dnl # GAIM_LIBDIR
dnl ###########################################################################
AC_DEFUN([AM_PATH_GAIM],
[dnl
	AC_PATH_PROG(PKG_CONFIG, pkg-config, no)

	no_gaim=""

	if test x"$PKG_CONFIG" != x"no" ; then
		if $PKG_CONFIG --atleast-pkgconfig-version 0.7 ; then
			:
		else
			echo "*** pkg-config is too old;  version 0.7 or newer is required."
			no_gaim="yes"
			PKG_CONFIG="no"
		fi
	else
		no_gaim="yes"
	fi

	min_version=ifelse([$1], ,2.0.0,$1)
	found_version=""
	
	AC_MSG_CHECKING(for gaim - version >= $min_version)

	if test x"$no_gaim" = x"" ; then
		GAIM_DATADIR=`$PKG_CONFIG --variable=datadir gaim`
		GAIM_LIBDIR=`$PKG_CONFIG --variable=libdir gaim`

		GAIM_CFLAGS=`$PKG_CONFIG --cflags gaim`
		GAIM_LIBS=`$PKG_CONFIG --libs gaim`

		gaim_version=`$PKG_CONFIG --modversion gaim`
		gaim_major_version=`echo $gaim_version | cut -d. -f 1`
		gaim_minor_version=`echo $gaim_version | cut -d. -f 2`
		
		dnl # stash the micro version in a temp variable.  Then stash
		dnl # the numeric for it in gaim_micro_version and anything
		dnl # else in gaim_extra_version.
		gaim_micro_version_temp=`echo $gaim_version | cut -d. -f 3`
		gaim_micro_version=`echo $gaim_micro_version_temp | sed 's/[[^0-9]]//g'`
		gaim_extra_version=`echo $gaim_micro_version_temp | sed 's/[[0-9]]//g'`

		dnl # get the major, minor, and macro that the user gave us
		min_major_version=`echo $min_version | cut -d. -f 1`
		min_minor_version=`echo $min_version | cut -d. -f 2`
		min_micro_version=`echo $min_version | cut -d. -f 3`

		dnl # check the users version against the version from pkg-config
		if test $gaim_major_version -eq $min_major_version -a \
			$gaim_minor_version -ge $min_minor_version -a \
			$gaim_micro_version -ge $min_micro_version
		then
			:
		else
			no_gaim="yes"
			found_version="$gaim_major_version.$gaim_minor_version.$gaim_micro_version$gaim_extra_version"
		fi

		dnl # Do we want a compile test here?
	fi

	if test x"$no_gaim" = x"" ; then
		AC_MSG_RESULT(yes (version $gaim_major_version.$gaim_minor_version.$gaim_micro_version$gaim_extra_version))
		ifelse([$2], , :, [$2])
	else
		AC_MSG_RESULT(no)
		if test x"$PKG_CONFIG" = x"no" ; then
			echo "*** A new enough version of pkg-config was not found."
			echo "*** See http://www.freedesktop.org/software/pkgconfig/"
		fi

		if test x"found_version" != x"" ; then
			echo "*** A new enough version of gaim was not found."
			echo "*** You have version $found_version"
			echo "*** See http://gaim.sf.net/"
		fi
		
		GAIM_CFLAGS=""
		GAIM_LIBS=""
		GAIM_DATADIR=""
		GAIM_LIBDIR=""

		ifelse([$3], , :, [$3])
	fi

	AC_SUBST(GAIM_CFLAGS)
	AC_SUBST(GAIM_LIBS)
	AC_SUBST(GAIM_DATADIR)
	AC_SUBST(GAIM_LIBDIR)
])
