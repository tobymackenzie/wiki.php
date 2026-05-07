<?php
namespace TJM\Wiki\Event;
use Symfony\Contracts\EventDispatcher\Event;
use TJM\Wiki\File;

class RemovedFileEvent extends Event{
	use FileTrait;
	public function __construct(File $file){
		$this->setFile($file);
	}
}
