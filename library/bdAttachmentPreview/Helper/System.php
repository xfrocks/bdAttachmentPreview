<?php

class bdAttachmentPreview_Helper_System
{
    public static function exec($cmd, array $options = array())
    {
        $options = array_merge(array(
            'cwd' => '',
            'env' => array(),
            'stdout' => true,
            'stderr' => true,

            // http://blog.dubbelboer.com/2012/08/24/execute-with-timeout.html
            // set a sensible default timeout for *NIX environment
            'timeout' => (DIRECTORY_SEPARATOR === '/' ? 30 : 0),
        ), $options);

        $descriptorSpec = array(1 => array('pipe', 'w'));
        if ($options['timeout'] > 0
            || $options['stdout']
            || XenForo_Application::debugMode()
        ) {
            $descriptorSpec[1] = array('pipe', 'w');
        }
        if ($options['stderr']
            || XenForo_Application::debugMode()
        ) {
            $descriptorSpec[2] = array('pipe', 'w');
        }

        $cwd = $options['cwd'];
        if (empty($cwd)) {
            $cwd = getcwd();
        }

        $env = $options['env'];
        if (!isset($env['PATH'])) {
            $env['PATH'] = self::_getEnvPath();
        }

        $execCmd = $cmd;
        if ($options['timeout'] > 0) {
            $execCmd = 'exec ' . $cmd;
        }

        $openTime = microtime(true);
        $process = proc_open($execCmd, $descriptorSpec, $pipes, $cwd, $env);
        if (!is_resource($process)) {
            XenForo_Error::logError(sprintf('Unable to execute command %s', $cmd));
            return array(
                'status' => 1,
                'stdout' => '',
                'stderr' => '',
            );
        }

        if ($options['timeout'] > 0) {
            $buffer = '';
            stream_set_blocking($pipes[1], 0);

            $timeout = $options['timeout'] * 1000000;
            while ($timeout > 0) {
                $start = microtime(true);

                $read = array($pipes[1]);
                $other = array();
                stream_select($read, $other, $other, 0, $timeout);

                $procStatus = proc_get_status($process);
                $buffer .= stream_get_contents($pipes[1]);
                if (!$procStatus['running']) {
                    break;
                }

                $timeout -= (microtime(true) - $start) * 1000000;
            }

            $stdout = preg_split('#\n#', $buffer, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $stdout = array(0 => '');
            if (isset($pipes[1])) {
                $stdout = stream_get_contents($pipes[1]);
                $stdout = preg_split('#\n#', $stdout, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        $stderr = array(0 => '');
        if (isset($pipes[2])) {
            stream_set_blocking($pipes[2], 0);
            $stderr = stream_get_contents($pipes[2]);
            $stderr = preg_split('#\n#', $stderr, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!isset($procStatus)) {
            $procStatus = proc_get_status($process);
        }
        $terminated = null;
        if ($procStatus['running']) {
            $terminateTime = microtime(true);
            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__METHOD__, sprintf('%s -> terminated at %.5f',
                    $cmd, $terminateTime - $openTime));
            }
            $terminated = proc_terminate($process);
        }

        foreach (array_keys($pipes) as $pipeId) {
            fclose($pipes[$pipeId]);
        }

        $closeStatus = proc_close($process);
        $elapsedTime = microtime(true) - $openTime;
        $status = $procStatus['running'] ? $closeStatus : $procStatus['exitcode'];

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__METHOD__, sprintf("%s -> %d (terminated?=%s, elapsed=%.5f)\n%s%s", $cmd,
                $status, $terminated, $elapsedTime, implode("\n", $stdout), implode("\n", $stderr)));
        }

        return array(
            'status' => $status,
            'stdout' => $stdout,
            'stderr' => $stderr,
        );
    }

    public static function execStatus($cmd, array $options = array())
    {
        $result = self::exec($cmd, array_merge(array('stdout' => false, 'stderr' => false), $options));
        return intval($result['status']);
    }

    public static function execStdout($cmd, array $options = array())
    {
        $result = self::exec($cmd, array_merge(array('stderr' => false), $options));

        if (!empty($result['stdout'][0])) {
            return trim($result['stdout'][0]);
        } else {
            return '';
        }
    }

    protected static function _getEnvPath()
    {
        static $path = null;

        if ($path === null) {
            $path = getenv('PATH');

            if (empty($path)) {
                // workaround in case getenv does not work
                $path = exec('echo $PATH');
            }
        }

        return $path;
    }
}