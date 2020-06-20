<?php

namespace Alltube\Library\Exception;

/**
 * Conversion of playlists is not supported.
 */
class PlaylistConversionException extends AlltubeLibraryException
{
    /**
     * @var string Error message
     */
    protected $message = 'Conversion of playlists is not supported.';
}
