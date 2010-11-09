#
# Alias file for Mark Donohoe
#
# bash alias file.  Keep seperate from .bashhrc to keep .bashhrc smaller
alias ll='ls -l'
alias la='ls -A'
alias l='ls -CF'
alias a=alias
alias all='ls -Ffx'
#alias atwk='export DISPLAY=15.13.184.205:0.0'
alias c=clear
alias cdn='cd /var/lib/nomos3'
alias chat='socksify ssh -L 6667:lart.fc.hp.com:6667 hplu.fc.hp.com'
alias cvsf='export CVSROOT=:pserver:anonymous@cvs.fedoraproject.org:/cvs/pkgs'
alias df='df -h'
alias emacs="/usr/bin/emacs -ms Red -cr Green -i -geometry =80x69+100+38  -bg NavyBlue -fg White & "
alias g=grep
alias j=jobs
alias ikey='sudo /usr/bin/hpproxyagent'
alias lppr="pr -l60 -n' '3"
alias l='less -X'
alias make='nice make'
alias pc='php -l'
alias rehash='PATH=$PATH'
alias resrc='. ~/.bash_profile;. ~/.bashrc'
alias s='screen '
alias sa='ssh-add'
alias sirius='sirius.rags:'
alias sls='screen -ls'
alias slr='screen -r '
alias ssirius='ssh markd@sirius.rags'
# subversion
alias slist='svn list svn+ssh://svn-linux.fc.hp.com/svn/ospo-tools'
alias slt='svn list svn+ssh://svn-linux.fc.hp.com/svn/ospo-tools/trunk'
alias svs='svn status -v .'
alias devoss='ssh markd@devoss.nealk'
alias ldl='ssh markd@ldl.fc.hp.com'
alias hplu='socksify ssh markd@hplu.fc.hp.com'
alias wls='\ls | wc -l'
alias webt='socksify ssh -L 8088:web-proxy.fc.hp.com:8088 hplu.fc.hp.com'
alias wp='export http_proxy=http://web-proxy.fc.hp.com:8088'

#	     alias setprmt='PS="$HOST[!] ";PS1="$PS";PS2="$PS>";ps3="PS?"'
#
#older emacs invocations are kept here for historical purposes
#
#	     alias emacs="/opt/hp/bin700/xemacs -bg NavyBlue -fg Yellow -font *courier-medium-r-normal--18* -geometry =80x35+100+38 -cr red & "
#	     alias xemacs="/usr/local/xemacs/bin/hppa1.0-hp-hpux10.00/xemacs -bg NavyBlue -fg Yellow -font *courier-medium-r-normal--18* -geometry =80x35+100+38 -cr red & "
#



