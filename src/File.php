<?php
namespace TJM\Wiki;

class File{
	protected $content;
	//--$path: relative path within wiki
	protected $path;
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
	}
	public function getContent(){
		return $this->content;
	}
	public function setContent($content){
		$this->content = $content;
	}
	public function getPath(){
		return $this->path;
	}
	public function setPath($value = null){
		$this->path = $value;
	}
}
