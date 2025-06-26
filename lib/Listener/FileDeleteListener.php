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
    }

    public function handle(Event $event): void {
        if (!($event instanceof BeforeNodeDeletedEvent)) {
            return;
        }

        $user = $this->userSession->getUser();
        if (!$user) {
            return;
        }

        // Check if user is an admin
        $isAdmin = $this->groupManager->isInGroup($user->getUID(), 'admin');

        // If not an admin, prevent deletion
        if (!$isAdmin) {
            $node = $event->getNode();
            $this->logger->info(
                'Prevented file deletion by non-admin user',
                [
                    'user' => $user->getUID(),
                    'path' => $node->getPath()
                ]
            );
            throw new NotPermittedException('Only administrators are allowed to delete files');
        }
    }
} 