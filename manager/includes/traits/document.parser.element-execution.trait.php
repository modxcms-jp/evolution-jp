<?php

trait DocumentParserElementExecutionTrait
{
    private function startElementExecution($elementLevel)
    {
        $globalLevel = (string)$this->config('error_reporting', '1');
        $inherited = ($elementLevel === null || $elementLevel === '' || $elementLevel === 'inherit');
        $effectiveLevel = $inherited ? $globalLevel : (string)$elementLevel;
        [$mask, $internalLevel] = $this->convertErrorReportingLevel($effectiveLevel);
        $previousPhp = error_reporting($mask);
        $previousInternal = $this->error_reporting;
        $this->error_reporting = $internalLevel;

        return [
            'prev_php' => $previousPhp,
            'prev_internal' => $previousInternal,
            'compat' => !$inherited && $effectiveLevel !== $globalLevel,
            'effective' => $effectiveLevel,
            'global' => $globalLevel
        ];
    }

    private function finishElementExecution(array $state)
    {
        if (isset($state['prev_php'])) {
            error_reporting($state['prev_php']);
        }
        if (array_key_exists('prev_internal', $state)) {
            $this->error_reporting = $state['prev_internal'];
        }
    }

    private function discardElementBuffer($initialLevel)
    {
        $output = '';
        while (ob_get_level() > $initialLevel) {
            $output = ob_get_clean() . $output;
        }

        return $output;
    }

    private function handleElementThrowable(\Throwable $throwable, $type, $name, $output, array $executionState)
    {
        $source = $this->buildElementSource($type, $name, $executionState);
        $this->messageQuit(
            'PHP Fatal Error',
            '',
            true,
            E_ERROR,
            $throwable->getFile(),
            $source,
            $throwable->getMessage(),
            $throwable->getLine(),
            $output,
            false
        );

        if ($this->isBackend()) {
            $contextName = $name ?: '(anonymous)';
            $message = sprintf(
                'An error occurred while executing the %s "%s". Please see the event log for more information.',
                strtolower($type),
                $contextName
            );
            if ($output !== '') {
                $message .= sprintf('<p>%s</p>', $output);
            }
            $this->event->alert($message);
        }
    }

    private function buildElementSource($type, $name, array $executionState = [])
    {
        $source = trim($type . ($name ? ': ' . $name : '')) ?: $type;
        if (!empty($executionState['compat'])) {
            $source .= sprintf(
                ' [compat mode element=%s global=%s]',
                $executionState['effective'],
                $executionState['global']
            );
        }

        return $source;
    }

    private function convertErrorReportingLevel($level)
    {
        switch ((string)$level) {
            case '0':
                return [0, 0];
            case '2':
                return [E_ALL & ~E_NOTICE, 2];
            case '99':
                return [E_ALL, 99];
            case '1':
                return [E_ALL & ~E_NOTICE & ~E_DEPRECATED, 1];
            default:
                return [error_reporting(), $this->error_reporting];
        }
    }
}

