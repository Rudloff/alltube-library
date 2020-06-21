# AllTube library

This library lets you extract a video URL from a webpage
by providing a wrapper
for [youtube-dl](https://ytdl-org.github.io/youtube-dl/index.html).

It is primarily used by [AllTube Download](https://github.com/Rudloff/alltube).

You can install it with:

```bash
composer require rudloff/alltube-library
```

You can then use it in your PHP code:

```php
use Alltube\Library\Downloader;

require_once __DIR__.'/vendor/autoload.php';

$downloader = new Downloader('/usr/local/bin/youtube-dl');
$video = $downloader->getVideo('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
$video->getUrl();
```

You can also have a look at
this [example project](https://github.com/Rudloff/alltube-example-project).
