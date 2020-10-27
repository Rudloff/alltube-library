<?php

namespace Alltube\Library\Exception;

/**
 * Conversion of playlists is not supported.
 */
class PlaylistConversionException extends AlltubeLibraryException
{
    /**
     * Error message.
     * @var string
     */
    protected $message = 'Conversion of playlists is not supported.';
}
