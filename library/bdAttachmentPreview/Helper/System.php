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
        ), $options);

        $descriptorSpec = array(1 => array('pipe', 'w'));
        if ($options['stdout'] || XenForo_Application::debugMode()) {
            $descriptorSpec[1] = array('pipe', 'w');
        }
        if ($options['stderr'] || XenForo_Application::debugMode()) {
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

        $process = proc_open($cmd, $descriptorSpec, $pipes, $cwd, $env);

        $stdout = array(0 => '');
        if (isset($pipes[1])) {
            $stdout = stream_get_contents($pipes[1]);
            $stdout = preg_split('#\n#', $stdout, -1, PREG_SPLIT_NO_EMPTY);
            fclose($pipes[1]);
        }

        $stderr = array(0 => '');
        if (isset($pipes[2])) {
            $stderr = stream_get_contents($pipes[2]);
            $stderr = preg_split('#\n#', $stderr, -1, PREG_SPLIT_NO_EMPTY);
            fclose($pipes[2]);
        }

        $status = proc_close($process);

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__METHOD__, sprintf("%s -> %d\n%s%s", $cmd,
                $status, implode("\n", $stdout), implode("\n", $stderr)));
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