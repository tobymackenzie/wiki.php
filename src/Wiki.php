<?php
namespace TJM\Wiki;
use DateTime;
use Exception;
use InvalidArgumentException;
use TJM\ShellRunner\ShellRunner;

class Wiki{
	const STAGE_ALL = '*';
	protected $defaultExtension = 'md';
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
	public function getFile($name){
		$filePath = $this->getFilePath($name);
		$file = new File($this->getRelativeFilePath($filePath));
		if(file_exists($filePath)){
			$file->setContent(file_get_contents($filePath));
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
		$file = new File($this->getRelativeFilePath($filePath));
		return file_exists($filePath);
	}
	public function writeFile(File $file){
		if(!$file->getPath()){
			throw new Exception("writeFile(): File does not have a path");
		}
		$path = $this->getFilePath($file);
		$dirPath = pathinfo($path, PATHINFO_DIRNAME);
		if(!is_dir($dirPath)){
			$this->runShell('mkdir -p ' . $dirPath);
		}
		if(!file_exists($path) || file_get_contents($path) !== $file->getContent()){
			return (bool) file_put_contents($path, $file->getContent());
		}
		return false;
	}

	//--pages
	public function getPage($name){
		$file = $this->getFile($this->getRelativeFilePath($this->getPageFilePath($name)));
		return $file;
	}
	public function hasPage($name){
		return file_exists($this->getPageFilePath($name));
	}
	public function getPageFilePath($name){
		$basePath = $this->getFilePath($name);
		$path = $basePath . '.' . $this->defaultExtension;
		if(file_exists($path) && is_file($path)){
			return $path;
		}
		$path = $basePath;
		if(file_exists($path) && is_file($path)){
			return $path;
		}
		foreach(glob($basePath . '.*') as $path){
			if(is_file($path)){
				return $path;
			}
		}
		return $basePath . '.' . $this->defaultExtension;
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
			$command = str_replace('{{fileName}}', $fileName, $command);
			$command = str_replace('{{path}}', $filePath, $command);
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
