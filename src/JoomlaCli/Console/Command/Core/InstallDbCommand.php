<?php

namespace JoomlaCli\Console\Command\Core;

use JoomlaCli\Console\Model\Joomla\Download;
use JoomlaCli\Console\Model\Joomla\Versions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class InstallDbCommand
 *
 * Installs Mysql database from given joomla version
 *
 * @package JoomlaCli\Console\Command\Core
 */
class InstallDbCommand extends Command
{
    /**
     * @var Versions
     */
    protected $versionsModel;

    /**
     * @var Array
     */
    protected $version;

    /**
     * @var string
     */
    protected $dbname;

    /**
     * @var string
     */
    protected $dbuser;

    /**
     * @var string
     */
    protected $dbpassword;

    /**
     * @var string
     */
    protected $dbhost;

    /**
     * @var string
     */
    protected $dbprefix;

    /**
     * @var string
     */
    protected $target;

    /**
     * @var bool
     */
    protected $installSampleData;


    /**
     * Constructor
     *
     * @param Download $downloadModel model to handle Joomla downloads
     * @param Versions $versionsModel model to handle Joomla versions
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
            ->setName('core:install-db')
            ->setDescription('Install default joomla database')
            ->addOption(
                'dbname',
                null,
                InputOption::VALUE_REQUIRED,
                'Mysql database name',
                'joomla'
            )
            ->addOption(
                'dbuser',
                null,
                InputOption::VALUE_REQUIRED,
                'Mysql database user',
                'root'
            )
            ->addOption(
                'dbpass',
                null,
                InputOption::VALUE_REQUIRED,
                'Mysql Database password',
                ''
            )
            ->addOption(
                'dbhost',
                null,
                InputOption::VALUE_REQUIRED,
                'Mysql Host',
                'localhost'
            )
            ->addOption(
                'dbprefix',
                null,
                InputOption::VALUE_REQUIRED,
                'Mysql Host',
                'jos_'
            )
            ->addOption(
                'install-sample-data',
                null,
                InputOption::VALUE_NONE,
                'Install sample data'
            )
            ->addOption(
                'joomla-version',
                null,
                InputOption::VALUE_REQUIRED,
                'Joomla version',
                '3.*'
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

        $this->version = $this->versionsModel->getVersion($input->getOption('joomla-version'), filter_var($input->getOption('stable'), FILTER_VALIDATE_BOOLEAN));
        $this->dbname = $input->getOption('dbname');
        $this->dbuser = $input->getOption('dbuser');
        $this->dbpassword = $input->getOption('dbpass');
        $this->dbhost = $input->getOption('dbhost');
        $this->dbprefix = $input->getOption('dbprefix');
        $this->target = sys_get_temp_dir() . '/' . uniqid('Joomla-cli');
        $this->installSampleData = $input->getOption('install-sample-data');

        $this->check($input);
        $this->doInstallDb($output);

    }

    /**
     * Perform some checks before we start executing real stuff
     *
     * @param InputInterface $input input object to retrieve cli input vars
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function check(InputInterface $input)
    {
        if (!$this->version) {
            throw new \RuntimeException('Could not find version of ' . $input->getOption('joomla-version'));
        }
    }

    /**
     * Perform the sql statements, also downloads given Joomla version so we can get the install.sql
     *
     * @param OutputInterface $output output object to perform output actions
     *
     * @throws \RuntimeException
     * @throws \Exception
     *
     * @return void
     */
    protected function doInstallDb(OutputInterface $output)
    {
        $release = array_keys($this->version)[0];
        $url = array_values($this->version)[0];

        $this->downloadModel->download(
            $url,
            $release,
            $this->target,
            $this->versionsModel->isTag($release)
        );

        // do sql stuff
        try {
            $this->createDatabase();
            $this->importDatabase();
            $this->createAdminUser();

        } catch (\Exception $e) {
            $this->cleanUp($this->target);

            throw $e;
        }

        $this->cleanUp($this->target);
    }

