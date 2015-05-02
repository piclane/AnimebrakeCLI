<?php
require_once __DIR__ . '/cygwin.inc.php';
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/MediaInfo.class.php';

$inputPath = _cp(@$argv[1]);
$outputPath = 'D:/Encoded';
$videoEncoder = 'x265';
$videoQuality = 20;
$audioBitrate = 160;

// 引数が空の時は入力ソースの入力を求めるプロンプトを表示する
if(empty($inputPath)) {
    while(empty($inputPath)) {
        echo "input> ";
        $inputPath = _cp(fgets(STDIN, 4096));
    }
}

// 入力チェック＆展開
if(is_dir($inputPath)) {
    $inputPaths = glob("{$inputPath}/*.*");
} else if(is_file($inputPath)) {
    $inputPaths = array($inputPath);
} else {
    $inputPaths = glob($inputPath);
}
if(empty($inputPaths)) {
    die("{$inputPath} is not found.\n");
}

natcasesort($inputPaths);
$videos = VideoFile::fromPaths($inputPaths, $outputPath);
/** @var VideoFile $video */
foreach($videos as $video) {
    if($video->skip) {
        continue;
    }

    echo "\n";
    echo str_repeat('*', getTerminalColumns())."\n";
    echo "*\n";
    echo "* [".date('Y-m-d H:i:s')."] {$video->input}\n";
    echo "*\n";
    echo str_repeat('*', getTerminalColumns())."\n";
    echo "\n";

    $videoInfo = MediaInfo::scan($video->input);
    $videoWidth = $videoInfo->get('/video/1', 'Width');
    $videoIsInterlaced = $videoInfo->is('/video/1', 'Scan type', 'interlaced');
    $audioNums = $videoInfo->countChildren('/audio');

    $options = implode(' ', array(
        // Source Options
        "--input \"{$video->input}\"",
        // Destination Options
        "--output \"{$video->outputTemp}\"",
        "--format mp4",
        "--markers",
        // Video Options
        "--encoder {$videoEncoder}",
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
    @unlink($video->outputTemp);
    passthru($exec);
    rename($video->outputTemp, $video->output);
    echo "\n";
}

class VideoFile {
    /** @var string 入力ファイルのパス */
    public /* final */ $input;

    /** @var string 出力ファイルのパス */
    public /* final */ $output;

    /** @var string 一時出力ファイルのパス */
    public /* final */ $outputTemp;

    /** @var boolean スキップする時 true */
    public /* final */ $skip;

    /**
     * 複数のパスをまとめて VideoFile に変換します
     *
     * @param string[] $inputPaths 入力ファイルのパスの配列
     * @param string $outputPath 出力ディレクトリのパス
     * @return VideoFile[] 変換れた VideoFile の配列
     */
    public static function fromPaths(array $inputPaths, $outputPath) {
        $videos = array();
        foreach($inputPaths as $inputPath) {
            $videos[] = new VideoFile($inputPath, $outputPath);
        }
        return $videos;
    }

    /**
     * コンストラクタ
     *
     * @param string $inputPath 入力ファイルのパス
     * @param string $outputPath 出力ディレクトリのパス
     */
    function __construct($inputPath, $outputPath) {
        $pathInfo = pathinfo($inputPath);
        $this->input = $inputPath;
        $this->output = "{$outputPath}/{$pathInfo['filename']}.mp4";
        $this->outputTemp = "{$outputPath}/{$pathInfo['filename']}.tmp";
        $this->skip = file_exists($this->output);
    }
}

/**
 * 端末の幅を取得します
 *
 * @param int $def デフォルト値
 * @return int 端末の幅
 */
function getTerminalColumns($def = 80) {
    $cols = exec('tput cols', $output, $retval);
    if($retval === 0) {
        return intval($cols);
    } else {
        return $def;
    }
}