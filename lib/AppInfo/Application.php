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

	/**
	 * Array of paths that should be restricted for non-admin users
	 */
	protected $restrictedPaths = [
		// Add restricted paths here
		'/settings/user/security',
		'/settings/user/sync-clients',
		'/apps/files/trashbin',
	];

	public function __construct() {
		parent::__construct(self::APP_ID);

		// Get container and server
		$container = $this->getContainer();
		$server = $container->getServer();
		
		// Register for template rendering events to catch frontend navigation
		$dispatcher = $server->get(IEventDispatcher::class);
		$dispatcher->addListener(BeforeTemplateRenderedEvent::class, function(BeforeTemplateRenderedEvent $event) {
			$logger = $this->getContainer()->get(LoggerInterface::class);
			$request = $this->getContainer()->get(\OCP\IRequest::class);
			$userSession = $this->getContainer()->get(\OCP\IUserSession::class);
			$groupManager = $this->getContainer()->get(IGroupManager::class);
			$urlGenerator = $this->getContainer()->get(IURLGenerator::class);
			
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

				// Example: Restrict access to settings pages for non-admin users
				foreach ($this->restrictedPaths as $restrictedPath) {
					if (strpos($path, $restrictedPath) === 0 && !in_array('admin', $userGroups)) {
						// $restrictedTemplate = new Template(self::APP_ID, 'restricted');
						// $event->setTemplate($restrictedTemplate);

						// Option 1: Redirect to home page
						header('Location: ' . $urlGenerator->linkToRoute('files.view.index'));
						exit();
						
						// Option 2: Show an error template
						// $errorTemplate = new Template(self::APP_ID, 'error');
						// $errorTemplate->assign('message', 'Access denied');
						// $event->setTemplate($errorTemplate);
						
						// Option 3: Just prevent access with a simple message
						// die('Access denied');
					}
				}

				// Example: Modify template content for specific user groups
				// if (in_array('restricted_view', $userGroups)) {
				// 	// You could load a different template
				// 	// $restrictedTemplate = new Template(self::APP_ID, 'restricted');
				// 	// $event->setTemplate($restrictedTemplate);
					
				// 	// Or modify the current template parameters
				// 	$params = $event->getTemplate()->getParams();
				// 	$params['restricted'] = true;
				// 	$event->getTemplate()->assignArray($params);
				// }
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
