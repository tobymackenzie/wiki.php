<?php
namespace TJM\Wiki;

abstract class Plugin implements PluginInterface{
	protected ?Wiki $wiki = null;
	public function setWiki(Wiki $wiki){
		$this->wiki = $wiki;
	}
}
