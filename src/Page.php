<?php
namespace TJM\Wiki;

class Page extends File{
	protected $name;
	public function getName(){
		return $this->name;
	}
	public function setName($value = null){
		$this->name = $value;
	}
}
