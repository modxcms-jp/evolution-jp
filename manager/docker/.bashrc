#!/bin/bash

HISTCONTROL=ignoredups:erasedups
export PS1='\n \[\e[1;32m\]\w \n \[\e[1;32m\]> \[\e[m\]'
export HISTFILE=~/.bash_history
touch $HISTFILE
