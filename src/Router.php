<?php
/** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection */

/** @noinspection PhpUnused */


namespace Ocallit\Route;

class Router {
    protected string $name = "";

    protected string $filePath = "";

    protected string $notFoundHandler = 'route_not_found.php';

    protected array $routes = [];

    public function __construct(string $filePath, string $name = "") {
        $this->filePath = rtrim($filePath, '/') . '/';
        $this->name = $name;
    }

    public function getName(): string {return $this->name;}

    public function addRoute(string|array $method, string $routePath, callable $callable):Route {
        return $this->routes[$this->hash($this->staticPrefix($routePath))][] =
          new Route($method, $routePath, $callable);
    }

    public function getRoute(string $routePath):Route|null {
        $possibleRoutes = $this->routes[$this->hash($this->staticPrefix($routePath))] ?? [];
        foreach($possibleRoutes as $route)
            if($route->getRoutePath() === $routePath)
                return $route;
        return null;
    }


    /**
     * Returns the callable associated with the route, with params set
     * @param string $method
     * @param string $routePath
     * @return callable
     */
    public function route(string $method, string $routePath):callable {
        $staticPrefix = $this->staticPrefix($routePath);
        $fullStaticPath = $this->filePath . $staticPrefix;
        // mejorar que tal si es index.html?
        if (file_exists($fullStaticPath)) {
            if (is_dir($fullStaticPath)) {
                $currentDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
                $requestedDir = realpath($fullStaticPath);
                if ($currentDir !== $requestedDir) {
                    include_once($fullStaticPath . '/index.php');
                    exit();
                }
            } elseif (is_file($fullStaticPath)) {
                include_once($fullStaticPath);
                exit();
            }
        }
        $possibleRoutes = $this->getRoutesByMethod($method,$this->routes[$this->hash($staticPrefix)]);
        foreach($possibleRoutes as $route)
            if($route->is_dynamic()) {
                return [$route->getAction(), []];
            }


        /** @var Route $route */
        foreach($possibleRoutes as $route) {
            if($route->is_dynamic())
                continue;
            if(preg_match($route->getRoutePattern(), $routePath, $matches)) {
                $params = [];
                foreach($matches as $key => $value)
                    if (is_string($key))
                        $params[$key] = $value;
                return [$route->getAction(), $params];
            }
        }
        return $this->notFoundHandler;
    }

    protected function getRoutesByMethod(string $method, array $routes):array {
        $byMethod = [];
        foreach($routes as $route) {
            $routeMethod = $route->getMethod();
            if(is_array($routeMethod)) {
                if(in_array($method, $routeMethod) || in_array('ANY', $routeMethod))
                    $byMethod[] = $route;
            } elseif($routeMethod === $method || $routeMethod === "ANY") {
                $byMethod[] = $route;
            }
        }
        return $byMethod;
    }

    public function name2routePath($name, $params=[]):string {return "";}

    public function getRouteByName($name):Route|null {
        foreach($this->routes as $route)
            if($route->getName() === $name)
                return $route;
        return null;
    }

    protected function staticPrefix(string $routePath):string {
        return strchr($routePath, "{", true);
    }

    protected function hash(string $staticPrefix):string {
        return hash('xxh32', $staticPrefix);
    }
}
