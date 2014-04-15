<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Test
 */
namespace Zend\Test\Atoum\Controller;


use mageekguy\atoum;
use Zend\Console\Console;
use Zend\Dom;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\View\SendResponseListener;
use Zend\Test\Atoum\Exception\ExpectationFailedException;
use Zend\Uri\Http as HttpUri;
use Zend\Stdlib\Parameters;

/**
 * @category   Zend
 * @package    Zend_Test
 * @subpackage Atoum
 */
class AbstractControllerTestCase extends atoum\test
{
    /**
     * @var Zend\Mvc\ApplicationInterface
     */
    private $application;

    /**
     * @var array
     */
    private $applicationConfig;

    /**
     * Flag to use console router or not
     * @var boolean
     */
    protected $useConsoleRequest = false;

    /**
     * Set the usage of the console router or not
     * @param boolean $boolean
     */
    public function setUseConsoleRequest($boolean)
    {
        $this->useConsoleRequest = (boolean)$boolean;
    }

    /**
     * Set the application config
     * @param array $applicationConfig
     */
    public function setApplicationConfig($applicationConfig)
    {
        $this->applicationConfig = $applicationConfig;
    }

    /**
     * Get the application object
     * @return Zend\Mvc\ApplicationInterface
     */
    public function getApplication()
    {
        if(null === $this->application) {
            $appConfig = $this->applicationConfig;
            if(!$this->useConsoleRequest) {
                $consoleServiceConfig = array(
                    'service_manager' => array(
                        'factories' => array(
                          'ServiceListener' => 'Zend\Mvc\Service\ServiceListenerFactory',
                        ),
                    ),
                );
                $appConfig = array_replace_recursive($appConfig, $consoleServiceConfig);
            }

            Console::overrideIsConsole($this->useConsoleRequest);
            $this->application = Application::init($appConfig);

            $events = $this->application->getEventManager();
            foreach($events->getListeners(MvcEvent::EVENT_FINISH) as $listener) {
                $callback = $listener->getCallback();
                if(is_array($callback) && $callback[0] instanceof SendResponseListener) {
                    $events->detach($listener);
                }
            }
        }
        return $this->application;
    }

    /**
     * Get the service manager of the application object
     * @return Zend\ServiceManager\ServiceManager
     */
    public function getApplicationServiceLocator()
    {
        return $this->getApplication()->getServiceManager();
    }

    /**
     * Get the application request object
     * @return \Zend\Stdlib\RequestInterface
     */
    public function getRequest()
    {
        return $this->getApplication()->getRequest();
    }

    /**
     * Get the application response object
     * @return Zend\Stdlib\ResponseInterface
     */
    public function getResponse()
    {
        return $this->getApplication()->getResponse();
    }

    /**
     * Set the request URL
     *
     * @param string $url
     */
    public function url($url)
    {
        $request = $this->getRequest();
        if($this->useConsoleRequest) {
            $params = preg_split('#\s+#', $url);
            $request->params()->exchangeArray($params);
        } else {
            $uri = new HttpUri($url);
            $query = $uri->getQuery();
            if($query) {
                parse_str($query, $args);
                $request->setQuery(new Parameters($args));
            }
            $request->setUri($uri);
        }
    }

    /**
     * Dispatch the MVC with an URL
     * Accept a HTTP (simulate a customer action) or console route.
     *
     * The URL provided set the request URI in the request object.
     *
     * @param string $url
     */
    public function dispatch($url)
    {
        $this->url($url);
        $this->getApplication()->run();
    }

    /**
     * Reset the request
     * @return AbstractControllerTestCase
     */
    public function reset()
    {
        // initiate the request object to authorize multi dispatch
        $request = $this->getRequest();
        if($this->useConsoleRequest) {
            $request->params()->exchangeArray(array());
        } else {
            $request->setQuery(new Parameters(array()));
            $request->setPost(new Parameters(array()));
            $request->setFiles(new Parameters(array()));
            $request->setCookies(new Parameters(array()));
            $request->setServer(new Parameters($_SERVER));
        }
        return $this;
    }

