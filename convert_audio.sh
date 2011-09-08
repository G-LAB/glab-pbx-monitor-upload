#!/bin/bash

cd /var/spool/asterisk/monitor

if which lame >/dev/null; then
	echo Starting file conversion...
else
	echo Exiting..please install lame.
	exit 1
fi

if [ -e "$1" ]; then
	pcmwav=$(basename $1 .wav).wav
	mp3=$(basename $pcmwav .wav).mp3
	lame -h -b 192 $pcmwav $mp3
	rm $pcmwav
fi