#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$cmd = buildCommand();
$inputPath = $cmd['input'];
$outputPath = $cmd['output'];
$videoEncoder = $cmd['vencoder'];
$videoQuality = $cmd['vquality'];
$videoWidth = $cmd['width'];
$audioEncoder = $cmd['aencoder'];
$audioBitrate = $cmd['abitrate'];

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
    $videoSrcWidth = $videoInfo->get('/video/1', 'Width');
    $videoDstWidth = $videoWidth <= 10 ? $videoWidth * $videoSrcWidth : min($videoWidth, $videoSrcWidth);
    $videoIsInterlaced = $videoInfo->is('/video/1', 'Scan type', 'interlaced') ||
                         $videoInfo->is('/video/1', 'Scan type', 'mbaff');
    $audioNums = $videoInfo->countChildren('/audio');

    $hbInput = escapeshellarg(_wp($video->input));
    $hbOutput = escapeshellarg(_wp($video->outputTemp));
    $options = implode(' ', array(
        // Source Options
        "--input {$hbInput}",
        // Destination Options
        "--output {$hbOutput}",
        "--format mp4",
        "--markers",
        // Video Options
        "--encoder=\"{$videoEncoder}\"",
        "--encoder-preset=\"fast\"",
        "--encoder-tune=\"ssim\"",
        "--encoder-profile=\"Auto\"",
        "--quality {$videoQuality}",
        "--pfr",
        "--rate 60",
        // Picture Settings
        "--width {$videoDstWidth}",
        "--crop 0:0:0:0",
        "--loose-anamorphic",
        "--modulus 2",
        // Audio Options
        "--audio ".implode(',', range(1, $audioNums)),
        "--aencoder ".implode(',', array_fill(0, $audioNums, 'copy:aac')),
        "--audio-fallback av_aac",
        "--ab ".implode(',', array_fill(0, $audioNums, $audioBitrate)),
        "--mixdown ".implode(',', array_fill(0, $audioNums, 'dpl2')),
        "--arate ".implode(',', array_fill(0, $audioNums, 'Auto')),
        "--drc ".implode(',', array_fill(0, $audioNums, '0')),
        "--gain ".implode(',', array_fill(0, $audioNums, '0')),
        // Filters
        ($videoIsInterlaced ? '--deinterlace="bob"' : ''),
        // Misc.
        "--use-hwd",
        "--verbose 0",
    ));
    $exec = "nice -n 10 \"".BIN_HANDBRAKECLI."\" {$options}";

    echo "$ {$exec}\n\n";
    @unlink($video->outputTemp);
    passthru($exec);
    @rename($video->outputTemp, $video->output);
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
        return intval($cols) - 1;
    } else {
        return $def;
    }
}

/**
 * コマンドラインからのオプションパーサーを取得します
 *
 * @return \Commando\Command オプションパーサー
 */
function buildCommand($argv = null) {
    $cmd = new Commando\Command($argv);

    $cmd->option('i')
        ->aka("input")
        ->map(function($value) { return _cp($value); })
        ->describe("入力ファイル");

    $cmd->option('o')
        ->aka('output')
        ->default(_cp(getcwd()))
        ->map(function($value) { return _cp($value); })
        ->describe('出力ファイル');

    $cmd->option('e')
        ->aka('encoder')
        ->aka('vencoder')
        ->default('x265')
        ->describe(
            "映像エンコーダーを指定します。\n".
            '"x264", "x265", "ffmpeg4", "ffmpeg2", "theora" から選択してください。');

    $cmd->option('q')
        ->aka('quality')
        ->aka('vquality')
        ->map(function($value) { return intval($value); })
        ->default(20)
        ->describe(
            "映像の品質をコントロールします。\n".
            "デフォルト値は 20 です。");

    $cmd->option('w')
        ->aka('width')
        ->aka('vwidth')
        ->default(1.0)
        ->describe(
            "映像の幅(ピクセル)を指定します。10以下の値を指定した時は倍率としてみなされます。");

    $cmd->option('E')
        ->aka('aencoder')
        ->default('av_aac')
        ->describe(
            "音声エンコーダーを指定します：\n",
            "\"av_aac\", \"fdk_aac\", \"fdk_haac\", \"copy:aac\", \"ac3\", \n".
            "\"copy:ac3\", \"copy:dts\", \"copy:dtshd\", \"mp3\", \"copy:mp3\", \n".
            "\"vorbis\", \"flac16\", \"flac24\", \"copy\" から選択してください。");

    $cmd->option('B')
        ->aka('ab')
        ->aka('abitrate')
        ->default(160)
        ->describe(
            '平均音声ビットレートを指定します。');

    return $cmd;
}
