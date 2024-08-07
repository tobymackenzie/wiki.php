<?php
namespace TJM\Wiki;
use DateTime;
use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Yaml\Yaml;
use TJM\ShellRunner\ShellRunner;

class Wiki{
	const STAGE_ALL = '*';
	protected $defaultExtension = 'md';
	protected FrontMatterParser $frontMatterParser;
	protected $mediaDir = '_media';
	protected $path;
	protected $shell;

	public function __construct($opts = []){
		if($opts){
			if(!is_array($opts)){
				$opts = [
					'path'=> $opts,
				];
			}
			foreach($opts as $opt=> $value){
				$setter = 'set' . ucfirst($opt);
				if(method_exists($this, $setter)){
					$this->$setter($value);
				}else{
					$this->$opt = $value;
				}
			}
		}
		if(empty($this->path)){
			throw new Exception('Must set path value for ' . self::class . ' instance');
		}
	}

	//==files
	public function commitFile(File $file, $message = null){
		$this->writeFile($file);
		if(empty($message)){
			$name = $file->getPath();
			if(pathinfo($name, PATHINFO_EXTENSION) === $this->defaultExtension){
				$name = substr($name, 0, -1 * (strlen($this->defaultExtension) + 1));
			}
			$message = 'content(' . $name . '): ' . (new DateTime())->format('Y-m-d H:i:s');
		}
		$this->stage($file);
		return $this->commit($message);
	}
	/*
	Determine if file exists, case sensitive on file name (excluding extension)
	*/
	protected function fileExists($name){
		if(file_exists($name) && is_file($name)){
			if(trim(shell_exec('if [ -e "' . __FILE__ . " -a -e " . str_replace('.php', '.PHP', __FILE__) . '" ]; then echo "0"; else echo "1"; fi')) === '1'){
				//--check case sensitive file name on case insenstive FS
				//-! will this cause problems if code tries creating a file on a case insensitive system that already exists case insensitively?
				//-! using find for forcing case sensitivity. a bit heavy, but I'm not sure if there is another way.
				$cmd = "find " . escapeshellarg(dirname($name)) . " -maxdepth 1 -type f -name " . escapeshellarg(pathinfo($name, PATHINFO_FILENAME) . "*");
				$extension = pathinfo($name, PATHINFO_EXTENSION);
				if($extension){
					$cmd .= " -iname " . escapeshellarg("*.{$extension}");
				}
				$cmd .= " 2> /dev/null";
				return (bool) shell_exec($cmd);
			}else{
				return true;
			}
		}else{
			return false;
		}
	}
	public function getFile($name){
		$filePath = $this->getFilePath($name);
		$file = new File($this->getRelativeFilePath($filePath));
		if($this->fileExists($filePath)){
			if(class_exists(FrontMatterParser::class) && $file->isMarkdown()){
				$self = $this;
				$getParsedFile = function() use($filePath, $self){
					if(!isset($self->frontMatterParser)){
						$self->frontMatterParser = new FrontMatterParser(new SymfonyYamlFrontMatterParser());
					}
					return $self->frontMatterParser->parse(file_get_contents($filePath));
				};
				$file->setMeta(function($file) use($getParsedFile){
					$parsedFile = $getParsedFile();
					$file->setConent($parsedFile->getContent());
					return $parsedFile->getFrontMatter();
				});
				$file->setContent(function($file) use($getParsedFile){
					$parsedFile = $getParsedFile();
					$file->setMeta($parsedFile->getFrontMatter());
					return $parsedFile->getContent();
				});
			}else{
				$file->setContent(function() use($filePath){
					return file_get_contents($filePath);
				});
			}
		}
		return $file;
	}
	public function getFileDir($name){
		$path = $this->getFilePath($name);
		if(!$this->isWikiPathSafe($path)){
			throw new InvalidArgumentException("Page name {$name} invalid.");
		}
		return dirname($path);
	}
	public function hasFile($name){
		$filePath = $this->getFilePath($name);
		//--make sure path is in repo via side effect
		$this->getRelativeFilePath($filePath);
		return $this->fileExists($filePath);
	}
	public function moveFile(File $file, $name){
		if($this->hasFile($name)){
			throw new Exception("Cannot move file to {$name}: File already exists.");
		}
		$oldPath = $this->getFilePath($file);
		$newPath = $this->getFilePath($name);
		$newDirPath = pathinfo($newPath, PATHINFO_DIRNAME);
		if(!file_exists($newDirPath)){
			$this->runShell('mkdir -p ' . escapeshellarg($newDirPath));
		}
		$file->setPath($this->getRelativeFilePath($newPath));
		return $this->run('mv ' . escapeshellarg($oldPath) . ' ' . escapeshellarg($newPath));
	}
	public function removeFile(File $file){
		if($this->hasFile($file)){
			return unlink($this->getFilePath($file));
		}
		return false;
	}
	public function writeFile(File $file){
		if(!$file->getPath()){
			throw new Exception("writeFile(): File does not have a path");
		}
		$path = $this->getFilePath($file);
		$dirPath = pathinfo($path, PATHINFO_DIRNAME);
		if(!is_dir($dirPath)){
			$this->runShell('mkdir -p ' . escapeshellarg($dirPath));
		}
		$content = $file->getContent();
		if($file->isMarkdown() && $file->getMeta()){
			$content = "---\n" . Yaml::dump($file->getMeta()) . "---\n\n" . $content;
		}
		if(!$this->fileExists($path) || file_get_contents($path) !== $content){
			return (bool) file_put_contents($path, $content);
		}
		return false;
	}

