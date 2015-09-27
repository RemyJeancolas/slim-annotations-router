<?php
namespace Ergy\Slim\Annotations;

class RouteInfo
{
    protected $method;
    protected $controller;
    protected $action;
    protected $params;

    public function __construct($method, $controller, $action, $params)
    {
        $this->method = $method;
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
    }
}