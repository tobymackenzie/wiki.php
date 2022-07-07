<?php
namespace TJM\Wiki;
use DateTime;
use InvalidArgumentException;
use TJM\ShellRunner\ShellRunner;

class Wiki{
	protected $filePath;
	protected $mediaDir = '_media';
	protected $shell;

	public function __construct($opts = []){
		if($opts){
			if(!is_array($opts)){
				$opts = [
					'filePath'=> $opts,
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
	}

	//==pages
	public function commitPage($name, $message = null, Page $page = null){
		if(empty($page) || $this->setPage($name, $page)){
			$dirPath = $this->getPageDirPath($name);
			if(!is_dir($dirPath . '/.git')){
				$this->runShell('git init 2> /dev/null', $dirPath);
			}
			if(empty($message)){
				$message = 'change: ' . (new DateTime())->format('Y-m-d H:i:s');
			}
			$this->runShell("git add -A && git commit -m " . escapeshellarg($message), $dirPath);
			return true;
		}
		return false;
	}
	public function getPage($name){
		$dirPath = $this->getPageDirPath($name);
		//-! maybe use meta to configure file name if different from default
		$page = new Page($name);
		$filePath = $this->getPageFilePath($name, $page);
		if(file_exists($filePath)){
			$page->setContent(file_get_contents($filePath));
		}
		return $page;
	}
	public function setPage($name, Page $page){
		$dirPath = $this->getPageDirPath($name);
		if(!is_dir($dirPath)){
			$this->runShell('mkdir ' . $dirPath);
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
		return strpos($realPath, $this->filePath) === 0 && strlen($realPath) > strlen($this->filePath);
	}
	public function getPageDirPath($name){
		$path = $this->filePath . '/' . $name;
		if(!$this->isPagePathSafe($path)){
			throw new InvalidArgumentException("Page name {$name} invalid.");
		}
		return $path;
	}
	public function getPageFilePath($name, Page $item){
		$dirPath = $this->getPageDirPath($name);
		$fileName = ($item->getFileName() ?: $name) . '.' . $item->getFileExtension();
		return $dirPath . '/' . $fileName;
	}

	//==shell
	protected function runShell($command, $path = null){
		if(empty($this->shell)){
			$this->shell = new ShellRunner();
		}
		$this->shell->run($command, $path);
	}
}
