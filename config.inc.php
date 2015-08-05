<?php
require_once __DIR__ . '/cygwin.inc.php';

if (stristr(PHP_OS, 'win') && !stristr(PHP_OS, 'darwin')) {
    $BIN_HANDBRAKECLI_86 = _cp('C:\Program Files (x86)\Handbrake\HandBrakeCLI.exe');
    $BIN_HANDBRAKECLI_64 = _cp('C:\Program Files\Handbrake\HandBrakeCLI.exe');
    $BIN_HANDBRAKECLI = is_file($BIN_HANDBRAKECLI_86) ? $BIN_HANDBRAKECLI_86 : $BIN_HANDBRAKECLI_64;
    $BIN_MEDIAINFO =  _cp('C:\Program Files\MediaInfo\MediaInfo.exe');
} else {
    $BIN_HANDBRAKECLI = '/usr/local/bin/HandBrakeCLI';
    $BIN_MEDIAINFO = '/usr/local/bin/MediaInfo';
}

/** @const HandBrakeCLI へのフルパス */
define('BIN_HANDBRAKECLI', $BIN_HANDBRAKECLI);

/** @const MediaInfo へのフルパス */
define('BIN_MEDIAINFO',    $BIN_MEDIAINFO);