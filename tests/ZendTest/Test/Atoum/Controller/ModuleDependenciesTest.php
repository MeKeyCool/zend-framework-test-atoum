<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Test
 */
namespace ZendTest\Test\Atoum\Controller;

use Zend\Test\Atoum\Controller\AbstractHttpControllerTestCase;

/**
 * @category   Zend
 * @package    Zend_Test
 * @subpackage UnitTests
 * @group      Zend_Test
 */
class ModuleDependenciesTest extends AbstractHttpControllerTestCase
{
    public function __construct(score $score = null, locale $locale = null, adapter $adapter = null)
    {
        $namespace = substr(get_class($this), 0, strrpos(get_class($this), '\\'));
        $class = explode('\\', get_class($this));
        $className = end($class);
        $this->setTestNamespace($namespace);
        spl_autoload_register(function($class) use ($className) {
            if($class == $className) {
                eval("namespace{ class $className{}; }");
                return true;
            }
            return false;
        });
        parent::__construct($score, $locale, $adapter);
    }

    public function testDependenciesModules()
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../../_files/application.config.with.dependencies.php'
        );
        $sm = $this->getApplicationServiceLocator();
        $this->boolean($sm->has('FooObject'))->isEqualTo(true);
        $this->boolean($sm->has('BarObject'))->isEqualTo(true);

        $this->assertModulesLoaded(array('Foo', 'Bar'));

        $self = $this;
        $this->exception(function() use ($self) {$self->assertModulesLoaded(array('Foo', 'Bar', 'Unknow')); })
                ->isInstanceOf('Zend\Test\Atoum\Exception\ExpectationFailedException');
    }

    public function testBadDependenciesModules()
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../../_files/application.config.with.dependencies.disabled.php'
        );
        $sm = $this->getApplicationServiceLocator();
        $this->boolean($sm->has('FooObject'))->isEqualTo(false);
        $this->boolean($sm->has('BarObject'))->isEqualTo(true);

        $this->assertNotModulesLoaded(array('Foo'));

        $self = $this;
        $this->exception(function() use ($self) {$self->assertNotModulesLoaded(array('Foo', 'Bar')); })
                ->isInstanceOf('Zend\Test\Atoum\Exception\ExpectationFailedException');
    }
}
