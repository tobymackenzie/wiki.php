<?php
namespace TJM\Wiki;

class File{
	//-@ https://superuser.com/a/285878
	const MARKDOWN_EXTENSIONS = ['markdown', 'md', 'mdown', 'mdwn', 'mkd', 'mkdn'];

	protected $content;
	protected $meta = [];
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
			$this->content = call_user_func($this->content, $this);
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
	public function getMeta($key = null){
		//--use callable to support lazy loading meta.  callable must return meta value
		if(!is_string($this->meta) && is_callable($this->meta)){
			$this->meta = call_user_func($this->meta, $this);
		}
		if(isset($key) && is_array($this->meta)){
			return isset($this->meta[$key]) ? $this->meta[$key] : null;
		}else{
			return $this->meta;
		}
	}
	public function setMeta($a, $b = null){
		if(is_array($a) || is_null($a) || (!is_string($a) && is_callable($a))){
			$this->meta = $a;
		}else{
			$this->meta[$a] = $b;
		}
	}
	public function getPath(){
		return $this->path;
	}
	public function setPath($value = null){
		$this->path = $value;
	}
}