    /**
     * Create mysql database
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function createDatabase()
    {
        $result = exec(
            sprintf(
                'echo \'CREATE DATABASE %s CHARACTER SET utf8\' | mysql -u %s %s -h %s',
                escapeshellarg($this->dbname),
                escapeshellarg($this->dbuser),
                $this->dbpassword ? '-p' . escapeshellarg($this->dbpassword) : '',
                escapeshellarg($this->dbhost)
            )
        );

        if (!empty($result)) {
            throw new \RuntimeException(
                sprintf('Database creation error %s. Output: %s', $this->dbname, $result)
            );
        }
    }

    /**
     * Import installation databases
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function importDatabase()
    {
        $dumps = array($this->target . '/installation/sql/mysql/joomla.sql');

        if ($this->installSampleData) {
            $dumps[] = $this->target . '/installation/sql/mysql/sample_default.sql';
        }

        foreach ($dumps as $dump) {
            $contents = file_get_contents($dump);
            $contents = str_replace('#__', $this->dbprefix, $contents);
            file_put_contents($dump, $contents);

            $result = exec(
                sprintf(
                    'mysql -u %s %s -h %s %s < %s',
                    escapeshellarg($this->dbuser),
                    $this->dbpassword ? '-p' . escapeshellarg($this->dbpassword) : '',
                    escapeshellarg($this->dbhost),
                    escapeshellarg($this->dbname),
                    escapeshellarg($dump)
                )
            );

            if (!empty($result)) { // MySQL returned an error
                throw new \RuntimeException(sprintf('Cannot import database "%s". Output: %s', $dump, $result));
            }
        }
    }

    /**
     * Create admin user with password admin
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function createAdminUser()
    {
        $release = array_keys($this->version)[0];
        if (is_numeric(substr($release, 0, 1)) && substr($release, 0, 1) < 3) {
            // joomla 2.x admin user insert query
            $query = 'INSERT INTO `#__users` ' .
                '(`id`, `name`, `username`, `email`, `password`, `usertype`, `block`, ' .
                '`sendEmail`, `registerDate`, `lastvisitDate`, `activation`, `params`, ' .
                '`lastResetTime`, `resetCount`) VALUES ' .
                '(300, \'Super User\', \'admin\', \'admin@example.com\', \'$P$DNZKT30Km/anLr4MyojTpxoOFx2H3H.\', \'deprecated\',' .
                '0, 1, \'2014-08-04 22:23:33\', \'2014-08-04 22:23:33\', \'0\', \'\', \'0000-00-00 00:00:00\', 0);';
            $query .= ' INSERT INTO `#__user_usergroup_map` (`user_id`, `group_id`) VALUE (300, 8);';
        } else {
            // joomla 3.x admin user insert query
            $query = 'INSERT INTO `#__users` ' .
                '(`id`, `name`, `username`, `email`, `password`, `block`, ' .
                '`sendEmail`, `registerDate`, `lastvisitDate`, `activation`, `params`, ' .
                '`lastResetTime`, `resetCount`) VALUES ' .
                '(300, \'Super User\', \'admin\', \'admin@example.com\', \'$2y$10$eXr9/sI4r6/XtSVzO52KpuHn2QHZoPxoiMBKZRanDuXokjKB08s0.\', ' .
                '0, 1, \'2014-08-04 22:23:33\', \'2014-08-04 22:23:33\', \'0\', ' .
                '\'{\"admin_style\":\"\",\"admin_language\":\"\",\"language\":\"\",\"editor\":\"\",\"helpsite\":\"\",\"timezone\":\"\"}\', \'0000-00-00 00:00:00\', 0);';
            $query .= ' INSERT INTO `#__user_usergroup_map` (`user_id`, `group_id`) VALUE (300, 8);';
        }

        $query = str_replace('#__', $this->dbprefix, $query);

        $result = exec(
            sprintf(
                'echo %s | mysql -u %s %s -h %s %s',
                escapeshellarg($query),
                escapeshellarg($this->dbuser),
                $this->dbpassword ? '-p' . escapeshellarg($this->dbpassword) : '',
                escapeshellarg($this->dbhost),
                escapeshellarg($this->dbname)
            )
        );

        if (!empty($result)) {
            throw new \RuntimeException(
                sprintf('Admin user creation error %s. Output: %s', $this->dbname, $result)
            );
        }
    }

    /**
     * Cleanup created temp files
     *
     * @param string $tempTarget temp joomla installation directory
     *
     * @return void
     */
    protected function cleanUp($tempTarget)
    {
        $target = escapeshellarg($tempTarget);
        `rm -rf $target`;
    }
}