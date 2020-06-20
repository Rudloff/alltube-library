<?php

namespace Alltube\Library;

use Alltube\Library\Exception\AlltubeLibraryException;
use Alltube\Library\Exception\AvconvException;
use Alltube\Library\Exception\InvalidProtocolConversionException;
use Alltube\Library\Exception\InvalidTimeException;
use Alltube\Library\Exception\PasswordException;
use Alltube\Library\Exception\PlaylistConversionException;
use Alltube\Library\Exception\PopenStreamException;
use Alltube\Library\Exception\RemuxException;
use Alltube\Library\Exception\WrongPasswordException;
use Alltube\Library\Exception\YoutubedlException;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;

/**
 * Class used to call youtube-dl and download videos.
 */
class Downloader
{

    /**
     * youtube-dl binary path.
     *
     * @var string
     */
    private $youtubedl;

    /**
     * python binary path.
     *
     * @var string
     */
    private $python;

    /**
     * avconv or ffmpeg binary path.
     *
     * @var string
     */
    private $avconv;

    /**
     * avconv/ffmpeg logging level.
     * Must be one of these: quiet, panic, fatal, error, warning, info, verbose, debug.
     *
     * @var string
     */
    private $avconvVerbosity;

    /**
     * Path to the directory that contains the phantomjs binary.
     *
     * @var string
     */
    private $phantomjsDir;

    /**
     * youtube-dl parameters.
     *
     * @var string[]
     */
    private $params;

    /**
     * Downloader constructor.
     * @param string $youtubedl youtube-dl binary path
     * @param string[] $params youtube-dl parameters
     * @param string $python python binary path
     * @param string $avconv avconv or ffmpeg binary path
     * @param string $phantomjsDir Path to the directory that contains the phantomjs binary
     * @param string $avconvVerbosity avconv/ffmpeg logging level
     */
    public function __construct(
        $youtubedl = '/usr/bin/youtube-dl',
        array $params = ['--no-warnings'],
        $python = '/usr/bin/python3',
        $avconv = '/usr/bin/ffmpeg',
        $phantomjsDir = '/usr/bin/',
        $avconvVerbosity = 'error'
    ) {
        $this->youtubedl = $youtubedl;
        $this->params = $params;
        $this->python = $python;
        $this->avconv = $avconv;
        $this->phantomjsDir = $phantomjsDir;
        $this->avconvVerbosity = $avconvVerbosity;
    }

    /**
     * @param string $webpageUrl URL of the page containing the video
     * @param string $requestedFormat Requested video format
     * @param string $password Password
     * @return Video
     */
    public function getVideo($webpageUrl, $requestedFormat = 'best/bestvideo', $password = null)
    {
        return new Video($this, $webpageUrl, $requestedFormat, $password);
    }

    /**
     * Return a youtube-dl process with the specified arguments.
     *
     * @param string[] $arguments Arguments
     *
     * @return Process<string>
     */
    private function getProcess(array $arguments)
    {
        return new Process(
            array_merge(
                [$this->python, $this->youtubedl],
                $this->params,
                $arguments
            )
        );
    }

