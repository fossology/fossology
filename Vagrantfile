# -*- mode: ruby -*-
# vi: set ft=ruby :

# Copyright Siemens AG, 2014
# SPDX-License-Identifier:  GPL-2.0 LGPL-2.1

$add_proxy_settings = <<PROXYSCRIPT
# get gateway of running VM and set proxy info within global profile
echo 'export host_ip="`netstat -rn | grep "^0.0.0.0 " | cut -d " " -f10`"'  > /etc/profile.d/proxy.sh
echo 'export ftp_proxy="http://$host_ip:3128"'                             >> /etc/profile.d/proxy.sh
echo 'export http_proxy="http://$host_ip:3128"'                            >> /etc/profile.d/proxy.sh
echo 'export https_proxy="https://$host_ip:3128"'                          >> /etc/profile.d/proxy.sh

# load the proxy within current shell
. /etc/profile.d/proxy.sh

if ! sudo grep -q http_proxy /etc/sudoers; then
  sudo su -c 'sed -i "s/env_reset/env_reset\\nDefaults\\tenv_keep = \\"http_proxy https_proxy ftp_proxy\\"/" /etc/sudoers'
fi
PROXYSCRIPT

$build_and_test = <<SCRIPT
# fix "dpkg-reconfigure: unable to re-open stdin: No file or directory" issue
export DEBIAN_FRONTEND=noninteractive

echo "Provisioning system to compile, test and develop."
sudo apt-get update -qq -y

date > /etc/vagrant.provisioned

echo "lets go!"
cd /vagrant

./utils/fo-installdeps -e -y

make
sudo make install

sudo /usr/local/lib/fossology/fo-postinstall

sudo cp /vagrant/install/src-install-apache-example.conf /etc/apache2/conf-enabled/fossology.conf

# increase upload size
sudo /vagrant/install/scripts/php-conf-fix.sh --overwrite

sudo /etc/init.d/apache2 restart

echo "use your FOSSology at http://localhost:8081/repo/"
echo " user: fossy , password: fossy"
echo "or do a vagrant ssh and look at /vagrant for your source tree"
SCRIPT

Vagrant.configure("2") do |config|
  config.vm.box = "debian/jessie64"

  config.vm.provider "virtualbox" do |vbox|
    vbox.customize ["modifyvm", :id, "--memory", "4096"]
    vbox.customize ["modifyvm", :id, "--cpus", "2"]
  end

  config.vm.network "forwarded_port", guest: 80, host: 8081
  config.vm.network "forwarded_port", guest: 5432, host: 5432

  # use proxy from host if environment variable PROXY=true
  if ENV['PROXY']
    if ENV['PROXY'] == 'true'
      config.vm.provision "shell" do |shell|
        shell.inline = $add_proxy_settings
      end
    end
  end

  # call the script
  config.vm.provision "shell" do |shell|
    shell.inline = $build_and_test
  end

  config.vm.provision "shell", run: "always" do |shell|
    shell.inline = "/etc/init.d/fossology start"
  end
end
