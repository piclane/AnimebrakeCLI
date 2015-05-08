<?php
require_once __DIR__ . '/cygwin.inc.php';

if (stristr(PHP_OS, 'win') && !stristr(PHP_OS, 'darwin')) {
    $BIN_HANDBRAKECLI = _cp('C:\Program Files (x86)\Handbrake\HandBrakeCLI.exe');
    $BIN_MEDIAINFO =  _cp('C:\Program Files\MediaInfo\MediaInfo.exe');
} else {
    $BIN_HANDBRAKECLI = '/usr/local/bin/HandBrakeCLI';
    $BIN_MEDIAINFO = '/usr/local/bin/MediaInfo';
}

/** @const HandBrakeCLI へのフルパス */
define('BIN_HANDBRAKECLI', $BIN_HANDBRAKECLI);

/** @const MediaInfo へのフルパス */
define('BIN_MEDIAINFO',    $BIN_MEDIAINFO);