<?php
namespace TJM\Wiki\Tests;
use Exception;
use PHPUnit\Framework\TestCase;
use TJM\Wiki\File;
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

	//==files
	public function testCommitFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = 'foo/1.txt';
		$content = "test\n{$name}\n123";
		$file = $wiki->getFile($name);
		$file->setContent($content);
		chdir(self::WIKI_DIR);
		$this->assertTrue((bool) $wiki->commitFile($file, "Initial commit"), "Commiting file should not fail");
		$this->assertEquals("Initial commit\n", shell_exec('git log --pretty="%s"'));
		$file->setContent($content . "\n456");
		$this->assertTrue((bool) $wiki->commitFile($file), "Commiting file again should not fail");
		$this->assertMatchesRegularExpression("/content\(foo\\/1\\.txt\): [\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:[\d]{2}:[\d]{2}\nInitial commit\n/", shell_exec('git log --pretty="%s"'));
	}
	public function testGetFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = '1.txt';
		$content = "test\n{$name}\n123";
		file_put_contents(self::WIKI_DIR . '/' . $name, $content);
		$file = $wiki->getFile($name);
		$this->assertEquals($content, $file->getContent());
	}
	public function testHasFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = '1.txt';
		$this->assertFalse($wiki->hasFile($name), 'File should not exist before creation.');
		$content = "test\n123";
		file_put_contents(self::WIKI_DIR . '/' . $name, $content);
		$this->assertTrue($wiki->hasFile($name), 'File should exist after creation');
	}
	public function testWriteFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = '1.txt';
		$content = "test\n{$name}\n123";
		$file = $wiki->getFile($name);
		$file->setContent($content);
		$this->assertTrue($wiki->writeFile($file));
		$this->assertEquals($content, file_get_contents(self::WIKI_DIR . '/' . $name));
	}

	//--pages
	public function testGetPage(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = '1';
		$content = "test\n123";
		file_put_contents(self::WIKI_DIR . '/' . $name, $content);
		$file = $wiki->getPage($name);
		$this->assertEquals($content, $file->getContent());
		$content = "123\ntest";
		file_put_contents(self::WIKI_DIR . '/' . $name . '.md', $content);
		$file = $wiki->getPage($name);
		$this->assertEquals($content, $file->getContent());
	}
	public function testHasPage(){
		$wiki = new Wiki(self::WIKI_DIR);
		$this->assertFalse($wiki->hasPage('1'), 'File should not exist before creation.');
		mkdir(self::WIKI_DIR . '/1');
		$this->assertFalse($wiki->hasPage('1'), 'File should not exist for folder of same name.');
		rmdir(self::WIKI_DIR . '/1');
		file_put_contents(self::WIKI_DIR . '/1', '123');
		$this->assertTrue($wiki->hasPage('1'), 'File should exist after creating extensionless file');
		file_put_contents(self::WIKI_DIR . '/1.md', '321');
		$this->assertTrue($wiki->hasPage('1'), 'File should still exist after creating with extension');
	}
	public function testGetPageFilePath(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'foo'=> 'foo.md',
			'foo/bar'=> 'foo/bar.md',
		] as $name=> $path){
			$this->assertEquals(self::WIKI_DIR . '/' . $path, $wiki->getPageFilePath($name));
		}
		mkdir(self::WIKI_DIR . '/foo');
		$this->assertEquals(self::WIKI_DIR . '/foo.md', $wiki->getPageFilePath('foo'));
		file_put_contents(self::WIKI_DIR . '/foo.txt', 'test123');
		$this->assertEquals(self::WIKI_DIR . '/foo.txt', $wiki->getPageFilePath('foo'));
	}

	//==git
	public function testStage(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = '1.txt';
		$content = "test\n123";
		$statusCommand = '-c color.status=false status --short';
		$this->assertEquals("", $wiki->runGit($statusCommand));
		$file1 = self::WIKI_DIR . '/1.txt';
		file_put_contents($file1, 'abc');
		$wiki->stage('1.txt');
		$this->assertEquals("A  1.txt", $wiki->runGit($statusCommand));
		$file2 = self::WIKI_DIR . '/2.txt';
		file_put_contents($file2, 'abc');
		$wiki->stage(['1.txt', '2.txt']);
		$this->assertEquals("A  1.txt\nA  2.txt", $wiki->runGit($statusCommand));
		file_put_contents($file2, 'abcd');
		$wiki->stage(Wiki::STAGE_ALL);
		$this->assertEquals("A  1.txt\nA  2.txt", $wiki->runGit($statusCommand));
	}

	//==shell
	public function testRun(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = 'foo.md';
		$file = new File($name);
		$file->setContent('test');
		$wiki->writeFile($file);
		$this->assertEquals('test', $wiki->run('cat {{path}}', $name, $file));
		$wiki->run('echo "bar" >> {{path}}', $name, $file);
		$this->assertEquals("testbar", $wiki->run(array('command'=> 'cat {{path}}'), $name, $file));
		$this->assertEquals('foo.md', $wiki->run('ls ' . self::WIKI_DIR, $name));
		$this->assertEquals('foo.md', $wiki->run('ls', $name, null, $wiki->getFileDir($name)));
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
