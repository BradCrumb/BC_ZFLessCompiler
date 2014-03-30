<?php
namespace BC_ZFLessCompiler;

use Zend\ModuleManager\ModuleManager;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\Stdlib\Hydrator\ClassMethods;
use BC_ZFLessCompiler\Compiler\Less as LessCompiler;

use Zend\Mvc\MvcEvent;

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    ServiceProviderInterface {

	public function getAutoloaderConfig() {
		return array(
			'Zend\Loader\ClassMapAutoloader' => array(
				__DIR__ . '/autoload_classmap.php',
			),
			'Zend\Loader\StandardAutoloader' => array(
				'namespaces' => array(
					__NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
				),
			),
		);
	}

	public function getConfig() {
		return include __DIR__ . '/config/module.config.php';
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

    // http://www.cnblogs.com/wkpilu/p/how_to_write_zf2_module.html < ook een optie als onderstaande bout is
    public function onBootstrap(\Zend\EventManager\Event $event) { 
        $eventManager = $event->getApplication()->getEventManager();
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, array($this, 'runCompiler'));
    }

    public function runCompiler($event) {
        $serviceManager = $event->getApplication()->getServiceManager();
        $config = $serviceManager->get(__NAMESPACE__ . 'Config');

        $lessCompiler = new LessCompiler($config);
        //$lessCompiler->processModules($serviceManager);
        $lessCompiler->setEnforcement(
            $event->getRequest()->getQuery(LessCompiler::QUERY_PARAM_ENFORCEMENT, false)
        );
        $lessCompiler->run();
    }
}