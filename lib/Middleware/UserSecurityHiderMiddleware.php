<?php

namespace OCA\UserSecurityHider\Middleware;

use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;
use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LogLevel;

class UserSecurityHiderMiddleware extends Middleware {
    /** @var LoggerInterface */
    private $logger;

    /** @var IRequest */
    private $request;

    /** @var IUserSession */
    private $userSession;

    /** @var string */
    private $appName;

    public function __construct(LoggerInterface $logger, IRequest $request, ?IUserSession $userSession, string $appName) {
        $this->logger = $logger;
        $this->request = $request;
        $this->userSession = $userSession;
        $this->appName = $appName;
        
        $this->logger->debug('UserSecurityHiderMiddleware constructed', [
            'app_name' => $appName,
            'request_path' => $request->getPathInfo(),
            'script_name' => $request->getScriptName()
        ]);
    }

    /**
     * Log route changes before controller execution
     *
     * @param \OCP\AppFramework\Controller $controller the controller that is being called
     * @param string $methodName the name of the method that will be called on the controller
     */
    public function beforeController($controller, $methodName) {
        // Skip logging for CLI/cron contexts
        if (php_sapi_name() === 'cli') {
            return;
        }

        try {
            $userId = $this->userSession && $this->userSession->getUser() ? $this->userSession->getUser()->getUID() : 'anonymous';
            $path = $this->request->getPathInfo();
            $scriptName = $this->request->getScriptName();
            $requestUri = $this->request->getRequestUri();
            
            // Get the full URL path
            $fullPath = str_replace('/index.php', '', $scriptName) . $path;
            
            $route = [
                'controller' => get_class($controller),
                'method' => $methodName,
                'path' => $path,
                'script' => $scriptName,
                'full_path' => $fullPath,
                'request_uri' => $requestUri,
                'request_method' => $this->request->getMethod(),
                'parameters' => $this->sanitizeParams($this->request->getParams()),
                'user' => $userId,
                'timestamp' => date('c')
            ];

            $this->logger->debug(
                sprintf('[DEBUG] Navigation detected - User: %s, Method: %s, Path: %s, Script: %s', 
                    $userId, 
                    $this->request->getMethod(), 
                    $fullPath,
                    $scriptName
                ),
                ['route' => json_encode($route)]
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Error in middleware route logging: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'controller' => get_class($controller),
                'method' => $methodName
            ]);
        }
    }

    /**
     * Check if the operation is considered sensitive
     *
     * @param string $methodName
     * @return bool
     */
    private function isSensitiveOperation(string $methodName): bool {
        $sensitiveOperations = [
            'delete',
            'create',
            'update',
            'share',
            'upload',
            'download'
        ];

        foreach ($sensitiveOperations as $operation) {
            if (stripos($methodName, $operation) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove sensitive information from parameters
     *
     * @param array $params
     * @return array
     */
    private function sanitizeParams(array $params): array {
        $sensitiveKeys = ['password', 'token', 'auth', 'key'];
        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $params[$key] = '***REDACTED***';
            }
        }
        return $params;
    }
}