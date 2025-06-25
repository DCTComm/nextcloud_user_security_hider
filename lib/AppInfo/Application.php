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
use OCP\IGroupManager;
use OCP\Template;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IURLGenerator;

class Application extends App implements IBootstrap {
	public const APP_ID = 'user_security_hider';

	public function __construct() {
		parent::__construct(self::APP_ID);

		// Get container and server
		$container = $this->getContainer();
		$server = $container->getServer();
		
		// Register for template rendering events to catch frontend navigation
		$dispatcher = $server->get(IEventDispatcher::class);
		$dispatcher->addListener(BeforeTemplateRenderedEvent::class, function(BeforeTemplateRenderedEvent $event) {
			try {
				$logger = $this->getContainer()->get(LoggerInterface::class);
				$request = $this->getContainer()->get(\OCP\IRequest::class);
				$userSession = $this->getContainer()->get(\OCP\IUserSession::class);
				$groupManager = $this->getContainer()->get(IGroupManager::class);
				
				$user = $userSession && $userSession->getUser() ? $userSession->getUser() : null;
				$userId = $user ? $user->getUID() : 'anonymous';
				$userGroups = $user ? array_map(function($group) { 
					return $group->getGID(); 
				}, $groupManager->getUserGroups($user)) : [];
				
				$path = $request->getPathInfo();
				$scriptName = $request->getScriptName();
				$requestUri = $request->getRequestUri();
				
				// Only process if it's a frontend page (not an API call)
				if (strpos($requestUri, '/ocs/') === false && 
					strpos($requestUri, '/remote.php/') === false && 
					strpos($requestUri, '/status.php') === false) {
					
					// Log the access attempt
					$logger->debug(
						sprintf('[DEBUG] Template rendering - User: %s, Groups: %s, Path: %s, Script: %s',
							$userId,
							implode(', ', $userGroups),
							$path,
							$scriptName
						),
						[
							'user' => $userId,
							'groups' => $userGroups,
							'path' => $path,
							'script' => $scriptName,
							'request_uri' => $requestUri,
							'timestamp' => date('c')
						]
					);

					// Restrict access to security settings for non-admin users
					if (strpos($path, '/settings/user/security') === 0 && !in_array('admin', $userGroups)) {
						$restrictedTemplate = new Template(self::APP_ID, 'restricted');
						$event->setTemplate($restrictedTemplate);
						return;
					}
				}
			} catch (\Exception $e) {
				$logger = $this->getContainer()->get(LoggerInterface::class);
				$logger->error('Error in template rendering: ' . $e->getMessage(), [
					'exception' => $e,
					'app' => self::APP_ID
				]);
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
