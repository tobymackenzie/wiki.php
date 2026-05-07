<?php
namespace TJM\Wiki\Tests;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TJM\Wiki\Tests\Src\GetFilePathPlugin;
use TJM\Wiki\Tests\Src\GetFilePathNamePlugin;
use TJM\Wiki\Wiki;

class PluginTest extends TestCase{
	const RESOURCES_DIR = __DIR__ . '/resources';

	protected array $getPageFilePaths = [
		//--change
		'/old'=> '/new.md',
		'/old/a'=> '/new/a.md',
		//--nochange
		'/new/a'=> '/new/a.md',
		'/foo/a'=> '/foo/a.md',
	];
	public function testGetPageFilePath(){
		$wiki = new Wiki([
			'path'=> self::RESOURCES_DIR,
			'eventDispatcher'=> new EventDispatcher(),
		]);
		$wiki->addPlugin(new GetFilePathPlugin());
		foreach($this->getPageFilePaths as $val=> $expect){
			$this->assertEquals(self::RESOURCES_DIR . $expect, $wiki->getPageFilePath($val));
		}
	}
	public function testGetPageFilePathName(){
		$wiki = new Wiki([
			'path'=> self::RESOURCES_DIR,
			'eventDispatcher'=> new EventDispatcher(),
		]);
		$wiki->addPlugin(new GetFilePathNamePlugin());
		foreach($this->getPageFilePaths as $val=> $expect){
			$this->assertEquals(self::RESOURCES_DIR . $expect, $wiki->getPageFilePath($val));
		}
	}
}
