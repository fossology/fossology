#!/bin/bash		

# Operating System present in local Machine
if command -v lsb_release &> /dev/null
then 
	distro=$(lsb_release -si)
else
	distro=$(uname -s)
fi

echo "Detected Operating System: $distro"

# Install some Pre-requisite Packages
case $distro in 
	Debian|Ubuntu|Linux\ Mint|Kubuntu|Lubuntu|Xubuntu|Pop\!_OS|Elementary\ OS|Zorin\ OS|Kali\ Linux|MX\ Linux)
		sudo apt install dos2unix;;
	RHEL|CentOS|Fedora|OpenSUSE|SUSE\ Linux\ Enterprise)
		sudo rpm -i dos2unix;;
	Arch|Manjaro|Endeavour\ OS)
		sudo pacman -S dos2unix;;
	Alpine)
		sudo apk add dos2unix;;
	Gentoo|Funtoo)
		sudo emerge dos2unix;;
	Slackware)
		sudo slackpkg install dos2unix;;
	NixOS)
		nix-env -i dos2unix;;
	Darwin) 
		brew install dos2unix;;
	*)
		echo "Unsupported Operating System"
		exit 1;;
esac

echo "Installed dos2unix for $distro"

echo "Line Encoding from CRLF To LF"
find . -type f -print0 | parallel -0 'grep -Iql $'\r' {} && dos2unix {}'

if [[ "$distro" == "Darwin" ]]; then
	/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
fi

for script in ./utils/*.sh; do
	if [[ -x "$script" ]]; then
		echo "Running $script..."
		"$script"
	else
		echo "$script is not executable."
	fi
done