    /**
     * Trigger an application event
     *
     * @param string $eventName
     * @return Zend\EventManager\ResponseCollection
     */
    public function triggerApplicationEvent($eventName)
    {
        $events = $this->getApplication()->getEventManager();
        $event = $this->getApplication()->getMvcEvent();

        if($eventName == MvcEvent::EVENT_ROUTE || $eventName == MvcEvent::EVENT_DISPATCH) {
            $shortCircuit = function ($r) use ($event) {
                if ($r instanceof ResponseInterface) {
                    return true;
                }
                if ($event->getError()) {
                    return true;
                }
                return false;
            };
            return $events->trigger($eventName, $event, $shortCircuit);
        }
        return $events->trigger($eventName, $event);
    }

    /**
     * Assert the modules loaded with the module manager
     *
     * @param  array $modules
     * @return void
     */
    public function assertModulesLoaded(array $modules)
    {
        $moduleManager = $this->getApplicationServiceLocator()->get('ModuleManager');
        $modulesLoaded = $moduleManager->getModules();
        $list = array_diff($modules, $modulesLoaded);
        if($list) {
            throw new ExpectationFailedException(sprintf(
                'Several modules are not loaded "%s"', implode(', ', $list)
            ));
        }
        $this->array($list)->isEmpty();
    }

    /**
     * Assert the modules loaded with the module manager
     *
     * @param  array $modules
     * @return void
     */
    public function assertNotModulesLoaded(array $modules)
    {
        $moduleManager = $this->getApplicationServiceLocator()->get('ModuleManager');
        $modulesLoaded = $moduleManager->getModules();
        $list = array_intersect($modules, $modulesLoaded);
        if($list) {
            throw new ExpectationFailedException(sprintf(
                'Several modules WAS not loaded "%s"', implode(', ', $list)
            ));
        }
        $this->array($list)->isEmpty();
    }

    protected function getResponseStatusCode()
    {
        $response = $this->getResponse();
        if($this->useConsoleRequest) {
            $match = $response->getErrorLevel();
            if(null === $match) {
                $match = 0;
            }
            return $match;
        }
        return $response->getStatusCode();
    }

