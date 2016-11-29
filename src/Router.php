<?php
namespace Ergy\Slim\Annotations;

use FastRoute\DataGenerator;
use FastRoute\RouteParser;
use Psr\Http\Message\ResponseInterface;

class Router extends \Slim\Router
{
    private $_routesFile = 'routes.php';
    private $_controllersFile = 'controllers.php';
    private $_usedControllers = [];
    private $_controllerDirs = null;
    private $_cacheDir = null;
    private static $_slimInstance = null;

    /**
     * @param \Slim\App $slimInstance
     * @param string|string[] $controllerDirs
     * @param string $cacheDir
     * @throws \Exception
     */
    public function __construct(\Slim\App $slimInstance, $controllerDirs, $cacheDir, RouteParser $parser = null, DataGenerator $generator = null)
    {
        parent::__construct($parser, $generator);

        // We save current Slim instance
        self::$_slimInstance = $slimInstance;

        // We save controller dirs
        if (is_string($controllerDirs)) {
            $controllerDirs = [ $controllerDirs ];
        }

        if (!is_array($controllerDirs)) {
            throw new \InvalidArgumentException('Controllers directory must be either string or array');
        }

        $this->_controllerDirs = [];
        foreach ($controllerDirs as $d) {
            $realPath = realPath($d);
            if ($realPath !== false) {
                $this->_controllerDirs[] = $realPath;
            }
        }

        // We save the cache dir
        if (!is_dir($cacheDir)) {
            $result = @mkdir($cacheDir, 0777, true);
            if ($result === false) {
                throw new \RuntimeException('Can\'t create cache directory');
            }
        }

        if (!is_writable($cacheDir)) {
            throw new \RuntimeException('Cache directory must be writable by web server');
        }

        $this->_cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        $this->_generateRoutes();
    }

    /**
     * @return \Slim\App
     */
    public static function getSlim()
    {
        return self::$_slimInstance;
    }

    /**
     * @param string $calledClass
     * @param string $calledMethod
     * @param array $args
     * @return bool|\Psr\Http\Message\ResponseInterface
     */
    public function triggerControllerAction($calledClass, $calledMethod, $args)
    {
        try {
            $controller =  self::$_slimInstance->getContainer()->get($calledClass);
        } catch (\Exception $e) {
            $controller = new $calledClass;
        }
        
        $routeInfo = null;
        if (method_exists($controller, 'beforeExecuteRoute')) {
            $routeInfo = new RouteInfo(self::$_slimInstance->getContainer()->get('request')->getMethod(), $calledClass, $calledMethod, $args);
            $result = $controller->beforeExecuteRoute($routeInfo);
            if ($result === false || $result instanceof ResponseInterface) {
                return $result;
            }
        }

        if (count($args) > 0) {
            $result = call_user_func_array(array($controller, $calledMethod), $args);
        } else {
            $result = call_user_func(array($controller, $calledMethod));
        }
        if ($result === false || $result instanceof ResponseInterface) {
            return $result;
        }

        if (method_exists($controller, 'afterExecuteRoute')) {
            if ($routeInfo === null) {
                $routeInfo = new RouteInfo(self::$_slimInstance->getContainer()->get('request')->getMethod(), $calledClass, $calledMethod, $args);
            }
            $result = $controller->afterExecuteRoute($routeInfo);
            if ($result instanceof ResponseInterface) {
                return $result;
            }
        }
    }

