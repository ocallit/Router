<?php

/**
 * If the code wants to know when an event is fired $events = Events::getInstance()->on('eventName', ["class", "method"]);
 * If the code wants to trigger an event $events = Events::getInstance()->trigger('eventName', $object);
 * If the code wants to document or advertise its evens an event $events = Events::getInstance()->register('className', "eventName");
 * It is up to the developer to make a good event naming scheme with no clashes, so if an event changes from one class to another there is no need ot change everywehre
 */
final class Events {
    protected array $events = [];
    protected array $eventsRegistered = [];
    protected static ?self $instance = null;

    protected function __construct() {}


    public static function getInstance():self {
        if(Events::$instnace === null)
            Events::$instnace = new Events();
        return Events::$instnace;
    }

    public function on(string $eventName, callable $callable, string|null $source = null):void {
        if($source === null)
            try {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
                $source = $backtrace['file'];
            } catch(Throwable $e) {$source = "";}
        $this->events[$eventName][] = ['callback' => $callable, 'listener' => $source];
    }

    public function trigger(string $eventName, $object):void {
        foreach($this->events[$eventName] ?? [] as $event)
            try {
                $event($eventName, $object);
            } catch(Throwable $e) {}
    }

    public function register(string $className,string  $eventName):self {
        $this->eventsRegistered[$className][] = $eventName; 
        return $this;
    }

    public function getListeners():array {return $this->events;}
    public function getListenersForEvent(string $eventName):array {return $this->events[$eventName] ?? [];}
    
    public function getRegisteredEvents():array {return $this->eventsRegistered;}
    
    public function getRegisteredEventsForClass(string $className):array { return $this->eventsRegistered[$className] ?? []; }
    
    protected function isValidCallableFormat(array|string $callable): bool {
        if(is_string($callable)) {
            return preg_match('/^([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)(::([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*))?$/', $callable) === 1;
        }

        return is_array($callable)
          && count($callable) === 2
          && is_string($callable[0])
          && is_string($callable[1])
          && preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $callable[0]) === 1
          && preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $callable[1]) === 1;
    }

}
