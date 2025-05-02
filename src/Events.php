<?php
/** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection */
/** @noinspection PhpRedundantOptionalArgumentInspection */
/** @noinspection PhpUnused */

namespace Ocallit\Events;

use Ocallit\Route\ToCode;
use Throwable;
use function debug_backtrace;
use function file_put_contents;
use function function_exists;

class Events {
    protected array $listeners = [];
    protected array $publishers = [];
    protected static ?self $instance = null;

    protected function __construct() {}

    public static function getInstance():self {
        if(self::$instance === null)
            self::$instance = new Events();
        return self::$instance;
    }

    /**
     * Saves events as php - No closures or anonymous functions can be in listeners if you want to cache events.
     * @param string $eventCachedFilePath full path where events will be saved as php code
     * @return bool
     */
    public function saveEvents(string $eventCachedFilePath):bool {
        $listeners = ToCode::variable('$this->eventListeners', $this->listeners, indent: "\t\t");
        $publishers = ToCode::variable('$this->eventPublishers', $this->publishers, indent: "\t\t");
        return file_put_contents($eventCachedFilePath,
            "<?php\n\n$listeners\n\n\n//Publishers for selfinspection and documentation only\n$publishers\n\n",
            LOCK_EX
          ) !== false;
    }

    /**
     * Loads saved events from php - No closures or anonymous functions can be in listeners if you want to cache events.
     * @param string $eventCachedFilePath full path to php file with events cached as php code
     * @return void
     */
    public function loadEvents(string $eventCachedFilePath):void {
        include $eventCachedFilePath;
    }

    public function on(string $eventName, callable $callable, string|null $source = null):void {
        if($source === null)
            $source = $this->getCaller();
        $this->listeners[$eventName][] = ['callback' => $callable, 'listener' => $source ?? ""];
    }

    public function trigger(string $eventName, $object, string|null $source = null):void {
        if(!array_key_exists($eventName, $this->publishers)) {
            if($source === null)
                $source = $this->getCaller();
            $this->publishers[$eventName] = $source ?? "";
        }
        foreach($this->listeners[$eventName] ?? [] as $event)
            try {
                $event($eventName, $object);
            } catch(Throwable) {}
    }

    public function getListeners():array {return $this->listeners;}

    public function getPublishers():array {return $this->publishers;}

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

    protected function getCaller(): string {
        if(!function_exists('debug_backtrace'))
            return "N/A";
        try {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[1];
            $fileIfo = " at " . ($backtrace['file'] ?? "") . " Line: " . ($backtrace['function'] ?? "N/A");
            if(empty($backtrace['class']))
                return ($backtrace['function'] ?? "") . $fileIfo;
            return $backtrace['class'] . "->" . ($backtrace['function'] ?? "N/A") . $fileIfo;
        } catch(Throwable) {}
        return "";
    }

}
