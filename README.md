Zend Framework 2 Controller Test with Atoum
==============

Version 1.0.0 Created by [Vincent Blanchon](http://developpeur-zend-framework.fr/)

Introduction
------------

This repository provide a library to use Atoum with your controllers and modules.

Use case with http request :

```php
use Zend\Test\Atoum\Controller\AbstractHttpControllerTestCase;

class IndexControllerTest extends AbstractHttpControllerTestCase
{    
    public function beforeTestMethod()
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../../../config/application.config.php'
        );
        parent::beforeTestMethod();
    }
    
    public function testCanDisplayIndex()
    {
        // dispatch url
        $this->dispatch('/');
        
        // basic assertions
        $this->assertResponseStatusCode(200);
        $this->assertActionName('index');
        $this->assertControllerName('application-index');
        $this->assertMatchedRouteName('home');
        $this->assertQuery('div[class="container"]');
        $this->assertNotQuery('#form');
        $this->assertQueryCount('div[class="container"]', 2);
        
        // custom assert
        $sm = $this->getApplicationServiceLocator();
        // ... here my asserts with atoum ...
    }
}
```

Use case with console request :

```php
use Zend\Test\Atoum\Controller\AbstractConsoleControllerTestCase;

class CrawlControllerTest extends AbstractConsoleControllerTestCase
{    
    public function beforeTestMethod()
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../../../config/application.config.php'
        );
        parent::beforeTestMethod();
    }
    
    public function testCrawlTweet()
    {
        // dispatch url
        $this->dispatch('--crawl-tweet');
        
        // basic assertions
        $this->assertResponseStatusCode(0);
        $this->assertActionName('tweet');
        $this->assertControllerName('cron-crawl');
        
        // custom assert
        $sm = $this->getApplicationServiceLocator();
        // ... here my asserts with atoum ...
    }
}
```
