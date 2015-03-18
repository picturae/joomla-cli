<?php

namespace JoomlaCli\Console\Model\Joomla;

/**
 * Class Versions Model
 *
 * @package JoomlaCli\Console\Model\Joomla
 */
class Versions
{
    /**
     * @var string
     */
    protected $joomlaRepository = 'https://github.com/joomla/joomla-cms.git';

    /**
     * @var string
     */
    protected $joomlaTarballBaseUrl = 'https://github.com/joomla/joomla-cms/tarball';

    /**
     * @var string
     */
    protected $joomlaArchiveBaseUrl = 'https://github.com/joomla/joomla-cms/archive';

    /**
     * @var string
     */
    protected $cachePath;

    /**
     * @var int
     */
    protected $cacheExpire = 3600; //60*60 seconds (1 hour);

    /**
     * @var array
     */
    protected $versions = null;

    /**
     * @var bool
     */
    protected $stable = true;

    /**
     * Constructor
     *
     * @param null $cachePatch path to cache file
     * @param int  $cachExpire cache expire in seconds
     */
    public function __construct($cachePatch = null, $cachExpire = 3600)
    {
        $this->cachePath = $cachePatch;
        $this->cacheExpire = $cachExpire;
        $this->retrieveVersionsFromCache();
    }

    /**
     * Get a release from the release list of given version
     * Using .* will try to find latest of particular major / major.minor version
     * For example 3.3.* will find the latest 3.3 release
     *
     * @param string $version strict name or part of version with .*
     * @param bool $stable boolean to set the stability
     *
     * @throws \InvalidArgumentException
     *
     * @return array|null
     */
    public function getVersion($version, $stable)
    {
        if (!is_string($version)) {
            throw new \InvalidArgumentException('Unexpected value, string expected!');
        }

        // set stability in case we want to install RC or beta versions
        $this->setStability($stable);

        $versions = $this->getVersions();

        // first check on exact matches if we don't want a stable version
        if (!$this->stable && array_key_exists($version, $versions['heads'])) {
            return [$version => $versions['heads'][$version]];
        }

        // remove none stable (e.g. 3.4.1-rc) tags
        if($this->stable) {
            $versionCleanup = array_filter(array_flip($versions['tags']), function($val){
                $pattern  = '/^(?:(\d+)\.)?(?:(\d+)\.)?(\*|\d+)$/';
                return preg_match($pattern, $val);
            });
            $versionCleanup = array_flip($versionCleanup);
            $versions['tags'] = $versionCleanup;
        }

        if (array_key_exists($version, $versions['tags'])) {
            return [$version => $versions['tags'][$version]];
        }

        $latest = null;
        $matches = [];
        if (preg_match('/^(\d+)\.(\d+|\*)(\.\*)?$/', $version, $matches)) {
            foreach (array_keys($versions['tags']) as $tag) {
                if (isset($matches[1]) && isset($matches[2])) {
                    // check if major.minor.x or major.x
                    if ($matches[2] === '*') {
                        $check = preg_match('/^'.$matches[1] . '\.*/', $tag);
                    } else {
                        $check = preg_match('/^'.$matches[1] . '\.' . $matches[2] . '\.*/', $tag);
                    }

                    if ($check) {
                        if ($latest === null) {
                            $latest = $tag;
                        } elseif (version_compare($tag, $latest, '>')) {
                            $latest = $tag;
                        }
                    }
                }
            }

            if ($latest !== null) {
                return [$latest => $versions['tags'][$latest]];
            } else {
                return null;
            }
        }
    }

    /**
     * Retrieve all versions in following structure:
     * [
     *    heads => [
     *       'branch name' => 'download path',
     *       ...
     *    ],
     *    tags => [
     *       'tag name' => 'download path',
     *       ....
     *    ]
     * ]
     *
     * @return array|null
     */
    public function getVersions()
    {
        if ($this->versions === null) {
            $this->retrieveVersions();
        }

        return $this->versions;
    }

    /**
     * Checks if given version is a tag
     * returns true if it is a tag, false if it is a branch (in heads), exception when not found
     *
     * @param string $version exact version number
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function isTag($version)
    {
        if (!$this->stable && array_key_exists($version, $this->getVersions()['heads'])) {
            return false;
        } elseif (array_key_exists($version, $this->getVersions()['tags'])) {
            return true;
        } else {
            throw new \InvalidArgumentException('Invalid version given!');
        }
    }

    /**
     * Retrieve all versions from remote (github)
     *
     * @return array
     */
    protected function retrieveVersions()
    {
        if ($this->versions === null) {
            $repository = escapeshellarg($this->joomlaRepository);
            $result = `git ls-remote $repository | grep -E 'refs/(tags|heads)' | grep -v '{}'`;
            $refs   = array_filter(explode(PHP_EOL, $result));

            $versions = [];
            $pattern  = '/^[a-z0-9]+\s+refs\/(heads|tags)\/([a-z0-9\.\-_]+)$/i';
            foreach ($refs as $ref) {
                if (preg_match($pattern, $ref, $matches)) {

                    if ($matches[1] == 'tags') {
                        if (preg_match('/^1\.*/', $matches[2]) || !preg_match('/^\d\.\d+/', $matches[2])) {
                            continue;
                        }
                    }

                    if ($matches[1] == 'heads') {
                        $tarBall = $this->joomlaTarballBaseUrl . '/' . $matches[2];
                    } else {
                        $tarBall = $this->joomlaArchiveBaseUrl . '/' . $matches[2] . '.tar.gz';
                    }

                    $versions[$matches[1]][$matches[2]] = $tarBall;
                }
            }
            $this->versions = $versions;
            $this->storeVersionsInCache($versions);
        }

        return $this->versions;
    }

    /**
     * Load versions from cache
     *
     * @return void
     */
    protected function retrieveVersionsFromCache()
    {
        // check if file exists and is new enough
        if ($this->cachePath && file_exists($this->cachePath)) {
            if (filemtime($this->cachePath) < (time() - $this->cacheExpire)) {
                unlink($this->cachePath);
            } else {
                $versions = json_decode(file_get_contents($this->cachePath), true);
                if ($versions) {
                    $this->versions = $versions;
                }
            }
        }
    }

    /**
     * Store versions in cache
     *
     * @param array $versions from getVersions() method
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function storeVersionsInCache(Array $versions)
    {
        if ($this->cachePath) {
            $bytes = file_put_contents($this->cachePath, json_encode($versions));
            if ($bytes === false || $bytes === 0) {
                throw new \RuntimeException('Writing versions to cache file failed. ' . $this->cachePath);
            }
        }
    }

    /**
     * Set github repository to retrieve versions from
     *
     * @param string $repository github repository
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function setJoomlaRepository($repository)
    {
        if (!is_string($repository)) {
            throw new \InvalidArgumentException('Invalid type, string expected');
        }

        $this->joomlaRepository = $repository;
        $info = pathinfo($this->joomlaRepository);

        $this->joomlaTarballBaseUrl = $info['dirname'] . '/' . $info['filename'] . '/tarball';
        $this->joomlaArchiveBaseUrl = $info['dirname'] . '/' . $info['filename'] . '/archive';

        return $this;
    }

    /**
     * Get currently configured joomla repository
     *
     * @return string
     */
    public function getJoomlaRepository()
    {
        return $this->joomlaRepository;
    }

    /**
     * Set Joomla stability
     *
     * @var bool
     *
     * @return void
     */
    public function setStability($stable)
    {
        $this->stable = $stable;
    }
}