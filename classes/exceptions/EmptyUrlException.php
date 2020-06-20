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
     * @var string Error message
     */
    protected $message = 'youtube-dl returned an empty URL.';
}
