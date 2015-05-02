<?php
require_once __DIR__ . '/../MediaInfo.class.php';

class VideoInfoTest extends PHPUnit_Framework_TestCase  {

    public function testDetectTreePathRelation() {
        $method = new ReflectionMethod('VideoInfo', 'detectTreePathRelation');
        $method->setAccessible(true);

        $this->assertEquals('SELF',       $method->invoke(null, '/video/1', '/video/1'));
        $this->assertEquals('CHILD',      $method->invoke(null, '/video/1', '/video/1/child'));
        $this->assertEquals('DESCENDANT', $method->invoke(null, '/video/1', '/video/1/child/child'));
        $this->assertEquals('UNRELATED',  $method->invoke(null, '/video/1', '/audio/1'));
        $this->assertEquals('UNRELATED',  $method->invoke(null, '/video/1', '/video'));
    }


}