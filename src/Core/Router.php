<?php

namespace Src\Core;

use PDO;

final class Router
{
    /** @var array|array[] stores all routes */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    /**
     * Helper method for adding GET method routes
     *
     * @param string $path - path to the route
     * @param string $controller - associated controller
     * @param string $action - action to be taken
     * @return void
     */
    public function get(string $path, string $controller, string $action): void
    {
        $this->addRoute('GET', $path, $controller, $action);
    }

    /**
     * Helper method for adding POST method routes
     *
     * @param string $path - path to the route
     * @param string $controller - associated controller
     * @param string $action - action to be taken
     * @return void
     */
    public function post(string $path, string $controller, string $action): void
    {
        $this->addRoute('POST', $path, $controller, $action);
    }

    /**
     * Main router function for adding routes.
     *
     * @param string $method - method of the route (POST/GET)
     * @param string $path - path to the route
     * @param string $controller - associated controller
     * @param string $action - action to be taken
     * @return void
     */
    private function addRoute(string $method, string $path, string $controller, string $action)
    {
        $regex = preg_replace(
            '/\{([a-zA-Z0-9_]+)\}/', // replaces text in curly brackets (eg. {id}, {slug}, ...)
            '(?P<\1>[^/]+)',     // puts the text into named capture group
            $path);
        $regex = '#^' . $regex . '$#';      // add prefix and suffix to the regex replaced text

        // Save the route and its data into $routes array
        $this->routes[$method][] =
            [
                'regex' => $regex,
                'controller' => $controller,
                'action' => $action,
            ];
    }

    public function dispatch(PDO $db): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $url = rtrim($url, '/') ?: '/';

        foreach ($this->routes[$method] as $route)
        {
            // test match; save match to $matches arr
            if (preg_match($route['regex'], $url, $matches))
            {
                // preg_match returns useless indexes, we filter these out
                // we get a clean param array (eg. ['variableSymbol' => '20260101'])
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $controllerName = $route['controller']; // eg. Controllers\InvoiceController
                $actionName = $route['action'];         // eg. "detail"

                $controller = new $controllerName($db); // instantiate controller

                // dynamically call the action in controller
                // spread operator and array_values extracts the values from the array, excluding the keys
                $controller->$actionName(...array_values($params)); // eg. (from previous ex.) $controller->detail('20260101')

                return;
            }
        }

        http_response_code(404);
        // TODO: redirect to page error 404
        echo "Error 404: NOT FOUND";
    }
}