<?php

class ConnectorKernel
{
    private $modx;
    private $config;
    private $commandPath;

    public function __construct(DocumentParser $modx, array $config, $commandPath)
    {
        $this->modx = $modx;
        $this->config = $config;
        $this->commandPath = rtrim($commandPath, '/') . '/';
    }

    public function handle(ConnectorRequest $request, array $session)
    {
        $this->assertSession($session);
        $this->sendHeaders();
        $this->logDebug($request, $session);

        $command = $request->getCommand();
        $type = $request->getType();

        if (!$this->isValidCommand($command)) {
            $this->modx->logEvent(0, 3, 'Invalid command.(No reason for me to be here)');
            exit(0);
        }

        if (!$this->isValidResourceType($type)) {
            $this->modx->logEvent(0, 3, 'Invalid resource type.');
            exit(0);
        }

        $this->loadCommand($command);

        $action = new $command($this->config, $type, $request->getCurrentFolder());
        $action->run();
    }

    private function assertSession(array $session)
    {
        if (!isset($session['mgrValidated']) && !isset($session['webValidated'])) {
            exit("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
        }
    }

    private function sendHeaders()
    {
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }

    private function logDebug(ConnectorRequest $request, array $session)
    {
        if (!array_key_exists('Debug', $this->config) || $this->config['Debug'] !== true) {
            return;
        }

        $msg = '$command=' . $request->getCommand() . "\n";
        $msg .= '$type=' . $request->getType() . "\n";
        $msg .= '$cwd=' . $request->getCurrentFolder() . "\n";
        $msg .= '$extra=' . $request->getExtraParams() . "\n";
        $msg .= '$_GET=' . print_r($request->getQuery(), true) . "\n";
        $msg .= '$_POST=' . print_r($request->getPost(), true) . "\n";
        $msg .= '$_SERVER=' . print_r($request->getServer(), true) . "\n";
        $msg .= '$_SESSIONS=' . print_r($session, true) . "\n";
        $msg .= '$_COOKIE=' . print_r($request->getCookies(), true) . "\n";
        $msg .= '$_FILES=' . print_r($request->getFiles(), true) . "\n";

        $this->modx->logEvent(
            0,
            1,
            nl2br(
                str_replace(' ', '&nbsp;', hsc($msg))
            ),
            'mcpuk connector'
        );
    }

    private function isValidCommand($command)
    {
        return in_array($command, $this->config['Commands']);
    }

    private function isValidResourceType($type)
    {
        return in_array($type, $this->config['ResourceTypes']);
    }

    private function loadCommand($command)
    {
        $commandFile = $this->commandPath . $command . '.php';
        if (!is_file($commandFile)) {
            $this->modx->logEvent(0, 3, 'Command file not found: ' . $command);
            exit(0);
        }

        include_once $commandFile;
    }
}
