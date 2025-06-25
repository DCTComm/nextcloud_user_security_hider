<?php

declare(strict_types=1);

namespace OCA\UserSecurityHider\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\IEventDispatcher;

class Application extends App implements IBootstrap {
	public const APP_ID = 'user_security_hider';

	public function __construct() {
		parent::__construct(self::APP_ID);

		// Get container and server
		$container = $this->getContainer();
		$server = $container->getServer();
		
		// Register for template rendering events to catch frontend navigation
		$dispatcher = $server->get(IEventDispatcher::class);
		$dispatcher->addListener(BeforeTemplateRenderedEvent::class, function() {
			$logger = $this->getContainer()->get(LoggerInterface::class);
			$request = $this->getContainer()->get(\OCP\IRequest::class);
			$userSession = $this->getContainer()->get(\OCP\IUserSession::class);
			
			$userId = $userSession && $userSession->getUser() ? $userSession->getUser()->getUID() : 'anonymous';
			$path = $request->getPathInfo();
			$scriptName = $request->getScriptName();
			$requestUri = $request->getRequestUri();
			
			// Only log if it's a frontend page (not an API call)
			if (strpos($requestUri, '/ocs/') === false && 
				strpos($requestUri, '/remote.php/') === false && 
				strpos($requestUri, '/status.php') === false) {
				$logger->debug(
					sprintf('[DEBUG] Template rendering - User: %s, Path: %s, Script: %s',
						$userId,
						$path,
						$scriptName
					),
					[
						'user' => $userId,
						'path' => $path,
						'script' => $scriptName,
						'request_uri' => $requestUri,
						'timestamp' => date('c')
					]
				);
			}
		});
	}

	public function register(IRegistrationContext $context): void {
		// Empty registration as we don't need any services
	}

	public function boot(IBootContext $context): void {
		// Additional boot initialization if needed
	}
}
