<?php

/**
 * EmptyUrlException class.
 */

namespace Alltube\Library\Exception;

/**
 * Exception thrown when youtube-dl returns an empty URL.
 */
class EmptyUrlException extends AlltubeLibraryException
{
    /**
     * Error message.
     * @var string
     */
    protected $message = 'youtube-dl returned an empty URL.';
}
