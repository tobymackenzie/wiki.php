<?php
namespace TJM\Wiki\Tests\Src;
use TJM\Wiki\Event\GetPageFilePathEvent;
use TJM\Wiki\Plugin;

class GetFilePathNamePlugin extends Plugin{
	static public function getSubscribedEvents(): array{
		return [
			GetPageFilePathEvent::class=> 'event',
		];
	}
	public function event(GetPageFilePathEvent $event){
		$name = $event->getNormalizedName();
		if(substr($name, 0, 4) === '/old'){
			$event->setName('/new' . substr($name, 4));
		}
	}
}
