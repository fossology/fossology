# -*- mode: ruby -*-
# vi: set ft=ruby :

# Copyright Siemens AG, 2014
# SPDX-License-Identifier:  GPL-2.0 LGPL-2.1

$build_and_test = <<SCRIPT
export DEBIAN_FRONTEND=noninteractive

echo "Provisioning system to compile, test and develop."
# fix "dpkg-reconfigure: unable to re-open stdin: No file or directory" issue
sudo dpkg-reconfigure locales

# use proxy from host
# just create a file PROXY within src root folder to enable it
if [ -f /vagrant/PROXY ] ; then
  # get gateway of running VM and set proxy info within global profile
  echo 'export host_ip="`netstat -rn | grep "^0.0.0.0 " | cut -d " " -f10`"' >> /etc/profile.d/proxy.sh
  echo 'export ftp_proxy="http://$host_ip:3128"'                             >> /etc/profile.d/proxy.sh
  echo 'export http_proxy="http://$host_ip:3128"'                            >> /etc/profile.d/proxy.sh
  echo 'export https_proxy="https://$host_ip:3128"'                          >> /etc/profile.d/proxy.sh

  # load the proxy within current shell
  . /etc/profile.d/proxy.sh

  if ! sudo grep -q http_proxy /etc/sudoers; then
    sudo su -c 'sed -i "s/env_reset/env_reset\\nDefaults\\tenv_keep = \\"http_proxy https_proxy ftp_proxy\\"/" /etc/sudoers'
  fi
fi

sudo apt-get update -y

sudo apt-get install -qq curl php5

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/bin/composer

date > /etc/vagrant.provisioned

echo "lets go!"
cd /vagrant

./utils/fo-installdeps -e -y
make CFLAGS=-I/usr/include/glib-2.0
sudo make install
sudo /usr/local/lib/fossology/fo-postinstall
sudo /etc/init.d/fossology start

sudo cp /vagrant/install/src-install-apache-example.conf  /etc/apache2/conf-enabled/fossology.conf

sudo /etc/init.d/apache2 restart

echo "use your FOSSology at http://localhost:8081/repo/"
echo " user: fossy , password: fossy"
echo "or do a vagrant ssh and look at /vagrant for your source tree"
SCRIPT

Vagrant.configure("2") do |config|
  # Hmm... no Debian image available yet, let's use a derivate
  # Ubuntu Server 14.04 LTS (Trusty Tahr)
  config.vm.box = "trusty64"
  config.vm.box_url = "https://cloud-images.ubuntu.com/vagrant/trusty/current/trusty-server-cloudimg-amd64-vagrant-disk1.box"

  config.vm.provider :virtualbox do |vbox|
    vbox.customize ["modifyvm", :id, "--memory", "1024"]
    vbox.customize ["modifyvm", :id, "--cpus", "2"]
  end

  config.vm.network :forwarded_port, guest: 80, host: 8081

  # call the script
  config.vm.provision :shell, :inline => $build_and_test

  # fix "stdin: is not a tty" issue
#  config.ssh.shell = "bash -c 'BASH_ENV=/etc/profile exec bash'"
end
