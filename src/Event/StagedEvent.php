<?php
namespace TJM\Wiki\Event;
use Symfony\Contracts\EventDispatcher\Event;

class StagedEvent extends Event{
	protected array $files;
	public function __construct(array $files){
		$this->files = $files;
	}
	public function getFiles(){
		return $this->files;
	}
}
