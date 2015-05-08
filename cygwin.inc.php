<?php

/** @const cygwin 環境の時 true そうでない時 false */
define('IS_CYGWIN_ENV', strpos(php_uname('s'), 'CYGWIN') !== false);

/**
 * Windows パスを Cygwin パスに変換
 *
 * @param string $path Windows パス
 * @return string Cygwin パス
 */
function _cp($path) {
    if(empty($path)) {
        return '';
    }
    $path = trim($path);
    if(!IS_CYGWIN_ENV) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    if(preg_match('!^[\'"](.*)[\'"]$!', $path, $matches)) {
        $path = $matches[1];
    }
    if(preg_match('!^/cygdrive/([^/]+)(.*)$!', $path, $matches)) {
        $path = strtoupper($matches[1]).':'.$matches[2];
    }
    return $path;
}
