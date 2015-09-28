<?php
namespace Ergy\Slim\Annotations;

class RouteInfo
{
    public $method;
    public $controller;
    public $action;
    public $params;

    public function __construct($method, $controller, $action, $params)
    {
        $this->method = $method;
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
    }
}