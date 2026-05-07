<?php
namespace TJM\Wiki\Event;
use TJM\Wiki\File;

trait WantFileTrait{
	protected ?File $file = null;
	public function getFile(){
		return $this->file;
	}
	public function hasFile(){
		return isset($this->file);
	}
	public function setFile(?File $val = null){
		$this->file = $val;
	}
}
