<?php

namespace Alltube\Library\Exception;

/**
 * Could not open popen stream.
 */
class PopenStreamException extends AlltubeLibraryException
{
    /**
     * @var string Error message
     */
    protected $message = 'Could not open popen stream.';
}
