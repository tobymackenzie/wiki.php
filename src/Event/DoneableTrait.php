<?php
namespace TJM\Wiki\Event;

trait DoneableTrait{
	protected bool $done = false;
	public function setDone(bool $val){
		$this->done = $val;
	}
	public function isDone(){
		return $this->done;
	}
}
