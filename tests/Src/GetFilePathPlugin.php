<?php
namespace TJM\Wiki\Tests\Src;
use TJM\Wiki\Event\GetPageFilePathEvent;
use TJM\Wiki\Plugin;

class GetFilePathPlugin extends Plugin{
	static public function getSubscribedEvents(): array{
		return [
			GetPageFilePathEvent::class=> 'event',
		];
	}
	public function event(GetPageFilePathEvent $event){
		$name = $event->getNormalizedName();
		if(substr($name, 0, 4) === '/old'){
			$event->setPath($this->wiki->getPageFilePath('/new' . substr($name, 4)));
		}
	}
}
