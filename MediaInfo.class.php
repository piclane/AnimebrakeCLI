<?php
require_once __DIR__ . '/config.inc.php';

/**
 * Class MediaInfo
 *
 * 動画情報
 */
class MediaInfo {
    /** @var array 動画情報ツリー(ノード名=>array(キー=>値)) */
    private $info;

    /**
     * 動画ファイルの情報をスキャンします
     *
     * @param string $videoPath 動画ファイルへのパス
     * @return MediaInfo
     */
    public static function scan($videoPath) {
        $videoInfo = new MediaInfo();

        $lines = array();
        $currentTreePath = '/';
        exec("\"".BIN_MEDIAINFO."\" \"{$videoPath}\"", $lines);
        foreach($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (strpos($line, ':') === false) {
                if ($line === 'General') {
                    $currentTreePath = "/";
                } else if (preg_match('/^(.+?)( #(\d+))?$/', $line, $matches)) {
                    $name = strtolower($matches[1]);
                    $number = empty($matches[3]) ? 1 : intval($matches[3]);
                    $currentTreePath = "/{$name}/{$number}";
                }
            } else {
                if (preg_match('/^([^:]+):(.+)$/', $line, $matches)) {
                    $key = self::_mediainfoKey($matches[1]);
                    $value = self::_mediainfoVal($key, $matches[2]);
                    $videoInfo->set($currentTreePath, $key, $value);
                }
            }
        }

        return $videoInfo;
    }

    /**
     * MedisInfo から取得したキーを正規化します
     *
     * @param string $key MedisInfo から取得したキー
     * @return string 正規化されたキー
     */
    private static function _mediainfoKey($key) {
        $key = trim($key);
        $key = strtolower($key);
        return $key;
    }

    /**
     * MedisInfo から取得した値を正規化します
     *
     * @param string $key キー
     * @param string $value 値
     * @return string 正規化された値
     */
    private static function _mediainfoVal($key, $value) {
        $value = trim($value);
        if(preg_match('/^(.+) fps$/', $value, $matches)) {
            return doubleval($matches[1]);
        }
        if(preg_match('/^(.+) pixels/', $value, $matches)) {
            return intval(str_replace(' ', '', $matches[1]));
        }
        if(preg_match('/^([0-9]+) \(0x[0-9a-fA-F]+\)/', $value, $matches)) {
            return intval($matches[1]);
        }
        if($key === 'scan type') {
            return strtolower($value);
        }
        if($key === 'duration' && preg_match('/^((\d+)\s*h\s*)?((\d+)\s*mn\s*)?((\d+)\s*s\s*)?$/', $value, $matches)) {
            $duration = 'PT';
            if(!empty($matches[2])) {
                $duration .= "{$matches[2]}H";
            }
            if(!empty($matches[4])) {
                $duration .= "{$matches[4]}M";
            }
            if(!empty($matches[6])) {
                $duration .= "{$matches[6]}S";
            }
            $value = new DateInterval($duration);
        }
        return $value;
    }

    /**
     * ユーザーから与えられたツリーパスを正規化します
     *
     * @param string[]/string $treePath ツリーパス
     * @return string ツリーパス
     */
    private static function _treePath($treePath) {
        if(is_array($treePath)) {
            $treePath = implode('/', $treePath);
        }
        if($treePath{0} !== '/') {
            $treePath = "/{$treePath}";
        }
        return $treePath;
    }

    /**
     * ユーザーから与えられたキーを正規化します
     *
     * @param null|string $key キー
     * @return null|string キー
     */
    private static function _key($key) {
        if(is_null($key)) {
            return null;
        }
        $key = trim($key);
        $key = strtolower($key);
        return $key;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->info = array();
    }

    /**
     * ノードの属性値を設定します
     *
     * @param string[]|string $treePath ツリーパス
     * @param string $key キー
     * @param mixed $value 値
     */
    public function set($treePath, $key, $value) {
        $treePath = self::_treePath($treePath);
        $key = self::_key($key);

        if(!isset($this->info[$treePath])) {
            $this->info[$treePath] = array();
        }
        $this->info[$treePath][$key] = $value;
    }

    /**
     * ノードの属性値を取得します
     *
     * @param string[]|string $treePath ツリーパス
     * @param null|string $key キー
     * @return array|mixed $key が指定された時は値、null の時はノードの全属性を返します
     */
    public function get($treePath, $key = null) {
        $treePath = self::_treePath($treePath);
        $key = self::_key($key);

        if(is_null($key)) {
            return @$this->info[$treePath];
        } else {
            return @$this->info[$treePath][$key];
        }
    }

    /**
     * ノードの属性値が指定された値と一致するかどうかを取得します
     *
     * @param string[]|string $treePath ツリーパス
     * @param string $key キー
     * @param mixed $value 値
     * @return bool ノードの属性値が指定された値と一致する時 true
     */
    public function is($treePath, $key, $value) {
        return $this->get($treePath, $key) === $value;
    }

    /**
     * ノードの属性値の数を取得します
     *
     * @param string[]|string $treePath ツリーパス
     * @return int ノードの属性値の数
     */
    public function count($treePath) {
        $treePath = self::_treePath($treePath);

        if(isset($this->info[$treePath])) {
            return count($this->info[$treePath]);
        } else {
            return 0;
        }
    }

    /**
     * ノードの子ノードの数を取得します
     *
     * @param string[]|string $treePath ツリーパス
     * @return int ノードの子ノードの数
     */
    public function countChildren($treePath) {
        $treePath = self::_treePath($treePath);

        $collection = array();
        foreach($this->info as $tp=>$node) {
            $relation = self::detectTreePathRelation($treePath, $tp, $childPath);
            if($relation ===  'CHILD' || $relation === 'DESCENDANT') {
                $collection[$childPath[0]] = true;
            }
        }
        return count($collection);
    }

    /**
     * 指定されたノード同士の親子関係を取得します
     *
     * @param string $node ツリーパス
     * @param string $descendant $node の子孫となるノードのツリーパス
     * @param string[] $childPath $descendant ノードが $node の子孫だった時に $node からの相対パスを参照渡しで返します
     * @return string 関係を表す文字列
     */
    private static function detectTreePathRelation($node, $descendant, &$childPath=null) {
        $childPath = null;
        $node = explode('/', substr($node, 1));
        $nodeLevel = count($node);
        $descendant = explode('/', substr($descendant, 1));
        $descendantLevel = count($descendant);
        if($nodeLevel > $descendantLevel) {
            return 'UNRELATED';
        }
        $len = min($nodeLevel, $descendantLevel);
        $level = $len;
        for($i=0; $i<$len; $i++) {
            if($node[$i] !== $descendant[$i]) {
                $level = $i;
                break;
            }
        }
        if($level === $nodeLevel) {
            if($descendantLevel === $nodeLevel) {
                $childPath = array();
                return 'SELF';
            }
            if($descendantLevel === ($nodeLevel + 1)) {
                $childPath = array_slice($descendant, $nodeLevel);
                return 'CHILD';
            }
            if($descendantLevel  >  ($nodeLevel + 1)) {
                $childPath = array_slice($descendant, $nodeLevel);
                return 'DESCENDANT';
            }
        }
        return 'UNRELATED';
    }
}