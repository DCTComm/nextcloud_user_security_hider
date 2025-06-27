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
        
        $this->logger->debug('FileDeleteListener constructed', ['time' => time()]);
    }

    public function handle(Event $event): void {
        try {
            $this->logger->debug('FileDeleteListener handling event', [
                'event_class' => get_class($event),
                'time' => time()
            ]);

            // Handle both node events and hook events
            if ($event instanceof BeforeNodeDeletedEvent) {
                $this->handleNodeEvent($event);
            } else {
                $this->handleHookEvent($event);
            }
            
        } catch (NotPermittedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error in FileDeleteListener: ' . $e->getMessage(), [
                'exception' => $e,
                'time' => time()
            ]);
            throw new NotPermittedException('File deletion failed: ' . $e->getMessage());
        }
    }

    private function handleNodeEvent(BeforeNodeDeletedEvent $event): void {
        $user = $this->userSession->getUser();
        if (!$user) {
            $this->logger->warning('No user found in session, blocking deletion');
            throw new NotPermittedException('User not authenticated');
        }

        $userId = $user->getUID();
        $node = $event->getNode();
        $path = $node->getPath();
        
        $this->logger->info('Processing node delete request', [
            'user' => $userId,
            'path' => $path,
            'node_type' => $node->getType(),
            'time' => time()
        ]);

        if (!$this->groupManager->isInGroup($userId, 'admin')) {
            $this->logger->warning('Blocking file deletion - user is not admin', [
                'user' => $userId,
                'path' => $path,
                'time' => time()
            ]);
            throw new NotPermittedException('Only administrators are allowed to delete files');
        }
    }

    private function handleHookEvent($event): void {
        $user = $this->userSession->getUser();
        if (!$user) {
            $this->logger->warning('No user found in session, blocking deletion (hook)');
            throw new NotPermittedException('User not authenticated');
        }

        $userId = $user->getUID();
        
        $this->logger->info('Processing hook delete request', [
            'user' => $userId,
            'event' => get_class($event),
            'time' => time()
        ]);

        if (!$this->groupManager->isInGroup($userId, 'admin')) {
            $this->logger->warning('Blocking file deletion - user is not admin (hook)', [
                'user' => $userId,
                'time' => time()
            ]);
            throw new NotPermittedException('Only administrators are allowed to delete files');
        }
    }
} 