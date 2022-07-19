<?php
namespace TJM\Wiki;
use DateTime;
use Exception;
use InvalidArgumentException;
use TJM\ShellRunner\ShellRunner;

class Wiki{
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
	public function commit($message = null){
		if(empty($message)){
			$message = 'content: ' . (new DateTime())->format('Y-m-d H:i:s');
		}
		return $this->runGit("commit -m " . escapeshellarg($message));
	}

	//==pages
	public function commitPage($name, Page $page, $message = null){
		$this->setPage($name, $page);
		if(empty($message)){
			$message = 'content(' . $name . '): ' . (new DateTime())->format('Y-m-d H:i:s');
		}
		$this->runGit("add " . escapeshellarg($this->getPageFilePath($name, $page)));
		return $this->commit($message);
	}
	public function getPage($name){
		$dirPath = $this->getPageDirPath($name);
		//-! maybe use meta to configure file name if different from default
		$filePath = $this->getPageFilePath($name);
		$page = new Page($this->getRelativeFilePath($filePath));
		if(file_exists($filePath)){
			$page->setContent(file_get_contents($filePath));
		}
		return $page;
	}
	public function hasPage($name){
		return file_exists($this->getPageFilePath($name));
	}
	public function setPage($name, Page $page){
		$dirPath = $this->getPageDirPath($name);
		if(!is_dir($dirPath)){
			$this->run('mkdir -p {{dir}}', $name);
		}
		$filePath = $this->getPageFilePath($name, $page);
		if(!file_exists($filePath) || file_get_contents($filePath) !== $page->getContent()){
			return (bool) file_put_contents($filePath, $page->getContent());
		}
		return false;
	}

	//==paths
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
	protected function isPagePathSafe($path){
		$realPath = $this->getRealPath($path);
		$wikiRealPath = $this->getRealPath($this->path);
		return strpos($realPath, $wikiRealPath) === 0 && strlen($realPath) > strlen($wikiRealPath);
	}
	public function getPageDirPath($name){
		$path = $this->path . '/' . $name;
		if(!$this->isPagePathSafe($path)){
			throw new InvalidArgumentException("Page name {$name} invalid.");
		}
		return $path;
	}
	public function getPageFilePath($name, Page $item = null){
		if($item && $item->getPath()){
			return $this->path . '/' . $item->getPath();
		}else{
			$path = $this->path . '/' . $name . '/' . $name . '.' . $this->defaultExtension;
			if(!file_exists($path)){
				foreach(glob($this->path . '/' . $name . '/' . $name . '.*') as $file){
					return $file;
				}
			}
			return $path;
		}
	}
	/*
	Get file path relative to wiki root
	*/
	protected function getRelativeFilePath($path){
		$path = $this->getRealPath($path);
		$wikiPath = $this->getRealPath($this->path);
		if(strpos($path, $wikiPath) === 0 && strlen($path) >= strlen($wikiPath)){
			return str_replace($wikiPath, '', $path);
		}else{
			throw new Exception("getRelativeFilePath(): {$path} does not appear to be in wiki path");
		}
	}

	//==shell
	protected function parseCommandString($command, $name, Page $item = null){
		$dirPath = $this->getPageDirPath($name);
		$command = str_replace('{{dir}}', $dirPath, $command);
		if($item){
			$filePath = $this->getPageFilePath($name, $item);
			$fileName = pathinfo($filePath, PATHINFO_BASENAME);
			$command = str_replace('{{fileName}}', $fileName, $command);
			$command = str_replace('{{path}}', $filePath, $command);
		}
		return $command;
	}
	public function run($commandOpts, $name, Page $item = null, $location = null){
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
