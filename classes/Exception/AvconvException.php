<?php

namespace Alltube\Library\Exception;

/**
 * Can't find avconv or ffmpeg.
 */
class AvconvException extends AlltubeLibraryException
{
    /**
     * AvconvException constructor.
     * @param string $path Path to avconv or ffmpeg.
     */
    public function __construct($path)
    {
        parent::__construct("Can't find avconv or ffmpeg at " . $path . '.');
    }
}
