<?php
namespace TJM\Wiki\Event;
use Symfony\Contracts\EventDispatcher\Event;

class GetPageFilePathEvent extends Event{
	use DoneableTrait;
	protected string $name;
	protected ?string $path = null;
	public function __construct(string $name){
		$this->name = $name;
	}
	public function getName(){
		return $this->name;
	}
	public function getNormalizedName(){
		$val = $this->name;
		if(substr($val, 0, 1) !== '/'){
			$val = '/' . $val;
		}
		return $val;
	}
	public function setName(string $val){
		$this->name = $val;
	}
	public function getPath(){
		return $this->path;
	}
	public function setPath(string $val){
		$this->path = $val;
		$this->setDone(true);
	}
}
