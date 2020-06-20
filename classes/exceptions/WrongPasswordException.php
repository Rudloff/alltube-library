<?php

namespace Alltube\Library\Exception;

/**
 * Wrong password.
 */
class WrongPasswordException extends AlltubeLibraryException
{
    /**
     * @var string Error message.
     */
    protected $message = 'Wrong password.';
}
