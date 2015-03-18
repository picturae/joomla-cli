<?php

namespace JoomlaCli\Test\Console\Model\Joomla;

use JoomlaCli\Console\Model\Joomla\Versions;

/**
 * We fake the versions normally from github by manipulating the cache file
 */
class VersionsTest extends \PHPUnit_Framework_TestCase
{

    protected $cacheFile;

    public function testRepository()
    {
        $model = new Versions();
        $model->setJoomlaRepository('my-test-repo');

        $this->assertEquals('my-test-repo', $model->getJoomlaRepository());
    }

    public function testGetVersions()
    {
        file_put_contents(
            $this->cacheFile,
            json_encode(
                [
                    'heads' => ['1.x' => 'test'],
                    'tags' => ['1.0.0' => 'test']
                ]
            )
        );

        $model = new Versions($this->cacheFile);
        $versions = $model->getVersions();
        $this->assertEquals(['1.x' => 'test'], $versions['heads']);
        $this->assertEquals(['1.0.0' => 'test'], $versions['tags']);
    }

    public function testGetVersion()
    {
        file_put_contents(
            $this->cacheFile,
            json_encode(
                [
                    'heads' => [
                        '1.x' => 'http://test',
                        '2.x' => 'http://test',
                        'master' => 'http://test',
                    ],
                    'tags' => [
                        '1.0.0' => 'http://test/1.0.0',
                        '1.0.1' => 'http://test/1.0.1',
                        '1.0.2' => 'http://test/1.0.2',
                        '1.1.0' => 'http://test/1.1.0',
                        '1.1.1' => 'http://test/1.1.1',
                        '1.1.2' => 'http://test/1.1.2',
                    ]
                ]
            )
        );

        $model = new Versions($this->cacheFile);

        $this->assertEquals('1.1.2', array_keys($model->getVersion('1.1.*', true))[0]);
        $this->assertEquals('1.1.2', array_keys($model->getVersion('1.*', true))[0]);
        $this->assertEquals('1.0.0', array_keys($model->getVersion('1.0.0', true))[0]);
        $this->assertEquals('master', array_keys($model->getVersion('master', false))[0]);
        $this->assertEquals('http://test/1.0.2', array_values($model->getVersion('1.0.2', true))[0]);

    }

    public function testIsTag()
    {
        file_put_contents(
            $this->cacheFile,
            json_encode(
                [
                    'heads' => [
                        '1.x' => 'http://test',
                        '2.x' => 'http://test',
                        '1.0.0' => 'http://test',
                    ],
                    'tags' => [
                        '1.0.0' => 'http://test',
                        '1.0.1' => 'http://test',
                        '1.0.2' => 'http://test.test',
                    ]
                ]
            )
        );

        $model = new Versions($this->cacheFile);

        $this->assertFalse($model->isTag('1.0.0'));
        $this->assertTrue($model->isTag('1.0.1'));
    }

    public function testIsTagException()
    {
        $this->setExpectedException('InvalidArgumentException');
        file_put_contents(
            $this->cacheFile,
            json_encode(
                [
                    'heads' => ['1.x' => 'test'],
                    'tags' => ['1.0.0' => 'test']
                ]
            )
        );

        $model = new Versions($this->cacheFile);
        $model->isTag('does not exist');
    }

    protected function setup()
    {
        $this->cacheFile = sys_get_temp_dir() . '/joomla-cli-test-versions-cache.json';
    }

    protected function tearDown()
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }
}