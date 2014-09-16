<?php
namespace BC_ZFLessCompiler;

use Zend\Console\Console;
use Zend\ModuleManager\ModuleManager;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\Stdlib\Hydrator\ClassMethods;
use BC_ZFLessCompiler\Compiler\Less as LessCompiler;
use Zend\View\ViewEvent;
use Zend\Mvc\MvcEvent;

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    ServiceProviderInterface {

        private $hasRun = false;

	public function getAutoloaderConfig() {
		return array(
			'Zend\Loader\ClassMapAutoloader' => array(
				__DIR__ . '/../../autoload_classmap.php',
			),
			'Zend\Loader\StandardAutoloader' => array(
				'namespaces' => array(
					__NAMESPACE__ => __DIR__,
				),
			),
		);
	}

	public function getConfig() {
		return include __DIR__ . '/../../config/module.config.php';
	}

    public function getServiceConfig() {
        return array(
            'factories' => array(
                __NAMESPACE__ . 'Config' => function ($sm) {
                    $config = $sm->get('Config');

                    return isset($config[__NAMESPACE__]) ? $config[__NAMESPACE__]: array();
                },
            )
        );
    }

    public function onBootstrap(\Zend\EventManager\Event $event) {
        $eventManager = $event->getApplication()->getEventManager()->getSharedManager();;

	if (!Console::isConsole()) {
            $eventManager->attach('Zend\View\View', ViewEvent::EVENT_RENDERER_POST, function() use ($event) {
                $this->runCompiler($event);
            });
	}
    }

    public function runCompiler($event) {
        if (!$this->hasRun) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $config = $serviceManager->get(__NAMESPACE__ . 'Config');

            $lessCompiler = new LessCompiler($config);
            if ($event->getRequest() instanceof \Zend\Http\PhpEnvironment\Request) {
                $lessCompiler->setEnforcement(
                    $event->getRequest()->getQuery(LessCompiler::QUERY_PARAM_ENFORCEMENT, false)
                );
            }
            $lessCompiler->run();

            $this->hasRun = true;
        }
    }
}
