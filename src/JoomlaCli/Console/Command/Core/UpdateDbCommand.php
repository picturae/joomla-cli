<?php

namespace JoomlaCli\Console\Command\Core;

use JoomlaCli\Console\Joomla\Bootstrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateDbCommand
 *
 * Updates mysql database (running the fix method) if any migrations have not been done
 *
 * @package JoomlaCli\Console\Command\Core
 * @author  Mark van Duijker <https://github.com/mvanduijker>
 */
class UpdateDbCommand extends Command
{
    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('core:update-db')
            ->setDescription('Update joomla database')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to valid working joomla installation',
                getcwd()
            );
    }

    /**
     * Execute the program
     *
     * @param InputInterface  $input  cli input object
     * @param OutputInterface $output cli output object
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->check($input);

        $this->updateDb($input);
    }

    /**
     * Check if program can be run
     *
     * @param InputInterface $input cli input object
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function check(InputInterface $input)
    {
        // check valid joomla installation
        $path = $input->getOption('path');
        if (!file_exists($path)) {
            throw new \RuntimeException('Path does not exist: ' . $path);
        }

        if (!is_dir($path)) {
            throw new \RuntimeException('Path is not a directory: '. $path);
        }

        if (!file_exists(rtrim($path, '/') . '/configuration.php')) {
            throw new \RuntimeException('configuration.php not found, probably no joomla installation in: ' . $path);
        }
    }

    /**
     * Performs the database update
     *
     * @param InputInterface $input cli input object
     *
     * @return void
     */
    protected function updateDb(InputInterface $input)
    {
        $joomlaApp = Bootstrapper::getApplication($input->getOption('path'));

        // Load the update component's model to run the cleanup methods
        \JModelLegacy::addIncludePath($joomlaApp->getPath() . '/administrator/components/com_joomlaupdate/models', 'JoomlaupdateModel');

        /** @var JoomlaupdateModelDefault $model */
        $model = \JModelLegacy::getInstance('default', 'JoomlaupdateModel');

        // Make sure we got the model
        if (!($model instanceof \JoomlaupdateModelDefault)) {
        	echo 'Could not load update component model, please check the logs for additional details.' . PHP_EOL;
        	exit(1);
        }

        // Finalize the update
        if ($model->finaliseUpgrade() === false) {
        	echo 'Failed to finalize the upgrade, please check the logs for additional details.' . PHP_EOL;
        	exit(1);
        }

        // Cleanup after the update
        $model->cleanUp();

        \JModelLegacy::addIncludePath($joomlaApp->getPath() . '/administrator/components/com_installer/models', 'InstallerModel');
        /* @var $model \InstallerModelDatabase */
        $model = \JModelLegacy::getInstance('Database', 'InstallerModel');
        $model->fix();
    }


}
