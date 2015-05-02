<?php
require_once __DIR__.'/cygwin.inc.php';
require_once __DIR__ . '/AnimebrakeCLI/config.inc.php';
require_once __DIR__ . '/AnimebrakeCLI/MediaInfo.class.php';

//$path = _cp('I:\BD\フォトカノ\フォトカノ #1 「出会い」.ts');
$path = _cp(@$argv[1]);
$videoQuality = 20;
$audioBitrate = 160;

// 引数が空の時は入力ソースの入力を求めるプロンプトを表示する
if(empty($path)) {
    while(empty($path)) {
        echo "input> ";
        $path = _cp(fgets(STDIN, 4096));
    }
}

if(is_dir($path)) {
    $path = "{$path}/*.*";
} else if(!file_exists($path)) {
    die("{$path} is not found.\n");
}
foreach(glob($path) as $input) {
    echo "Scanning {$input}...\n";
    $videoInfo = MediaInfo::scan($input);
    $pathInfo = pathinfo($input);
    $outputTemp = "D:/Encoded/{$pathInfo['filename']}.tmp";
    $output = "D:/Encoded/{$pathInfo['filename']}.mp4";
    $videoWidth = $videoInfo->get('/video/1', 'Width');
    $videoIsInterlaced = $videoInfo->is('/video/1', 'Scan type', 'interlaced');
    $audioNums = $videoInfo->countChildren('/audio');
    if(file_exists($output)) {
        echo "{$output} is exist. skip this.\n";
        continue;
    }
    @unlink($outputTemp);

    $options = implode(' ', array(
        // Source Options
        "--input \"{$input}\"",
        // Destination Options
        "--output \"{$outputTemp}\"",
        "--format mp4",
        "--markers",
        // Video Options
        "--encoder x265",
        "--encoder-preset=fast",
        "--encoder-tune=\"ssim\"",
        "--quality {$videoQuality}",
        "--vfr",
        // Picture Settings
        "--width {$videoWidth}",
        "--crop 0:0:0:0",
        "--loose-anamorphic",
        "--modulus 2",
        // Audio Options
        "--audio ".implode(',', range(1, $audioNums)),
        "--aencoder ".implode(',', array_fill(0, $audioNums, 'av_aac')),
        "--audio-fallback ac3",
        "--ab ".implode(',', array_fill(0, $audioNums, $audioBitrate)),
        "--mixdown ".implode(',', array_fill(0, $audioNums, 'dpl2')),
        "--arate ".implode(',', array_fill(0, $audioNums, 'Auto')),
        "--drc ".implode(',', array_fill(0, $audioNums, '0')),
        "--gain ".implode(',', array_fill(0, $audioNums, '0')),
        // Filters
        ($videoIsInterlaced ? '--deinterlace="fast"' : ''),
        // Misc.
        "--verbose=0",
    ));
    $exec = "nice -n 10 \"".BIN_HANDBRAKECLI."\" {$options}";

    echo "$ {$exec}\n\n";
    passthru($exec);
    rename($outputTemp, $output);
}



