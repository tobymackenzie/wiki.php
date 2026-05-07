<?php
namespace TJM\Wiki\Event;
use Symfony\Contracts\EventDispatcher\Event;

class CommittedEvent extends Event{
	protected string $message;
	public function __construct(string $message){
		$this->message = $message;
	}
	public function getMessage(){
		return $this->message;
	}
}
