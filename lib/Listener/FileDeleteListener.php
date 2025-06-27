<?php

declare(strict_types=1);

namespace OCA\UserSecurityHider\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\NotPermittedException;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FileDeleteListener implements IEventListener {
    private $userSession;
    private $groupManager;
    private $logger;

    public function __construct(
        IUserSession $userSession,
        IGroupManager $groupManager,
        LoggerInterface $logger
    ) {
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->logger = $logger;
        
        $this->logger->info('FileDeleteListener constructed successfully');
    }

    public function handle(Event $event): void {
        $this->logger->info('FileDeleteListener handle() method called');
        
        if (!($event instanceof BeforeNodeDeletedEvent)) {
            $this->logger->info('Event is not BeforeNodeDeletedEvent, it is: ' . get_class($event));
            return;
        }

        $this->logger->info('Received BeforeNodeDeletedEvent');
        
        $user = $this->userSession->getUser();
        if (!$user) {
            $this->logger->info('No user found in session');
            return;
        }

        $userId = $user->getUID();
        $this->logger->info('Processing delete request for user: ' . $userId);

        // Check if user is an admin
        $isAdmin = $this->groupManager->isInGroup($userId, 'admin');
        $this->logger->info('User admin status check: ' . ($isAdmin ? 'true' : 'false'));

        // If not an admin, prevent deletion
        if (!$isAdmin) {
            $node = $event->getNode();
            $path = $node->getPath();
            
            $this->logger->warning(
                'Blocking file deletion attempt',
                [
                    'user' => $userId,
                    'path' => $path,
                    'isAdmin' => false
                ]
            );
            
            throw new NotPermittedException('Only administrators are allowed to delete files');
        }

        // Log successful deletion for admin users
        $node = $event->getNode();
        $this->logger->info(
            'Allowing file deletion by admin',
            [
                'user' => $userId,
                'path' => $node->getPath(),
                'isAdmin' => true
            ]
        );
    }
} 