<?php
namespace TJM\Wiki;

abstract class Plugin implements PluginInterface{
	protected ?Wiki $wiki = null;
	static public function getSubscribedEvents(): array{ return []; }
	public function setWiki(Wiki $wiki){
		$this->wiki = $wiki;
	}
}
