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
    return exec("cygpath -u '{$path}'");
}

/**
 * Cygwin パスを Windows パスに変換
 *
 * @param string $path Cygwin パス
 * @return string Windows パス
 */
function _wp($path) {
    if(empty($path)) {
        return '';
    }
    $path = trim($path);
    if(!IS_CYGWIN_ENV) {
        return $path;
    }
    return exec("cygpath -w '{$path}'");
}