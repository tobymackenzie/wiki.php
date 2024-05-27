<?php
namespace TJM\Wiki;

class File{
	//-@ https://superuser.com/a/285878
	const MARKDOWN_EXTENSIONS = ['markdown', 'md', 'mdown', 'mdwn', 'mkd', 'mkdn'];

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
		//--use callable to support lazy loading content.  callable must return content value
		if(!is_string($this->content) && is_callable($this->content)){
			$this->content = call_user_func($this->content);
		}
		return $this->content;
	}
	public function setContent($content){
		$this->content = $content;
	}
	public function getExtension(){
		return pathinfo($this->getPath(), PATHINFO_EXTENSION);
	}
	public function isMarkdown(){
		return in_array($this->getExtension(), static::MARKDOWN_EXTENSIONS);
	}
	public function getPath(){
		return $this->path;
	}
	public function setPath($value = null){
		$this->path = $value;
	}
}
