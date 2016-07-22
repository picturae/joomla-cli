<?php

namespace JoomlaCli\Console\Command\Extension\Language;

use JoomlaCli\Console\Joomla\Bootstrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class InstallCommand
 *
 * Installs a language in Joomla
 *
 * @package JoomlaCli\Console\Command\Extension\Language
 */
class InstallCommand extends Command
{
    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('extension:language:install')
            ->setDescription('Install a joomla language')
            ->addArgument(
                'language',
                InputArgument::REQUIRED,
                'Language to install'
            )
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
        $this->installLanguage($input, $output);

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
     * Install language
     *
     * @param InputInterface  $input  input cli object
     * @param OutputInterface $output output cli object
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function installLanguage(InputInterface $input, OutputInterface $output)
    {
        $joomlaApp = Bootstrapper::getApplication($input->getOption('path'));
        $joomlaApp->set('list_limit', 10000);
        $lang = $input->getArgument('language');

        // check if language already installed
        \JModelLegacy::addIncludePath($joomlaApp->getPath() . '/administrator/components/com_installer/models', 'InstallerModel');
        /* @var $model \InstallerModelManage */
        $model = \JModelLegacy::getInstance('Manage', 'InstallerModel');
        $items = $model->getItems();

        foreach ($items as $item) {
            if ($item->type !== 'language') continue;
            if (strtoupper($item->element) === strtoupper($lang)) {
              // language in database but not on disk, lets cleanup database first so we can install
              $db = \JFactory::getDbo();
              $db->setQuery('DELETE FROM #__extensions WHERE type=' . $db->quote('language') . ' AND element=' . $db->quote($item->element));
              $db->query();
              $db->setQuery('DELETE FROM #__extensions WHERE type=' . $db->quote('package') . ' AND element=' . $db->quote('pkg_' . $item->element));
              $db->query();
              break;
            }
        }

        \JModelLegacy::addIncludePath($joomlaApp->getPath() . '/administrator/components/com_installer/models', 'InstallerModel');
        /* @var $model \InstallerModelLanguages */
        $model = \JModelLegacy::getInstance('Languages', 'InstallerModel');
        $model->findLanguages();
        $items = $model->getItems();
        $table = \JTable::getInstance('update');

        foreach ($items as $item) {

            $table->load($item->update_id);
            $key = preg_replace('/^pkg_/i', '', $table->element);

            if (strtoupper($key) === strtoupper($lang)) {

                $output->writeln('<info>Installing language '. $lang .'</info>');
                $model->install([$item->update_id]);
                return;
            }
        }

        throw new \RuntimeException('Language ' . $lang . ' not found!');

    }
}
