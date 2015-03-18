<?php

namespace JoomlaCli\Console\Command\Core;

use JoomlaCli\Console\Model\Joomla\Download;
use \JoomlaCli\Console\Model\Joomla\Versions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DownloadCommand
 *
 * @package JoomlaCli\Console\Command\Core
 */
class DownloadCommand extends Command
{
    /**
     * @var string
     */
    protected $target;

    /**
     * @var bool
     */
    protected $keepInstallationFolder;

    /**
     * @var Download
     */
    protected $downloadModel;

    /**
     * @var Versions
     */
    protected $versionsModel;

    /**
     * @var Array
     */
    protected $version;

    /**
     * Constructor
     *
     * @param Download $downloadModel model to handle downloads
     * @param Versions $versionsModel model to handle versions
     */
    public function __construct(Download $downloadModel, Versions $versionsModel)
    {
        parent::__construct();
        $this->downloadModel = $downloadModel;
        $this->versionsModel = $versionsModel;
    }

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('core:download')
            ->setDescription('Download joomla core')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL,
                'Target path for Joomla download',
                'joomla'
            )
            ->addOption(
                'joomla-version',
                null,
                InputOption::VALUE_OPTIONAL,
                'Joomla version',
                '3.*'
            )
            ->addOption(
                'keep-installation-folder',
                null,
                InputOption::VALUE_NONE,
                'Keep the installation folder after download'
            )
            ->addOption(
                'stable',
                null,
                InputOption::VALUE_OPTIONAL,
                'Stability',
                'true'
            );
    }

    /**
     * Implement execute method
     *
     * @param InputInterface  $input  input object to retrieve input from commandline
     * @param OutputInterface $output output object to perform output actions
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->target = $input->getOption('path');
        $this->version = $this->versionsModel->getVersion($input->getOption('joomla-version'), filter_var($input->getOption('stable'), FILTER_VALIDATE_BOOLEAN));
        $this->keepInstallationFolder = $input->getOption('keep-installation-folder');

        $this->check();
        $this->doDownload($output);

    }

    /**
     * Perform some checks before we start executing real stuff
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function check()
    {
        if (file_exists($this->target)) {
            throw new \RuntimeException('Directory ' . $this->target . ' already exists!');
        }

        if (!$this->version) {
            throw new \RuntimeException('Could not find version of ' . $this->version);
        }
    }

    /**
     * Perform the download and extraction to target directory
     *
     * @param OutputInterface $output output object to perform output actions
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function doDownload(OutputInterface $output)
    {
        $release = array_keys($this->version)[0];
        $url = array_values($this->version)[0];

        $output->writeln('Downloading and extracting release ' . $release);
        $this->downloadModel->download(
            $url,
            $release,
            $this->target,
            $this->versionsModel->isTag($release)
        );
        $output->writeln('Installed Joomla to ' . $this->target);

        if (!$this->keepInstallationFolder) {
            $installationFolder = escapeshellarg($this->target . '/installation');

            `rm -rf $installationFolder`;

            $output->writeln('Removed installation folder');
        }
    }
}