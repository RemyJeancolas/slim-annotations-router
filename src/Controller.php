<?php
namespace Ergy\Annotations;

abstract class Controller
{
    public function __get($name)
    {
        return Router::getSlim()->getContainer()->get($name);
    }
}
