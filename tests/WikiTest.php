<?php
namespace TJM\Project\Tests;
use Exception;
use PHPUnit\Framework\TestCase;
use TJM\Wiki\Page;
use TJM\Wiki\Wiki;

class WikiTest extends TestCase{
	const WIKI_DIR = __DIR__ . '/tmp';
	static public function setUpBeforeClass(): void{
		mkdir(self::WIKI_DIR);
	}
	protected function tearDown(): void{
		shell_exec("rm -rf " . self::WIKI_DIR . "/.git && rm -rf " . self::WIKI_DIR . "/*");
	}
	static public function tearDownAfterClass(): void{
		rmdir(self::WIKI_DIR);
	}

	public function testInvalidInstantiate(){
		$this->assertException(Exception::class, function(){
			$wiki = new Wiki();
		}, "Expected exception when instantiating Wiki without path");
	}

	//==pages
	public function testCommitPage(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = 'foo';
		$content = "test\n{$name}\n123";
		$page = new Page($name);
		$page->setContent($content);
		$this->assertTrue($wiki->commitPage($name, $page, "Initial commit"), "First commit should work");
		chdir($wiki->getPageDirPath($name));
		$this->assertEquals("Initial commit\n", shell_exec('git log --pretty="%s"'));
		$page->setContent($content . "\n456");
		$this->assertTrue($wiki->commitPage($name, $page), "Second commit should work");
		$this->assertMatchesRegularExpression("/change\(foo\): [\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:[\d]{2}:[\d]{2}\nInitial commit\n/", shell_exec('git log --pretty="%s"'));

	}
	public function testGetPage(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = 'foo';
		$content = "test\n{$name}\n123";
		$pageDir = self::WIKI_DIR . '/' . $name;
		if(!is_dir($pageDir)){
			mkdir($pageDir);
		}
		$pagePath = $pageDir . '/' . $name . '.md';
		file_put_contents($pagePath, $content);
		$page = $wiki->getPage($name);
		$this->assertEquals($content, $page->getContent());
	}
	public function testHasPage(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = 'foo';
		$this->assertFalse($wiki->hasPage($name));
		$wiki->setPage($name, $wiki->getPage($name));
		$this->assertTrue($wiki->hasPage($name));
	}
	public function testSetPage(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'foo',
			'bar',
			'foo-bar',
			'foo.bar',
		] as $name){
			$content = "test\n{$name}\n123";
			$page = new Page($name);
			$page->setContent($content);
			$this->assertTrue($wiki->setPage($name, $page), "Page {$name} should be created.");
			$this->assertEquals("{$name}.md\n", shell_exec("ls " . self::WIKI_DIR . "/{$name}"));
			$this->assertEquals(file_get_contents(self::WIKI_DIR . "/{$name}/{$name}.md"), $content);
		}
	}


	//==paths
	public function testGetPageDirPath(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'foo',
			'bar',
			'foo/bar',
			'foo-bar',
			'foo.bar',
		] as $name){
			$this->assertEquals(self::WIKI_DIR . '/' . $name, $wiki->getPageDirPath($name));
		}
	}
	public function testInvalidGetPageDirPath(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'../foo',
			'./foo-bar/../../../foo/bar',
			'',
		] as $name){
			$this->assertException(Exception::class, function() use($name, $page, $wiki){
				$wiki->getPageDirPath($name);
			}, "Expected exception for Page with name {$name}");
		}
	}
	public function testGetPageFilePath(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'foo',
			'bar',
			'foo-bar',
			'foo.bar',
		] as $name){
			$page = new Page($name);
			$this->assertEquals(self::WIKI_DIR . "/{$name}/{$name}.md", $wiki->getPageFilePath($name, $page));
		}
	}

	//==shell
	public function testRun(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = 'foo';
		$page = new Page($name);
		$page->setContent('test');
		$wiki->setPage($name, $page);
		$this->assertEquals('test', $wiki->run('cat {{path}}', $name, $page));
		$wiki->run('echo "bar" >> {{path}}', $name, $page);
		$this->assertEquals("testbar", $wiki->run(array('command'=> 'cat {{path}}'), $name, $page));
		$this->assertEquals('foo.md', $wiki->run('ls {{dir}}', $name));
		$this->assertEquals('foo.md', $wiki->run('ls', $name, null, $wiki->getPageDirPath($name)));
	}

	/*=====
	==assert
	=====*/
	protected function assertException($expect, $run, $message = null){
		try{
			$run();
		}catch(Exception $e){
			$this->assertInstanceOf($expect, $e, "Exception should be instance of {$expect}");
			return true;
		}
		$this->fail($message ?: "No exception thrown, {$expect} expected");
	}
}
