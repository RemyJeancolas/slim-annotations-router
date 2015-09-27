<?php
namespace Ergy\Slim\Annotations;

abstract class Controller
{
    public function __get($name)
    {
        return Router::getSlim()->getContainer()->get($name);
    }
}
