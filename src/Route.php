<?php


namespace Ocallit\Route;

class Route {
    protected string|array $method;
    protected string $routePath;
    protected string $routePattern;
    protected bool $isDynamic;

    protected $action;
    protected string $name = '';

    public function __construct(array|string $method, string $routePath, callable $action) {
        $this->method = $method;
        $this->routePath = $routePath;
        if(!str_contains($routePath, '{')) {
            $this->routePattern = $routePath;
        } else {
            $this->isDynamic = true;
            $this->routePattern = preg_replace_callback(
              '/{([^}]+)}/',
              function($matches) use (&$paramCounter) {
                  $paramContent = $matches[1];
                  // Format 1: Named parameters with constraints: {name:pattern}
                  if (preg_match('/^([a-zA-Z][a-zA-Z0-9_]*):(.+)$/', $paramContent, $parts)) {
                      $name = $parts[1];
                      $constraint = $parts[2];
                  }
                  // Format 2: Unnamed parameters with constraints: {\d+}
                  elseif (preg_match('/^[\\\\^$.*+?()[\]{}|]/', $paramContent)) {
                      $name = 'param' . $paramCounter++;
                      $constraint = $paramContent;
                  }
                  // Format 3: Simple named parameters: {name}
                  else {
                      $name = $paramContent;
                      $constraint = '[^/]+';
                  }
                  return '(?P<' . $name . '>' . $constraint . ')';
              },
              $routePath
            );
        }
        $this->action = $action;
    }

    public function is_dynamic(): bool {return $this->isDynamic;}

    public function getMethod(): array|string {return $this->method;}

    public function getRoutePath(): string {return $this->routePath;}

    public function getRoutePattern(): string { return $this->routePattern;}

    public function getAction(): callable {return $this->action;}

    public function getName(): string {return $this->name;}

    public function setName(string $name): void {$this->name = $name;}

}