	//--pages
	public function getPage($name){
		$file = $this->getFile($this->getRelativeFilePath($this->getPageFilePath($name)));
		return $file;
	}
	public function hasPage($name){
		return $this->fileExists($this->getPageFilePath($name));
	}
	public function getPageFilePath($name){
		$basePath = $this->getFilePath($name);
		$path = $basePath . '.' . $this->defaultExtension;
		if($this->fileExists($path)){
			return $path;
		}
		$path = $basePath;
		if($this->fileExists($path)){
			return $path;
		}
		//--escape glob characters in path
		$globPath = preg_replace('/([\*\?\[])/', '\\\${1}', $basePath);
		foreach(glob($globPath . '.*') as $path){
			if(is_file($path)){
				return $path;
			}
		}
		return $basePath . '.' . $this->defaultExtension;
	}
	public function getPagePaths(): array{
		$files = [];
		$removeLength = strlen($this->path);
		$extensionLength = strlen($this->defaultExtension) + 1;
		foreach(
			new RegexIterator(
				new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS)
				),
				'/.*\.' . $this->defaultExtension . '$/', RegexIterator::GET_MATCH
			)
		as $file){
			$file = substr($file[0], $removeLength, -$extensionLength);
			$files[] = $file;
		}
		return $files;
	}

	//==git
	public function commit($message = null){
		if(empty($message)){
			$message = 'content: ' . (new DateTime())->format('Y-m-d H:i:s');
		}
		return $this->runGit("commit -m " . escapeshellarg($message));
	}
	public function stage($files){
		$args = [];
		$opts = [];
		foreach(is_array($files) ? $files : [$files] as $file){
			if($file === static::STAGE_ALL){
				$opts[] = '--all';
			}else{
				$args[] = escapeshellarg($this->getFilePath($file));
			}
		}
		return $this->runGit("add " . implode(' ', $opts) . ' ' . implode(' ', $args));
	}

	//==paths
	public function getFilePath($fileOrName){
		if($fileOrName instanceof File){
			if($fileOrName->getPath()){
				$name = $fileOrName->getPath();
			}else{
				throw new Exception('getFilePath: Passed in instance of File with no path set');
			}
		}else{
			$name = $fileOrName;
		}
		$path = $this->path . ($name === '/' ? $name : '/' . $name);
		if($this->isWikiPathSafe($path)){
			return $path;
		}else{
			throw new Exception("getFilePath: {$fileOrName} path not inside wiki path");
		}
	}
	protected function getRealPath($path){
		$realPath = [];
		foreach(explode('/', $path) as $bit){
			if($bit === '' || $bit === '.'){
				continue;
			}
			if($bit === '..'){
				if(count($realPath) < 1){
					return false;
				}
				array_pop($realPath);
			}else{
				$realPath[] = $bit;
			}
		}
		return '/' . implode('/', $realPath);
	}
	/*
	Get file path relative to wiki root
	*/
	protected function getRelativeFilePath($path){
		$path = $this->getRealPath($path);
		$wikiPath = $this->getRealPath($this->path);
		if(strpos($path, $wikiPath) === 0 && strlen($path) >= strlen($wikiPath)){
			$path = str_replace($wikiPath, '', $path);
			if(substr($path, 0, 1) === '/'){
				$path = substr($path, 1);
			}
			return $path;
		}else{
			throw new Exception("getRelativeFilePath(): {$path} does not appear to be in wiki path");
		}
	}

	/*
	get canonical internal path for case insensitve file name
	! doesn't account for folders
	*/
	public function getCanonicalPath($name){
		if($this->hasPage($name) || $this->hasFile($name)){
			if(substr($name, 0, 1) !== '/'){
				$name = '/' . $name;
			}
			return $name;
		}
		if(substr($name, 0, 1) === '/'){
			$name = substr($name, 1);
		}
		$extension = pathinfo($name, PATHINFO_EXTENSION);
		if($extension){
			$nameBit = preg_quote($name);
		}else{
			$nameBit = preg_quote($name) . '\.*';
		}
		if($name && strpos($name, '/') === false){
			$dir = $this->path;
		}else{
			$dir = $this->path . '/' . dirname($name);
			$nameBit = basename($nameBit);
		}
		$filePath = shell_exec("find " . escapeshellarg($dir) . " -maxdepth 1 -type f -iname " . escapeshellarg($nameBit) . " 2> /dev/null");
		if($filePath){
			$filePath = explode("\n", trim($filePath))[0];
			if($this->isWikiPathSafe($filePath)){
				$filePath = str_replace($this->path, '', $filePath);
				if($extension){
					return $filePath;
				}else{
					return explode('.', $filePath, -1)[0];
				}
			}
		}
		return null;
	}
	protected function isWikiPathSafe($path){
		$realPath = $this->getRealPath($path);
		$wikiRealPath = $this->getRealPath($this->path);
		return strpos($realPath, $wikiRealPath) === 0 && strlen($realPath) > strlen($wikiRealPath);
	}

	//==shell
	protected function parseCommandString($command, $name = null, File $item = null){
		if($item){
			$filePath = $this->getFilePath($item);
			$fileName = pathinfo($filePath, PATHINFO_BASENAME);
			$command = str_replace('{{fileName}}', escapeshellarg($fileName), $command);
			$command = str_replace('{{path}}', escapeshellarg($filePath), $command);
		}
		return $command;
	}
	public function run($commandOpts, $name = null, File $item = null, $location = null){
		if(is_array($commandOpts)){
			if(is_array($commandOpts['command'])){
				foreach($commandOpts['command'] as $key=> $command){
					$commandOpts['command'][$key] = $this->parseCommandString($command, $name, $item);
				}
			}else{
				$commandOpts['command'] = $this->parseCommandString($commandOpts['command'], $name, $item);
			}
		}else{
			$commandOpts = $this->parseCommandString($commandOpts, $name, $item);
		}
		return $this->runShell($commandOpts, $location);
	}
	public function runGit($command, $opts = []){
		if(!is_dir($this->path . '/.git')){
			$this->runShell('git init 2> /dev/null', $this->path);
		}
		if(is_string($command)){
			$opts['command'] = 'git ' . $command;
		}else{
			foreach($command as $key=> $value){
				$command[$key] = 'git ' . $value;
			}
			$opts['command'] = $command;
		}
		return $this->runShell($opts, $this->path);
	}
	protected function runShell($command, $path = null){
		if(empty($this->shell)){
			$this->shell = new ShellRunner();
		}
		return $this->shell->run($command, $path);
	}
}