    /**
     * Assert response status code
     *
     * @param  int $code
     * @return void
     */
    public function assertResponseStatusCode($code)
    {
        if($this->useConsoleRequest) {
            if(!in_array($code, array(0, 1))) {
                throw new ExpectationFailedException(
                    'Console status code assert value must be O (valid) or 1 (error)'
                );
            }
        }
        $match = $this->getResponseStatusCode();
        if($code != $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting response code "%s", actual status code is "%s"',
                $code,
                $match
            ));
        }
        $this->integer($code)->isEqualTo($match);
    }

    /**
     * Assert response status code
     *
     * @param  int $code
     * @return void
     */
    public function assertNotResponseStatusCode($code)
    {
        if($this->useConsoleRequest) {
            if(!in_array($code, array(0, 1))) {
                throw new ExpectationFailedException(
                    'Console status code assert value must be O (valid) or 1 (error)'
                );
            }
        }
        $match = $this->getResponseStatusCode();
        if($code == $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting response code was NOT "%s"',
                $code
            ));
        }
        $this->integer($code)->isNotEqualTo($match);
    }

    protected function getControllerFullClassName()
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $controllerIdentifier = $routeMatch->getParam('controller');
        $controllerManager = $this->getApplicationServiceLocator()->get('ControllerLoader');
        $controllerClass = $controllerManager->get($controllerIdentifier);
        return get_class($controllerClass);
    }

    /**
     * Assert that the application route match used the given module
     *
     * @param  string $module
     * @return void
     */
    public function assertModule($module)
    {
        $controllerClass = $this->getControllerFullClassName();
        $match = substr($controllerClass, 0, strpos($controllerClass, '\\'));
        $match = strtolower($match);
        $module = strtolower($module);
        if($module != $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting module "%s", actual module is "%s"',
                $module,
                $match
            ));
        }
        $this->string($module)->isEqualTo($match);
    }

    /**
     * Assert that the application route match used NOT the given module
     *
     * @param  string $module
     * @return void
     */
    public function assertNotModule($module)
    {
        $controllerClass = $this->getControllerFullClassName();
        $match = substr($controllerClass, 0, strpos($controllerClass, '\\'));
        $match = strtolower($match);
        $module = strtolower($module);
        if($module == $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting module was NOT "%s"',
                $module
            ));
        }
        $this->string($module)->isNotEqualTo($match);
    }

    /**
     * Assert that the application route match used the given controller class
     *
     * @param  string $controller
     * @return void
     */
    public function assertControllerClass($controller)
    {
        $controllerClass = $this->getControllerFullClassName();
        $match = substr($controllerClass, strrpos($controllerClass, '\\')+1);
        $match = strtolower($match);
        $controller = strtolower($controller);
        if($controller != $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting controller class "%s", actual controller class is "%s"',
                $controller,
                $match
            ));
        }
        $this->string($controller)->isEqualTo($match);
    }

    /**
     * Assert that the application route match used NOT the given controller class
     *
     * @param  string $controller
     * @return void
     */
    public function assertNotControllerClass($controller)
    {
        $controllerClass = $this->getControllerFullClassName();
        $match = substr($controllerClass, strrpos($controllerClass, '\\')+1);
        $match = strtolower($match);
        $controller = strtolower($controller);
        if($controller == $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting controller class was NOT "%s"',
                $controller
            ));
        }
        $this->string($controller)->isNotEqualTo($match);
    }

    /**
     * Assert that the application route match used the given controller name
     *
     * @param  string $controller
     * @return void
     */
    public function assertControllerName($controller)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $match = $routeMatch->getParam('controller');
        $match = strtolower($match);
        $controller = strtolower($controller);
        if($controller != $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting controller name "%s", actual controller is "%s"',
                $controller,
                $match
            ));
        }
        $this->string($controller)->isEqualTo($match);
    }

    /**
     * Assert that the application route match used NOT the given controller name
     *
     * @param  string $controller
     * @return void
     */
    public function assertNotControllerName($controller)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $match = $routeMatch->getParam('controller');
        $match = strtolower($match);
        $controller = strtolower($controller);
        if($controller == $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting controller name was NOT "%s"',
                $controller
            ));
        }
        $this->string($controller)->isNotEqualTo($match);
    }

    /**
     * Assert that the application route match used the given action
     *
     * @param  string $action
     * @return void
     */
    public function assertActionName($action)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $match = $routeMatch->getParam('action');
        $match = strtolower($match);
        $action = strtolower($action);
        if($action != $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting action name "%s", actual action is "%s"',
                $action,
                $match
            ));
        }
        $this->string($action)->isEqualTo($match);
    }

    /**
     * Assert that the application route match used NOT the given action
     *
     * @param  string $action
     * @return void
     */
    public function assertNotActionName($action)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $match = $routeMatch->getParam('action');
        $match = strtolower($match);
        $action = strtolower($action);
        if($action == $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting action name was NOT "%s"',
                $action
            ));
        }
        $this->string($action)->isNotEqualTo($match);
    }

    /**
     * Assert that the application route match used the given route name
     *
     * @param  string $route
     * @return void
     */
    public function assertMatchedRouteName($route)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $match = $routeMatch->getMatchedRouteName();
        $match = strtolower($match);
        $route = strtolower($route);
        if($route != $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting matched route was "%s", actual route is "%s"',
                $route,
                $match
            ));
        }
        $this->string($route)->isEqualTo($match);
    }

    /**
     * Assert that the application route match used NOT the given route name
     *
     * @param  string $route
     * @return void
     */
    public function assertNotMatchedRouteName($route)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $match = $routeMatch->getMatchedRouteName();
        $match = strtolower($match);
        $route = strtolower($route);
        if($route == $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting route matched was NOT "%s"', $route
            ));
        }
        $this->string($route)->isNotEqualTo($match);
    }

    /**
     * Execute a dom query
     * @param string $path
     * @return array
     */
    protected function query($path)
    {
        $response = $this->getResponse();
        $dom = new Dom\Query($response->getContent());
        $result = $dom->execute($path);
        return $result;
    }

    /**
     * Count the dom query executed
     * @param string $path
     * @return integer
     */
    protected function queryCount($path)
    {
        return count($this->query($path));
    }

    /**
     * Assert against DOM selection
     *
     * @param  string $path CSS selector path
     * @return void
     */
    public function assertQuery($path)
    {
        $match = $this->queryCount($path);
        if(!$match > 0) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s EXISTS', $path
            ));
        }
        $this->integer($match)->isGreaterThan(0);
    }

    /**
     * Assert against DOM selection
     *
     * @param  string $path CSS selector path
     * @return void
     */
    public function assertNotQuery($path)
    {
        $match = $this->queryCount($path);
        if($match != 0) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s DOES NOT EXIST', $path
            ));
        }
        $this->integer($match)->isEqualTo(0);
    }

    /**
     * Assert against DOM selection; should contain exact number of nodes
     *
     * @param  string $path CSS selector path
     * @param  string $count Number of nodes that should match
     * @return void
     */
    public function assertQueryCount($path, $count)
    {
        $match = $this->queryCount($path);
        if($match != $count) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s OCCURS EXACTLY %d times',
                $path, $count
            ));
        }
        $this->integer($count)->isEqualTo($match);
    }

    /**
     * Assert against DOM selection; should NOT contain exact number of nodes
     *
     * @param  string $path CSS selector path
     * @param  string $count Number of nodes that should NOT match
     * @return void
     */
    public function assertNotQueryCount($path, $count)
    {
        $match = $this->queryCount($path);
        if($match == $count) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s DOES NOT OCCUR EXACTLY %d times',
                $path, $count
            ));
        }
        $this->integer($count)->isNotEqualTo($match);
    }

    /**
     * Assert against DOM selection; should contain at least this number of nodes
     *
     * @param  string $path CSS selector path
     * @param  string $count Minimum number of nodes that should match
     * @return void
     */
    public function assertQueryCountMin($path, $count)
    {
        $match = $this->queryCount($path);
        if($match < $count) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s OCCURS AT LEAST %d times',
                $path, $count
            ));
        }
        $this->integer($match)->isGreaterThanOrEqualTo($count);
    }

    /**
     * Assert against DOM selection; should contain no more than this number of nodes
     *
     * @param  string $path CSS selector path
     * @param  string $count Maximum number of nodes that should match
     * @return void
     */
    public function assertQueryCountMax($path, $count)
    {
        $match = $this->queryCount($path);
        if($match > $count) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s OCCURS AT MOST %d times',
                $path, $count
            ));
        }
        $this->integer($match)->isLessThanOrEqualTo($count);
    }

    /**
     * Assert against DOM selection; node should contain content
     *
     * @param  string $path CSS selector path
     * @param  string $match content that should be contained in matched nodes
     * @return void
     */
    public function assertQueryContentContains($path, $match)
    {
        $result = $this->query($path);
        if($result->count() == 0) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s EXISTS', $path
            ));
        }
        if($result->current()->nodeValue != $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node denoted by %s CONTAINS content "%s"',
                $result->current()->nodeValue, $match
            ));
        }
        $this->string($result->current()->nodeValue)->isEqualTo($match);
    }

    /**
     * Assert against DOM selection; node should NOT contain content
     *
     * @param  string $path CSS selector path
     * @param  string $match content that should NOT be contained in matched nodes
     * @return void
     */
    public function assertNotQueryContentContains($path, $match)
    {
        $result = $this->query($path);
        if($result->count() == 0) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s EXISTS', $path
            ));
        }
        if($result->current()->nodeValue == $match) {
            throw new ExpectationFailedException(sprintf(
                'Failed asserting node DENOTED BY %s DOES NOT CONTAIN content "%s"',
                $result->current()->nodeValue, $match
            ));
        }
        $this->string($result->current()->nodeValue)->isNotEqualTo($match);
    }
}
