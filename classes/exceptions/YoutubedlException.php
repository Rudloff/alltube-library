<?php

namespace Alltube\Library\Exception;

use Symfony\Component\Process\Process;

/**
 * Generic youtube-dl error.
 */
class YoutubedlException extends AlltubeLibraryException
{
    /**
     * YoutubedlException constructor.
     *
     * @param Process<string> $process Process that caused the exception
     */
    public function __construct(Process $process)
    {
        parent::__construct(
            $process->getCommandLine() . ' failed with this error:' . PHP_EOL . trim($process->getErrorOutput()),
            intval($process->getExitCode())
        );
    }
}
