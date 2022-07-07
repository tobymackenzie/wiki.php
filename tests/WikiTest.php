<?php
namespace TJM\Project\Tests;
use Exception;
use PHPUnit\Framework\TestCase;
use TJM\Wiki\Page;
use TJM\Wiki\Wiki;

class WikiTest extends TestCase{
	//==pages
	public function testCommitPage(){
		$wikiDir = __DIR__ . '/tmp';
		if(!is_dir($wikiDir)){
			mkdir($wikiDir);
		}
		$wiki = new Wiki($wikiDir);
		$name = 'foo';
		$content = "test\n{$name}\n123";
		$page = new Page($name);
		$page->setContent($content);
		$this->assertTrue($wiki->setPage($name, $page), "Page {$name} should be created.");
		$this->assertTrue($wiki->commitPage($name, "Initial commit"));
		chdir($wiki->getPageDirPath($name));
		$this->assertEquals("Initial commit\n", shell_exec('git log --pretty="%s"'));
		$page->setContent($content . "\n456");
		$this->assertTrue($wiki->commitPage($name, "Commit two", $page));
		$this->assertEquals("Commit two\nInitial commit\n", shell_exec('git log --pretty="%s"'));

		shell_exec("rm -rf {$wikiDir}/*");
		rmdir($wikiDir);
	}
	public function testGetPage(){
		$wikiDir = __DIR__ . '/tmp';
		if(!is_dir($wikiDir)){
			mkdir($wikiDir);
		}
		$wiki = new Wiki($wikiDir);
		$name = 'foo';
		$content = "test\n{$name}\n123";
		$pageDir = $wikiDir . '/' . $name;
		if(!is_dir($pageDir)){
			mkdir($pageDir);
		}
		$pagePath = $pageDir . '/' . $name . '.md';
		file_put_contents($pagePath, $content);
		$page = $wiki->getPage($name);
		$this->assertEquals($content, $page->getContent());

		shell_exec("rm -rf {$wikiDir}/*");
		rmdir($wikiDir);
	}
	public function testSetPage(){
		$wikiDir = __DIR__ . '/tmp';
		if(!is_dir($wikiDir)){
			mkdir($wikiDir);
		}
		$wiki = new Wiki($wikiDir);
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
			$this->assertEquals("{$name}.md\n", shell_exec("ls {$wikiDir}/{$name}"));
			$this->assertEquals(file_get_contents("{$wikiDir}/{$name}/{$name}.md"), $content);
		}
		shell_exec("rm -r {$wikiDir}/*");
		rmdir($wikiDir);
	}


	//==paths
	public function testGetPageDirPath(){
		$wikiDir = '/tmp';
		$wiki = new Wiki($wikiDir);
		foreach([
			'foo',
			'bar',
			'foo/bar',
			'foo-bar',
			'foo.bar',
		] as $name){
			$this->assertEquals('/tmp/' . $name, $wiki->getPageDirPath($name));
		}
	}
	public function testInvalidGetPageDirPath(){
		$wikiDir = '/tmp';
		$wiki = new Wiki($wikiDir);
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
		$wikiDir = '/tmp';
		$wiki = new Wiki($wikiDir);
		foreach([
			'foo',
			'bar',
			'foo-bar',
			'foo.bar',
		] as $name){
			$page = new Page($name);
			$this->assertEquals("/tmp/{$name}/{$name}.md", $wiki->getPageFilePath($name, $page));
		}
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
