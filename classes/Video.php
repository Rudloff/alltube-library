<?php

/**
 * Video class.
 */

namespace Alltube\Library;

use Alltube\Library\Exception\AlltubeLibraryException;
use Alltube\Library\Exception\EmptyUrlException;
use stdClass;

/**
 * Extract info about videos.
 *
 * Due to the way youtube-dl behaves, this class can also contain information about a playlist.
 *
 * @property-read string $title         Title
 * @property-read string $protocol      Network protocol (HTTP, RTMP, etc.)
 * @property-read string $url           File URL
 * @property-read string $ext           File extension
 * @property-read string $extractor_key youtube-dl extractor class used
 * @property-read array $entries       List of videos (if the object contains information about a playlist)
 * @property-read array $rtmp_conn
 * @property-read string|null $_type         Object type (usually "playlist" or null)
 * @property-read stdClass $downloader_options
 * @property-read stdClass $http_headers
 */
class Video
{

    /**
     * URL of the page containing the video.
     *
     * @var string
     */
    private $webpageUrl;

    /**
     * Requested video format.
     *
     * @var string
     */
    private $requestedFormat;

    /**
     * Password.
     *
     * @var string|null
     */
    private $password;

    /**
     * JSON object returned by youtube-dl.
     *
     * @var stdClass
     */
    private $json;

    /**
     * URLs of the video files.
     *
     * @var string[]
     */
    private $urls;

    /**
     * Downloader instance.
     *
     * @var Downloader
     */
    private $downloader;

    /**
     * VideoDownload constructor.
     *
     * @param Downloader $downloader Downloader instance
     * @param string $webpageUrl URL of the page containing the video
     * @param string $requestedFormat Requested video format
     *                                (can be any format string accepted by youtube-dl,
     *                                including selectors like "[height<=720]")
     * @param string $password Password
     */
    public function __construct(
        Downloader $downloader,
        $webpageUrl,
        $requestedFormat,
        $password = null
    ) {
        $this->downloader = $downloader;
        $this->webpageUrl = $webpageUrl;
        $this->requestedFormat = $requestedFormat;
        $this->password = $password;
    }

    /**
     * Get a property from youtube-dl.
     *
     * @param string $prop Property
     *
     * @return string
     * @throws Exception\PasswordException
     * @throws Exception\WrongPasswordException
     * @throws Exception\YoutubedlException
     */
    public function getProp($prop = 'dump-json')
    {
        $arguments = ['--' . $prop];

        if (isset($this->webpageUrl)) {
            $arguments[] = $this->webpageUrl;
        }
        if (isset($this->requestedFormat)) {
            $arguments[] = '-f';
            $arguments[] = $this->requestedFormat;
        }
        if (isset($this->password)) {
            $arguments[] = '--video-password';
            $arguments[] = $this->password;
        }

        return $this->downloader->callYoutubedl($arguments);
    }

    /**
     * Get all information about a video.
     *
     * @return stdClass Decoded JSON
     * @throws Exception\PasswordException
     * @throws Exception\WrongPasswordException
     * @throws Exception\YoutubedlException
     */
    public function getJson()
    {
        if (!isset($this->json)) {
            $this->json = json_decode($this->getProp('dump-single-json'));
        }

        return $this->json;
    }

    /**
     * Magic method to get a property from the JSON object returned by youtube-dl.
     *
     * @param string $name Property
     *
     * @return mixed
     * @throws Exception\PasswordException
     * @throws Exception\WrongPasswordException
     * @throws Exception\YoutubedlException
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->getJson()->$name;
        }

        return null;
    }

    /**
     * Magic method to check if the JSON object returned by youtube-dl has a property.
     *
     * @param string $name Property
     *
     * @return bool
     * @throws Exception\PasswordException
     * @throws Exception\WrongPasswordException
     * @throws Exception\YoutubedlException
     */
    public function __isset($name)
    {
        return isset($this->getJson()->$name);
    }

    /**
     * Get URL of video from URL of page.
     *
     * It generally returns only one URL.
     * But it can return two URLs when multiple formats are specified
     * (eg. bestvideo+bestaudio).
     *
     * @return string[] URLs of video
     * @throws EmptyUrlException
     * @throws Exception\PasswordException
     * @throws Exception\WrongPasswordException
     * @throws Exception\YoutubedlException
     */
    public function getUrl()
    {
        // Cache the URLs.
        if (!isset($this->urls)) {
            $this->urls = explode("\n", $this->getProp('get-url'));

            if (empty($this->urls[0])) {
                throw new EmptyUrlException();
            }
        }

        return $this->urls;
    }

    /**
     * Get filename of video file from URL of page.
     *
     * @return string Filename of extracted video
     * @throws Exception\PasswordException
     * @throws Exception\WrongPasswordException
     * @throws Exception\YoutubedlException
     */
    public function getFilename()
    {
        return trim($this->getProp('get-filename'));
    }

    /**
     * Get filename of video with the specified extension.
     *
     * @param string $extension New file extension
     *
     * @return string Filename of extracted video with specified extension
     * @throws Exception\PasswordException
     * @throws Exception\WrongPasswordException
     * @throws Exception\YoutubedlException
     */
    public function getFileNameWithExtension($extension)
    {
        return str_replace('.' . $this->ext, '.' . $extension, $this->getFilename());
    }

    /**
     * Return arguments used to run rtmp for a specific video.
     *
     * @return string[] Arguments
     */
    public function getRtmpArguments()
    {
        $arguments = [];

        if ($this->protocol == 'rtmp') {
            foreach (
                [
                    'url' => '-rtmp_tcurl',
                    'webpage_url' => '-rtmp_pageurl',
                    'player_url' => '-rtmp_swfverify',
                    'flash_version' => '-rtmp_flashver',
                    'play_path' => '-rtmp_playpath',
                    'app' => '-rtmp_app',
                ] as $property => $option
            ) {
                if (isset($this->{$property})) {
                    $arguments[] = $option;
                    $arguments[] = $this->{$property};
                }
            }

            if (isset($this->rtmp_conn)) {
                foreach ($this->rtmp_conn as $conn) {
                    $arguments[] = '-rtmp_conn';
                    $arguments[] = $conn;
                }
            }
        }

        return $arguments;
    }

    /**
     * Get the same video but with another format.
     *
     * @param string $format New format
     *
     * @return Video
     */
    public function withFormat($format)
    {
        return new self($this->downloader, $this->webpageUrl, $format, $this->password);
    }
}
