<?php

declare(strict_types=1);

namespace OCA\UserSecurityHider\Migration;

use OCP\Migration\IRepairStep;
use OCP\Migration\IOutput;
use Psr\Log\LoggerInterface;
use OCA\UserSecurityHider\AppInfo\Application;

class InstallRepairStep implements IRepairStep {
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function getName(): string {
        return 'Install UserSecurityHider app components';
    }

    public function run(IOutput $output): void {
        $output->info('Installing UserSecurityHider app...');
        $this->logger->debug('UserSecurityHider repair step running');
        
        // Any initialization code can go here
        
        $output->info('UserSecurityHider app installation completed');
    }
} 