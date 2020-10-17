<?php

namespace Alltube\Library\Exception;

/**
 * Invalid time.
 */
class InvalidTimeException extends AlltubeLibraryException
{

    /**
     * InvalidTimeException constructor.
     * @param string $time Invalid time
     */
    public function __construct(string $time)
    {
        parent::__construct('Invalid time: ' . $time);
    }
}