    private function _generateRoutes()
    {
        $parsingNeeded = !file_exists($this->_cacheDir.$this->_routesFile);

        // We look for controller files
        $files = $this->_findControllerFiles();

        // We check if there has been modifications since last cache generation
        if (!$parsingNeeded) {
            $routesCacheMtime = filemtime($this->_cacheDir.$this->_routesFile);
            foreach ($files as $file => $mtime) {
                if ($mtime > $routesCacheMtime) {
                    $parsingNeeded = true;
                    break;
                }
            }
        }

        // We look for deleted controller files
        if (!$parsingNeeded && file_exists($this->_cacheDir.$this->_controllersFile)) {
            require_once $this->_cacheDir.$this->_controllersFile;
            foreach ($this->_usedControllers as $controllerFile) {
                if (!file_exists($controllerFile)) {
                    $parsingNeeded = true;
                    break;
                }
            }
        }

        // We regenerate cache file if needed
        if ($parsingNeeded) {
            $controllerFiles = [];
            $commonFileContent = '<?php'."\r\n".'/**'."\r\n".' * Slim annotations router %s cache file, self generated on '.date('c')."\r\n".' */'."\r\n\r\n";
            $routesFileContent = sprintf($commonFileContent, 'routes');
            $controllersFileContent = sprintf($commonFileContent, 'controllers');
            foreach ($files as $file => $mtime) {
                // We generate routes for current file
                $content = $this->_parseFile($file);
                if ($content !== '') {
                    $routesFileContent .= $content;
                    $controllerFiles[] = $file;
                }
            }

            file_put_contents($this->_cacheDir.$this->_routesFile, $routesFileContent);

            $usedControllers = (count($controllerFiles) > 0) ? '$this->_usedControllers = [\''.join('\',\'', $controllerFiles).'\'];' : '';
            file_put_contents($this->_cacheDir.$this->_controllersFile, $controllersFileContent.$usedControllers);
        }

        require_once $this->_cacheDir.$this->_routesFile;
    }

    private function _findControllerFiles()
    {
        $result = [];
        foreach ($this->_controllerDirs as $dir) {
            $directoryIterator = new \RecursiveDirectoryIterator($dir);
            $iterator = new \RecursiveIteratorIterator($directoryIterator);
            $files = new \RegexIterator($iterator, '/^.+Controller\.php$/i', \RecursiveRegexIterator::GET_MATCH);
            foreach ($files as $k => $v) {
                $result[$k] = filemtime($k);
            }
        }
        return $result;
    }

    /**
     * @param string $file
     * @return string
     */
    private function _parseFile($file)
    {
        $result = '';

        // We load file content
        $content = file_get_contents($file);

        // We search for namespace
        $namespace = null;
        if (preg_match('/namespace\s+([\w\\\_-]+)/', $content, $matches) === 1) {
            $namespace = $matches[1];
        }

        // We look for class name
        if (preg_match('/class\s+([\w_-]+)/', $content, $matches) === 1) {
            $className = ($namespace !== null) ? $namespace.'\\'.$matches[1] : $matches[1];

            // We find class infos
            $reflector = new \ReflectionClass($className);
            $prefix = '';
            if (preg_match('/@RoutePrefix\(["\'](((?!(["\'])).)*)["\']\)/', $reflector->getDocComment(), $matches) === 1) {
                $prefix = $matches[1];
            }

            $methods = $reflector->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $m) {
                if (substr($m->name, -6) !== 'Action' || $m->isStatic()) {
                    continue;
                }

                if (preg_match('/@Route\(\s*["\']([^\'"]*)["\'][^)]*\)/', $m->getDocComment(), $matches) === 1) {
                    $routePath = $matches[1];

                    $route = $matches[0];
                    $methods = '\'GET\'';
                    if (preg_match('/methods={([^}]*)}/', $route, $matches) === 1) {
                        $methods = str_replace('"', "'", $matches[1]);
                    }

                    $routeName = null;
                    if (preg_match('/name=[\'"]([\w\._-]+)["\']/', $route, $matches)) {
                        $routeName = $matches[1];
                    }

                    $result .= '$this->map(['.$methods.'], \''.$prefix.$routePath.'\', function($request, $response, $args) { return $this->triggerControllerAction(\''.$className.'\', \''.$m->name.'\', $args); })';
                    if ($routeName !== null) {
                        $result .= '->setName(\''.$routeName.'\')';
                    }
                    $result .= ';'."\r\n";
                }
            }
        }

        return $result;
    }
}
