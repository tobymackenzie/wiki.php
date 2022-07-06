<?php
namespace TJM\Wiki;

class Page{
	protected $content;
	protected $fileExtension = 'md';
	protected $fileName;
	public function __construct($opts = []){
		if($opts){
			if(!is_array($opts)){
				$opts = [
					'fileName'=> $opts,
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
	public function getFileExtension(){
		return $this->fileExtension;
	}
	public function getFileName(){
		return $this->fileName;
	}
}
