<?php

namespace JoomlaCli\Console\Model\Joomla;
/**
 * Class Download
 *
 * @package JoomlaCli\Console\Model\Joomla
 */
class Download
{
    /**
     * @var string
     */
    protected $cachePath;

    /**
     * Constructor
     *
     * @param string $cachePath directory path where to cache the downloads
     */
    public function __construct($cachePath)
    {
        $this->cachePath = $cachePath;
        $this->check();
    }

    /**
     * Check settings
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function check()
    {
        if (!file_exists($this->cachePath)) {
            // create the cache path
            $path = escapeshellarg($this->cachePath);
            `mkdir -p $path`;
        }

        if (!file_exists($this->cachePath) || !is_dir($this->cachePath) || !is_writeable($this->cachePath)) {
            throw new \RuntimeException('Cache path not accessible! ' . $this->cachePath);
        }
    }

    /**
     * Check target location, if not exists it creates it.
     *
     * @param string $target path
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function checkTarget($target)
    {
        if (!file_exists($target)) {
            $targetEscaped = escapeshellarg($target);

            $returnValue = `mkdir -p $targetEscaped`;
            if ($returnValue) {
                throw new \RuntimeException('Could not create directory ' . $target);
            }
        }
    }

    /**
     * Perform the download
     *
     * @param string $url           url to download
     * @param string $release       name / version of the download
     * @param string $target        where to unpack the download
     * @param bool   $cacheDownload to keep the download
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function download($url, $release, $target, $cacheDownload = true)
    {
        $this->checkTarget($target);

        $cachedRelease = $this->cachePath . '/' . $release;

        if (!file_exists($cachedRelease)) {
            $bytes = file_put_contents($cachedRelease, fopen($url, 'r'));
            if ($bytes === false || $bytes === 0) {
                throw new \RuntimeException(sprintf('Failed to download %s', $url));
            }
        }

        // unpack
        $cachePathEscaped = escapeshellarg($cachedRelease);
        $targetEscaped    = escapeshellarg($target);
        `cd $targetEscaped; tar xzf $cachePathEscaped`;

        if (!$cacheDownload) {
            unlink($cachedRelease);
        }
    }
}