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
            $this->logger->debug('FileDeleteListener handling event of type: ' . get_class($event));
            
            if (!($event instanceof BeforeNodeDeletedEvent)) {
                $this->logger->debug('Event is not BeforeNodeDeletedEvent, skipping');
                return;
            }

            $this->logger->info('Processing BeforeNodeDeletedEvent');
            
            $user = $this->userSession->getUser();
            if (!$user) {
                $this->logger->warning('No user found in session, blocking deletion');
                throw new NotPermittedException('User not authenticated');
            }

            $userId = $user->getUID();
            $node = $event->getNode();
            $path = $node->getPath();
            
            $this->logger->info('Processing delete request', [
                'user' => $userId,
                'path' => $path,
                'node_type' => $node->getType(),
                'time' => time()
            ]);

            // Check if user is an admin
            $isAdmin = $this->groupManager->isInGroup($userId, 'admin');
            
            // If not an admin, prevent deletion
            if (!$isAdmin) {
                $this->logger->warning(
                    'Blocking file deletion - user is not admin',
                    [
                        'user' => $userId,
                        'path' => $path,
                        'time' => time()
                    ]
                );
                
                throw new NotPermittedException(
                    'Only administrators are allowed to delete files'
                );
            }

            // Log successful deletion for admin users
            $this->logger->info(
                'Allowing file deletion by admin',
                [
                    'user' => $userId,
                    'path' => $path,
                    'time' => time()
                ]
            );
            
        } catch (NotPermittedException $e) {
            // Re-throw NotPermittedException
            throw $e;
        } catch (\Exception $e) {
            // Log any unexpected errors
            $this->logger->error('Error in FileDeleteListener: ' . $e->getMessage(), [
                'exception' => $e,
                'time' => time()
            ]);
            // Convert to NotPermittedException to prevent deletion
            throw new NotPermittedException('File deletion failed: ' . $e->getMessage());
        }
    }
} 