    /**
     * Check if a command runs successfully.
     *
     * @param string[] $command Command and arguments
     *
     * @return bool False if the command returns an error, true otherwise
     */
    public static function checkCommand(array $command)
    {
        $process = new Process($command);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Get a process that runs avconv in order to convert a video.
     *
     * @param Video $video Video object
     * @param int $audioBitrate Audio bitrate of the converted file
     * @param string $filetype Filetype of the converted file
     * @param bool $audioOnly True to return an audio-only file
     * @param string $from Start the conversion at this time
     * @param string $to End the conversion at this time
     *
     * @return Process<string> Process
     * @throws AlltubeLibraryException
     * @throws AvconvException If avconv/ffmpeg is missing
     * @throws InvalidTimeException
     */
    private function getAvconvProcess(
        Video $video,
        $audioBitrate,
        $filetype = 'mp3',
        $audioOnly = true,
        $from = null,
        $to = null
    ) {
        if (!$this->checkCommand([$this->avconv, '-version'])) {
            throw new AvconvException($this->avconv);
        }

        $durationRegex = '/(\d+:)?(\d+:)?(\d+)/';

        $afterArguments = [];

        if ($audioOnly) {
            $afterArguments[] = '-vn';
        }

        if (!empty($from)) {
            if (!preg_match($durationRegex, $from)) {
                throw new InvalidTimeException($from);
            }
            $afterArguments[] = '-ss';
            $afterArguments[] = $from;
        }
        if (!empty($to)) {
            if (!preg_match($durationRegex, $to)) {
                throw new InvalidTimeException($to);
            }
            $afterArguments[] = '-to';
            $afterArguments[] = $to;
        }

        $urls = $video->getUrl();

        $arguments = array_merge(
            [
                $this->avconv,
                '-v', $this->avconvVerbosity,
            ],
            $video->getRtmpArguments(),
            [
                '-i', $urls[0],
                '-f', $filetype,
                '-b:a', $audioBitrate . 'k',
            ],
            $afterArguments,
            [
                'pipe:1',
            ]
        );

        //Vimeo needs a correct user-agent
        $arguments[] = '-user_agent';
        $arguments[] = $video->getProp('dump-user-agent');

        return new Process($arguments);
    }


    /**
     * Call youtube-dl.
     *
     * @param string[] $arguments Arguments
     *
     * @return string Result
     * @throws WrongPasswordException If the password is wrong
     * @throws YoutubedlException If youtube-dl returns an error
     * @throws PasswordException If the video is protected by a password and no password was specified
     */
    public function callYoutubedl(array $arguments)
    {
        $process = $this->getProcess($arguments);
        //This is needed by the openload extractor because it runs PhantomJS
        $process->setEnv(['PATH' => $this->phantomjsDir]);
        $process->run();
        if (!$process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $exitCode = intval($process->getExitCode());
            if ($errorOutput == 'ERROR: This video is protected by a password, use the --video-password option') {
                throw new PasswordException($errorOutput, $exitCode);
            } elseif (substr($errorOutput, 0, 21) == 'ERROR: Wrong password') {
                throw new WrongPasswordException($errorOutput, $exitCode);
            } else {
                throw new YoutubedlException($errorOutput, $exitCode);
            }
        } else {
            return trim($process->getOutput());
        }
    }


    /**
     * Get video stream from an M3U playlist.
     *
     * @param Video $video Video object
     * @return resource popen stream
     * @throws AlltubeLibraryException
     * @throws AvconvException If avconv/ffmpeg is missing
     * @throws PopenStreamException If the popen stream was not created correctly
     */
    public function getM3uStream(Video $video)
    {
        if (!$this->checkCommand([$this->avconv, '-version'])) {
            throw new AvconvException($this->avconv);
        }

        $urls = $video->getUrl();

        $process = new Process(
            [
                $this->avconv,
                '-v', $this->avconvVerbosity,
                '-i', $urls[0],
                '-f', $video->ext,
                '-c', 'copy',
                '-bsf:a', 'aac_adtstoasc',
                '-movflags', 'frag_keyframe+empty_moov',
                'pipe:1',
            ]
        );

        $stream = popen($process->getCommandLine(), 'r');
        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }


    /**
     * Get audio stream of converted video.
     *
     * @param Video $video Video object
     * @param int $audioBitrate MP3 bitrate when converting (in kbit/s)
     * @param string $from Start the conversion at this time
     * @param string $to End the conversion at this time
     *
     * @return resource popen stream
     * @throws AlltubeLibraryException
     * @throws AvconvException
     * @throws InvalidProtocolConversionException If you try to convert an M3U or Dash media
     * @throws PlaylistConversionException If you try to convert a playlist
     * @throws PopenStreamException If the stream is invalid
     */
    public function getAudioStream(Video $video, $audioBitrate = 128, $from = null, $to = null)
    {
        if (isset($video->_type) && $video->_type == 'playlist') {
            throw new PlaylistConversionException();
        }

        if (isset($video->protocol)) {
            if (in_array($video->protocol, ['m3u8', 'm3u8_native', 'http_dash_segments'])) {
                throw new InvalidProtocolConversionException($video->protocol);
            }
        }

        $avconvProc = $this->getAvconvProcess($video, $audioBitrate, 'mp3', true, $from, $to);

        $stream = popen($avconvProc->getCommandLine(), 'r');

        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }


    /**
     * Get an avconv stream to remux audio and video.
     *
     * @param Video $video Video object
     * @return resource popen stream
     * @throws AlltubeLibraryException
     * @throws PopenStreamException If the popen stream was not created correctly
     * @throws RemuxException If the video does not have two URLs
     */
    public function getRemuxStream(Video $video)
    {
        $urls = $video->getUrl();

        if (!isset($urls[0]) || !isset($urls[1])) {
            throw new RemuxException('This video does not have two URLs.');
        }

        $process = new Process(
            [
                $this->avconv,
                '-v', $this->avconvVerbosity,
                '-i', $urls[0],
                '-i', $urls[1],
                '-c', 'copy',
                '-map', '0:v:0',
                '-map', '1:a:0',
                '-f', 'matroska',
                'pipe:1',
            ]
        );

        $stream = popen($process->getCommandLine(), 'r');
        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }


    /**
     * Get video stream from an RTMP video.
     *
     * @param Video $video Video object
     * @return resource popen stream
     * @throws AlltubeLibraryException
     * @throws PopenStreamException If the popen stream was not created correctly
     */
    public function getRtmpStream(Video $video)
    {
        $urls = $video->getUrl();

        $process = new Process(
            array_merge(
                [
                    $this->avconv,
                    '-v', $this->avconvVerbosity,
                ],
                $video->getRtmpArguments(),
                [
                    '-i', $urls[0],
                    '-f', $video->ext,
                    'pipe:1',
                ]
            )
        );
        $stream = popen($process->getCommandLine(), 'r');
        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }


    /**
     * Get the stream of a converted video.
     *
     * @param Video $video Video object
     * @param int $audioBitrate Audio bitrate of the converted file
     * @param string $filetype Filetype of the converted file
     *
     * @return resource popen stream
     * @throws AlltubeLibraryException
     * @throws AvconvException
     * @throws InvalidProtocolConversionException If your try to convert and M3U8 video
     * @throws PopenStreamException If the popen stream was not created correctly
     */
    public function getConvertedStream(Video $video, $audioBitrate, $filetype)
    {
        if (in_array($video->protocol, ['m3u8', 'm3u8_native'])) {
            throw new InvalidProtocolConversionException($video->protocol);
        }

        $avconvProc = $this->getAvconvProcess($video, $audioBitrate, $filetype, false);

        $stream = popen($avconvProc->getCommandLine(), 'r');

        if (!is_resource($stream)) {
            throw new PopenStreamException();
        }

        return $stream;
    }

    /**
     * List all extractors.
     *
     * @return string[] Extractors
     *
     * @throws AlltubeLibraryException
     */
    public function getExtractors()
    {
        return explode("\n", trim($this->callYoutubedl(['--list-extractors'])));
    }


    /**
     * Get a HTTP response containing the video.
     *
     * @param Video $video Video object
     * @param mixed[] $headers HTTP headers of the request
     *
     * @return ResponseInterface
     * @throws AlltubeLibraryException
     * @link https://github.com/guzzle/guzzle/issues/2640
     */
    public function getHttpResponse(Video $video, array $headers = [])
    {
        // IDN conversion breaks with Google hosts like https://r3---sn-25glene6.googlevideo.com/.
        $client = new Client(['idn_conversion' => false]);
        $urls = $video->getUrl();
        $stream_context_options = [];

        if (array_key_exists('Referer', (array)$video->http_headers)) {
            $stream_context_options = [
                'http' => [
                    'header' => 'Referer: ' . $video->http_headers->Referer
                ]
            ];
        }

        return $client->request(
            'GET',
            $urls[0],
            [
                'stream' => true,
                'stream_context' => $stream_context_options,
                'headers' => array_merge((array)$video->http_headers, $headers)
            ]
        );
    }
}
