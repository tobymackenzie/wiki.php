<?php
namespace TJM\Wiki\Event;
use TJM\Wiki\File;

trait FileTrait{
	protected File $file;
	public function getFile(){
		return $this->file;
	}
	public function setFile(File $val){
		$this->file = $val;
	}
}
