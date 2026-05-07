<?php
namespace TJM\Wiki\Event;
use Symfony\Contracts\EventDispatcher\Event;
use TJM\Wiki\File;

class WroteFileEvent extends Event{
	use FileTrait;
	protected string $content;
	protected string $path;
	public function __construct(File $file, string $path, string $content){
		$this->setFile($file);
		$this->path = $path;
		$this->file = $file;
	}
	public function getContent(){
		return $this->content;
	}
	public function getPath(){
		return $this->path;
	}
}
