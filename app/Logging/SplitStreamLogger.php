<?php

namespace Crater\Logging;

use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Canal "stderr": info y notice a stdout, warning o mas grave a stderr,
 * para que Railway no marque como error cada linea informativa.
 */
class SplitStreamLogger
{
    public function __invoke(array $config): Logger
    {
        $level = Logger::toMonologLevel($config['level'] ?? 'debug');
        $stdout = $config['stdout'] ?? 'php://stdout';
        $stderr = $config['stderr'] ?? 'php://stderr';

        $errors = new StreamHandler($stderr, max($level, Logger::WARNING));
        $info = new StreamHandler($stdout, $level);

        return new Logger($config['name'] ?? 'stderr', [
            $errors,
            new FilterHandler($info, $level, Logger::NOTICE),
        ]);
    }
}
