<?php
namespace TJM\Wiki\Tests;
use Exception;
use PHPUnit\Framework\TestCase;
use TJM\Wiki\File;
use TJM\Wiki\Wiki;

class WikiTest extends TestCase{
	const WIKI_DIR = __DIR__ . '/tmp';
	const STATUS_COMMAND = '-c color.status=false status --short';

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
		mkdir(self::WIKI_DIR . '/foo');
		foreach([
			'1.txt', //--root
			 'foo/1.txt', //--subdir
		] as $name){
			$content = "test\n{$name}\n123";
			file_put_contents(self::WIKI_DIR . '/' . $name, $content);
			$file = $wiki->getFile($name);
			$this->assertEquals($content, $file->getContent());

		}
	}
	public function testHasFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		foreach([
			'1.txt', //--root
			 'foo/1.txt', //--subdir
		] as $name){
			$this->assertFalse($wiki->hasFile($name), 'File should not exist before creation.');
			$content = "test\n123";
			file_put_contents(self::WIKI_DIR . '/' . $name, $content);
			$this->assertTrue($wiki->hasFile($name), 'File should exist after creation');
		}
	}
	public function testHasFileWrongCaseExtension(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		mkdir(self::WIKI_DIR . '/foo/bar');
		$content = "test\n123";
		//--add some noise files to ensure they don't affect file find
		file_put_contents(self::WIKI_DIR . '/foo/two.ttxt', $content);
		file_put_contents(self::WIKI_DIR . '/foo/twomore.txt', $content);
		file_put_contents(self::WIKI_DIR . '/foo/bar/two.txt', $content);

		foreach([
			'one.txt'=> 'one.TXT', //--root
			 'foo/two.txt'=> 'foo/two.TXT', //--subdir
		] as $name=> $try){
			file_put_contents(self::WIKI_DIR . '/' . $name, $content);
			$this->assertTrue($wiki->hasFile($try), "File {$name} should exist with wrong case of extension {$try}.");
		}
	}
	public function testHasntFileWrongCase(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		mkdir(self::WIKI_DIR . '/foo/bar');
		$content = "test\n123";
		//--add some noise files to ensure they don't affect file find
		file_put_contents(self::WIKI_DIR . '/foo/twomore.txt', $content);
		file_put_contents(self::WIKI_DIR . '/foo/two.ttxt', $content);
		file_put_contents(self::WIKI_DIR . '/foo/bar/two.txt', $content);

		foreach([
			'one.txt'=> 'One.txt', //--root
			 'foo/two.txt'=> 'foo/TWO.txt', //--subdir
		] as $name=> $try){
			file_put_contents(self::WIKI_DIR . '/' . $name, $content);
			$this->assertFalse($wiki->hasFile($try), "File {$name} should not exist with wrong case {$try}.");
		}
	}
	public function testHasntFileFolder(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		mkdir(self::WIKI_DIR . '/foo/bar');
		$content = "test\n123";
		//--add some noise files to ensure they don't affect file find
		file_put_contents(self::WIKI_DIR . '/foo/foo.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/bar/bar.md', $content);

		foreach([
			'foo', //--root
			 'foo/bar', //--subdir
		] as $name){
			$this->assertFalse($wiki->hasFile($name), "File {$name} should not exist when only a folder exists.");
		}
	}
	public function testMoveFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'1.txt'=> '2.txt', //--root
			 '3.txt'=> 'foo/3.txt', //--subdir
		] as $name1=> $name2){
			$content = "test\n123";
			$file = $wiki->getFile($name1);
			$file->setContent($content);
			$wiki->writeFile($file);
			$this->assertTrue($wiki->hasFile($name1));
			$this->assertFalse($wiki->hasFile($name2));
			$wiki->moveFile($file, $name2);
			$this->assertFalse($wiki->hasFile($name1));
			$this->assertTrue($wiki->hasFile($name2));
			$this->assertEquals($content, file_get_contents(self::WIKI_DIR . '/' . $name2));
		}
	}
	public function testRemoveFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'1.txt', //--root
			 'foo/1.txt', //--subdir
		] as $name){
			$content = "test\n123";
			$file = $wiki->getFile($name);
			$this->assertFalse($wiki->hasFile($file));
			$file->setContent($content);
			$wiki->writeFile($file);
			$this->assertTrue($wiki->hasFile($name));
			$wiki->removeFile($file);
			$this->assertFalse($wiki->hasFile($name));
		}
	}
	public function testWriteFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'1.txt', //--root
			 'foo/1.txt', //--subdir
		] as $name){
			$content = "test\n{$name}\n123";
			$file = $wiki->getFile($name);
			$file->setContent($content);
			$this->assertTrue($wiki->writeFile($file));
			$this->assertEquals($content, file_get_contents(self::WIKI_DIR . '/' . $name));
		}
	}
	public function testFilenameShellSecurity(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'1.txt && rm -r .',
			'1.txt\' && rm -r .',
		] as $name){
			$content = "test\n{$name}\n123";
			$file = $wiki->getFile($name);
			$file->setContent($content);
			$this->assertTrue($wiki->writeFile($file));
			$this->assertTrue(file_exists(self::WIKI_DIR . '/' . $name));
			$this->assertEquals($content, file_get_contents(self::WIKI_DIR . '/' . $name));
		}
	}

	//--pages
	public function testGetPage(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		foreach([
			'1', //--root
			 'foo/1', //--subdir
		] as $name){
			$content = "test\n{$name}\n123";
			file_put_contents(self::WIKI_DIR . '/' . $name, $content);
			$file = $wiki->getPage($name);
			$this->assertEquals($content, $file->getContent());
			$content = "123\ntest";
			file_put_contents(self::WIKI_DIR . '/' . $name . '.md', $content);
			$file = $wiki->getPage($name);
			$this->assertEquals($content, $file->getContent());
		}
	}
	public function testHasPage(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'1', //--root
			 'foo/1', //--subdir
		] as $name){
			$this->assertFalse($wiki->hasPage($name), 'File should not exist before creation.');
			$wiki->run('mkdir -p ' . self::WIKI_DIR . '/' . $name);
			$this->assertFalse($wiki->hasPage($name), 'File should not exist for folder of same name.');
			rmdir(self::WIKI_DIR . '/' . $name);
			file_put_contents(self::WIKI_DIR . '/' . $name, '123');
			$this->assertTrue($wiki->hasPage($name), 'File should exist after creating extensionless file');
			file_put_contents(self::WIKI_DIR . '/' . $name . '.md', '321');
			$this->assertTrue($wiki->hasPage($name), 'File should still exist after creating with extension');
		}
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
	public function testCommitPageFile(){
		$wiki = new Wiki(self::WIKI_DIR);
		$page = $wiki->getPage('foo');
		$page->setContent('123');
		$wiki->commitFile($page);
		chdir(self::WIKI_DIR);
		$this->assertMatchesRegularExpression("/content\(foo\): [\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:[\d]{2}:[\d]{2}/", shell_exec('git log --pretty="%s"'));
	}

	//--meta
	public function testGetMeta(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		foreach([
			'1.md', //--root
			 'foo/1.md', //--subdir
		] as $name){
			$content = "test\n{$name}\n123";
			file_put_contents(self::WIKI_DIR . '/' . $name, "---\nfoo: 123\nfn: '{$name}'\n---\n\n" . $content);
			$file = $wiki->getFile($name);
			$this->assertEquals($content, $file->getContent(), 'File content should match set content');
			$this->assertEquals(123, $file->getMeta('foo'));
			$this->assertEquals($name, $file->getMeta('fn'));
			$this->assertSame(null, $file->getMeta('not'));
		}
	}
	public function testSetMeta(){
		$wiki = new Wiki(self::WIKI_DIR);
		$file = $wiki->getFile('foo.md');
		$this->assertSame(null, $file->getMeta('foo'));
		$file->setMeta('foo', 123);
		$this->assertSame(null, $file->getMeta('fn'));
		$file->setMeta('fn', 'foo.md');
		$this->assertEquals(123, $file->getMeta('foo'));
		$this->assertEquals('foo.md', $file->getMeta('fn'));
		$this->assertSame(null, $file->getMeta('not'));
		$this->assertTrue(is_array($file->getMeta()));
	}
	public function testWriteMeta(){
		$wiki = new Wiki(self::WIKI_DIR);
		$file = $wiki->getFile('foo.md');
		$file->setMeta([
			'fn'=> 'foo.md',
			'foo'=> 123,
		]);
		$content = "test\nHello world\nfoo";
		$file->setContent($content);
		$wiki->writeFile($file);
		$this->assertEquals("---\nfn: foo.md\nfoo: 123\n---\n\n{$content}", file_get_contents(self::WIKI_DIR . '/foo.md'));
		$file->setMeta('a', 'apple');
		$wiki->writeFile($file);
		$this->assertEquals("---\nfn: foo.md\nfoo: 123\na: apple\n---\n\n{$content}", file_get_contents(self::WIKI_DIR . '/foo.md'));
	}

	//==git
	public function testStage(){
		$wiki = new Wiki(self::WIKI_DIR);
		$name = '1.txt';
		$content = "test\n123";
		$this->assertEquals("", $wiki->runGit(self::STATUS_COMMAND));
		$file1 = self::WIKI_DIR . '/1.txt';
		file_put_contents($file1, 'abc');
		$wiki->stage('1.txt');
		$this->assertEquals("A  1.txt", $wiki->runGit(self::STATUS_COMMAND));
		$file2 = self::WIKI_DIR . '/2.txt';
		file_put_contents($file2, 'abc');
		$wiki->stage(['1.txt', '2.txt']);
		$this->assertEquals("A  1.txt\nA  2.txt", $wiki->runGit(self::STATUS_COMMAND));
		file_put_contents($file2, 'abcd');
		$wiki->stage(Wiki::STAGE_ALL);
		$this->assertEquals("A  1.txt\nA  2.txt", $wiki->runGit(self::STATUS_COMMAND));
	}

	//==paths
	public function testCanonicalPathForCase(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		mkdir(self::WIKI_DIR . '/foo/bar');
		$content = "test\n123";
		file_put_contents(self::WIKI_DIR . '/index.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/foo.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/fooo.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/bar/bar.md', $content);
		foreach([
			'/iNDex'=> '/index',
			'/iNDex.md'=> '/index.md',
			'/foo/foo'=> '/foo/foo',
			'foo/foo'=> '/foo/foo',
			'/foo/foo.md'=> '/foo/foo.md',
			'foo/foo.md'=> '/foo/foo.md',
			'/foo/FOO'=> '/foo/foo',
			'foo/FOO'=> '/foo/foo',
			'foo/FOO.md'=> '/foo/foo.md',
			'foo/bar/BAR'=> '/foo/bar/bar',
			'foo/bar/BAR.md'=> '/foo/bar/bar.md',

			'foo/bar'=> null,
			'foo/BAR'=> null,
			'foo/barb'=> null,
			'foo/bar/barb'=> null,
		] as $name=> $expect){
			$this->assertEquals($expect, $wiki->getCanonicalPath($name));
		}
	}
	public function testCanonicalPathRegexChars(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		mkdir(self::WIKI_DIR . '/foo/bar');
		$content = "test\n123";
		file_put_contents(self::WIKI_DIR . '/index.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/foo.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/fooo.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/bar/bar.md', $content);
		foreach([
			'/*'=> null,
			'/in*'=> null,
			'/?'=> null,
			'/[i]ndex'=> null,
			'/foo/fo*'=> null,
			'/.+'=> null,
			//verify working
			'/index'=> '/index',
			'/foo/foo'=> '/foo/foo',
		] as $name=> $expect){
			$this->assertEquals($expect, $wiki->getCanonicalPath($name));
		}
	}
	public function testGlobCharsInPageName(){
		$wiki = new Wiki(self::WIKI_DIR);
		mkdir(self::WIKI_DIR . '/foo');
		mkdir(self::WIKI_DIR . '/foo/bar');
		$content = "test\n123";
		file_put_contents(self::WIKI_DIR . '/index.md', $content);
		file_put_contents(self::WIKI_DIR . '/about.md', $content);
		file_put_contents(self::WIKI_DIR . '/index*.md', $content);
		file_put_contents(self::WIKI_DIR . '/who?.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/foo.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/fooo.md', $content);
		file_put_contents(self::WIKI_DIR . '/foo/bar/bar.md', $content);
		foreach([
			'about'=> true,
			'index*'=> true,
			'who?'=> true,

			'foo*'=> false,
			'foo/*'=> false,
			'*'=> false,
			'a*'=> false,
			'i*'=> false,
			'index?'=> false,
			'who*'=> false,
		] as $path=> $expect){
			$this->assertEquals($expect, $wiki->hasPage($path), 'Should' . ($expect ? '' : "n't") . ' have page for ' . $path);
		}

	}

	//==shell
	public function testRun(){
		$wiki = new Wiki(self::WIKI_DIR);
		foreach([
			'foo.md',
			'foo/1.md',
		] as $name){
			$name = 'foo.md';
			$file = new File($name);
			$file->setContent($name . 'test');
			$wiki->writeFile($file);
			$this->assertEquals($name . 'test', $wiki->run('cat {{path}}', $name, $file));
			$wiki->run('echo "bar" >> {{path}}', $name, $file);
			$this->assertEquals($name . "testbar", $wiki->run(array('command'=> 'cat {{path}}'), $name, $file));
			$this->assertEquals($name, $wiki->run('ls ' . self::WIKI_DIR, $name));
			$this->assertEquals($name, $wiki->run('ls', $name, null, $wiki->getFileDir($name)));
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
