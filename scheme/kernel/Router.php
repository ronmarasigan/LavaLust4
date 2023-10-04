<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @copyright Copyright 2020 (https://ronmarasigan.github.io)
 * @since Version 1
 * @link https://lavalust.pinoywap.org
 * @license https://opensource.org/licenses/MIT MIT License
 */

/**
 * Class Router
 */
class Router
{
    /**
     * List of routes
     *
     * @var array
     */
    private $routes = [];

    /**
     * Group routes
     *
     * @var string
     */
    private $group_prefix = '';

    /**
     * GET Method
     *
     * @param string $url
     * @param mixed $callback
     * @return void
     */
    public function get($url, $callback)
    {
        $this->add_route($url, $callback, 'GET');
        return $this;
    }

    /**
     * POST Method
     *
     * @param string $url
     * @param mixed $callback
     * @return void
     */
    public function post($url, $callback)
    {
        $this->add_route($url, $callback, 'POST');
        return $this;
    }

    /**
     * PUT Method
     *
     * @param string $url
     * @param mixed $callback
     * @return void
     */
    public function put($url, $callback)
    {
        $this->add_route($url, $callback, 'PUT');
        return $this;
    }

    /**
     * PATCH Method
     *
     * @param string $url
     * @param mixed $callback
     * @return void
     */
    public function patch($url, $callback)
    {
        $this->add_route($url, $callback, 'PATCH');
        return $this;
    }

    /**
     * DELETE Method
     *
     * @param string $url
     * @param mixed $callback
     * @return void
     */
    public function delete($url, $callback)
    {
        $this->add_route($url, $callback, 'DELETE');
        return $this;
    }

    /**
     * Match any method
     *
     * @param string $url
     * @param mixed $callback
     * @param string $methods
     * @return void
     */
    public function match($url, $callback, $methods)
    {
        $this->add_route($url, $callback, $methods);
        return $this;
    }

    /**
     * Grouping Routes
     *
     * @param string $prefix
     * @param mixed $callback
     * @return void
     */
    public function group($prefix, $callback)
    {
        // Check if the URL starts with a slash and add one if needed
        if (strpos($prefix, '/') !== 0) {
			$prefix = '/' . $prefix;
		}
        $previous_group_prefix = $this->group_prefix;
        $this->group_prefix .= $prefix;

        call_user_func($callback);

        $this->group_prefix = $previous_group_prefix;
    }

    /**
     * Adding Routes
     *
     * @param string $url
     * @param mixed $callback
     * @param string $method
     * @return void
     */
    private function add_route($url, $callback, $method = 'GET', $name = NULL)
    {
        // Check if the URL starts with a slash and add one if needed
		if (strpos($url, '/') !== 0) {
			$url = '/' . $url;
		}
        
        if(is_string($method)) {
            $methods = explode('|', strtoupper($method));
        } else {
            $methods = $method;
        }
        
        foreach ($methods as $method) {
            $route = [
                'url' => $this->group_prefix . $this->sanitize_url($url),
                'callback' => $callback,
                'method' => $method,
                'name' => $name,
            ];
            $this->routes[] = $route;
        }
        
    }

    /**
     * Initiate Request
     *
     * @param string $url
     * @param string $method
     * @return void
     */
    public function initiate($url, $method)
    {
        //check for invalid chars
        $url_segments = explode('/', $url);
        array_shift($url_segments);
        foreach($url_segments as $uri)
        {
            if (! preg_match('/^['.config_item('permitted_uri_chars').']+$/i', $uri))
            {
                show_error('400 Bad Request', 'The URI you submitted has disallowed characters.', 'error_general', 400);
            }
        }
        foreach ($this->routes as $route) {
            if (strtoupper($route['method']) === strtoupper($method)) {
                
                //Regex
                $pattern = $this->convert_to_regex_pattern($route['url']);

                if (preg_match($pattern, $url, $matches)) {
                    array_shift($matches); // Remove the first element (full match)

                    $callback = $route['callback'];

                    if (is_string($callback)) {
                        if(strpos($callback, '::') !== false) {
                            [$controller, $method] = explode('::', $callback);
                        } else {
                            [$controller, $method] = [$callback, 'index'];                           
                        }
                        $app = APP_DIR .'controllers/'. ucfirst($controller) . '.php';
                        if(file_exists($app)){
                            require_once($app);
                            $this->call_controller_method($controller, $method, $matches);
                        } else {
                            show_error('Runtime Error', 'Controller file did not exist.');
                        }
                    } elseif (is_callable($callback)) {
                        call_user_func_array($callback,  array_values($matches));
                    } else {
                        throw new RuntimeException('Invalid callback.');
                    }
                    return;
                }
            }
        }
        empty(config_item('404_override')) ? show_404() : show_404('Route Not Found', "Route not found: $url", config_item('404_override'));
    }

    /**
     * Call the Controller and Method
     *
     * @param string $controller
     * @param string $method
     * @param mixed $params
     * @return void
     */
    private function call_controller_method($controller, $method, $params)
    {
        $controller_instance = new $controller();

        if ($this->is_method_accessible($controller_instance, $method)) {
            call_user_func_array([$controller_instance, $method], array_values($params));
        } else {
            throw new RuntimeException('Method is inaccessible.');
        }
    }

    /**
     * Check if Method is Accessible
     *
     * @param object $object
     * @param string $method
     * @return boolean
     */
    private function is_method_accessible($controller, $method)
    {
        return is_object($controller) && method_exists($controller, $method) && is_callable([$controller, $method]);
    }

    /**
     * Regex Pattern
     *
     * @param string $url
     * @return void
     */
    private function convert_to_regex_pattern($url)
    {
        $pattern = preg_replace_callback('/\(([^)]+)\)/', function($matches) {
            $param = $matches[1];
            if (strpos($param, ':num') === 0) {
                return '(\d+)';
            } elseif (strpos($param, ':any') === 0) {
                return '([^/]+)';
            } elseif (strpos($param, ':alpha') === 0) {
                return '([a-zA-Z]+)';
            } elseif (strpos($param, ':alphanum') === 0) {
                return '([a-zA-Z0-9]+)';
            } elseif (strpos($param, ':') === 0) {
                return '(' . substr($param, 1) . ')';
            }
            return $param;
        }, $url);
        return '#^' . $pattern . '$#';
    }

    /**
     * Sanitize URL
     *
     * @param string $url
     * @return void
     */
    public function sanitize_url($url)
    {
        // Remove trailing slashes
        $url = rtrim($url, '/');
        // Remove special characters
        $url = filter_var($url, FILTER_SANITIZE_URL);

        return $url;
    }

    /**
     * Set name of routes
     *
     * @param string $name
     * @return void
     */
    public function name($name)
    {
        $last_route = end($this->routes);
        $last_route['name'] = $name;
        $this->routes[key($this->routes)] = $last_route;
        return $this;
    }

    /**
     * Get route by name
     *
     * @param string $name
     * @return void
     */
    public function route_name($name)
    {
        foreach ($this->routes as $route) {
            if ($route['name'] === $name) {
                return $route;
            }
        }
        return null;
    }

    /**
     * Check if route exist
     *
     * @param string $name_of_url
     * @return void
     */
    public function route_exists($name_of_url)
    {
        foreach ($this->routes as $route) {
            if ($route['name'] === $name_of_url || $route['url'] === $name_of_url) {
                return true;
            }
        }
        return false;
    }

}
?>