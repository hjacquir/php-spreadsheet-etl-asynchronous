<?php
/**
 * User: h.jacquir
 * Date: 21/01/2020
 * Time: 13:42
 */

namespace Hj\Command;

use Buuum\Ftp\Connection;
use Buuum\Ftp\FtpWrapper;
use Hj\Adapter\HtmlFormatterAdapter;
use Hj\Collector\CollectorIterator;
use Hj\Collector\ErrorCollector;
use Hj\Directory\BaseDirectory;
use Hj\Directory\WaitingDirectory;
use Hj\Error\File\DirectoryNotExistError;
use Hj\Error\FtpFailureConnexion;
use Hj\Error\FtpFailureDownloadFile;
use Hj\Factory\FilePathConfigFactory;
use Hj\Factory\FtpConfigFactory;
use Hj\Factory\MailConfigFactory;
use Hj\Factory\MailHandlerFactory;
use Hj\Factory\SmtpConfigFactory;
use Hj\Helper\CatchedErrorHandler;
use Hj\Notifier\MailNotifier;
use Hj\Processor\FileProcessor;
use Hj\Strategy\File\MigrateFileStrategy;
use Hj\Strategy\Notifier\NotifyAdminStrategyOnSuccesfull;
use Hj\Strategy\Notifier\NotifyAdminStrategyWhenErrorOccured;
use Hj\Validator\ConfigFileValidator;
use Monolog\Logger;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MigrateFileFromDistant
 * @package Hj\Command
 */
class MigrateFileFromDistant extends AbstractCommand
{
    const COMMAND_NAME = 'spreadsheet-etl:migrate';
    const YAML_CONTEXT_FILE_ARGUMENT = 'yamlContextFilePath';
    const YAML_CONTEXT_FILE_ARGUMENT_DESCRIPTION = 'The path to the file configuration file in Yaml';

    /**
     */
    private $logger;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var ErrorCollector
     */
    private $errorCollector;

    /**
     * ExtractCommand constructor.
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription("this command allows you to migrate the files deposited on the remote FTP server.");
        $this
            ->addArgument(
                self::YAML_CONTEXT_FILE_ARGUMENT,
                InputArgument::REQUIRED,
                self::YAML_CONTEXT_FILE_ARGUMENT_DESCRIPTION
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->debug("Starting file migration from the ftp server ...");

        $yamlConfigFilePath = $input->getArgument(self::YAML_CONTEXT_FILE_ARGUMENT);

        $ftpConfigs = (new FtpConfigFactory())->createConfig($yamlConfigFilePath);

        $ftpHost = $ftpConfigs->getHost()->getValue();
        $ftpUsername = $ftpConfigs->getUserName()->getValue();
        $ftpPassword = $ftpConfigs->getPassword()->getValue();
        $ftpPort = $ftpConfigs->getPort()->getValue();

        $this->errorCollector = new ErrorCollector(
            new CollectorIterator()
        );
        $connection = new Connection(
            $ftpHost,
            $ftpUsername,
            $ftpPassword,
            $ftpPort,
            10,
            true
        );
        $migrationMessageOnSuccesfull = '';
        try {
            $connection->open();
            $ftp = new FtpWrapper($connection);
            $filePathConfig = (new FilePathConfigFactory())->createConfig($yamlConfigFilePath);

            $waitingDirectory = new WaitingDirectory(
                new BaseDirectory(
                    $filePathConfig->getWaitingDir()->getValue(),
                    new CatchedErrorHandler(
                        $this->errorCollector
                    ),
                    new DirectoryNotExistError()
                )
            );
            $migrationStrategy = new MigrateFileStrategy(
                $ftpConfigs,
                $waitingDirectory,
                $ftp,
                $this->logger,
                $this->errorCollector,
                new FtpFailureDownloadFile()
            );
            $processor = new FileProcessor(
                $this->logger,
                [
                    $migrationStrategy,
                ]
            );
            $processor->process();
            $migrationMessageOnSuccesfull = $migrationStrategy->getMigrationMessage();
        } catch (\Exception $e) {
            $this->errorCollector->addError(
                new FtpFailureConnexion($e)
            );
            echo $e->getMessage();
        }

        $swiftMessage = new Swift_Message();

        $smtpConfigs = (new SmtpConfigFactory())->createConfig($yamlConfigFilePath);

        $handlerFactory = new MailHandlerFactory(
            new Swift_Mailer(
                new Swift_SmtpTransport(
                    $smtpConfigs->getHost()->getValue()
                )
            )
        );

        $mailsConfigs = (new MailConfigFactory())->createConfig($yamlConfigFilePath);

        $notifyStrategies = [
            new NotifyAdminStrategyWhenErrorOccured(
                $mailsConfigs,
                $this->errorCollector,
                "Spreadsheet-etl had encountered the belows errors when migrating file from the server : \n\n"
            ),
            new NotifyAdminStrategyOnSuccesfull(
                $mailsConfigs,
                $this->errorCollector,
                'Spreadsheet-etl : file migration from FTP server',
                $migrationMessageOnSuccesfull
            )
        ];

        $mailNotifier = new MailNotifier(
            $mailsConfigs,
            new HtmlFormatterAdapter(
                "d/m/Y"
            ),
            $notifyStrategies,
            $swiftMessage,
            $handlerFactory,
            $this->logger,
            []
        );

        $mailNotifier->notify();

        $this->logger->debug("End of file migration from ftp server.");
        return 0;
    }

    /**
     * @return ErrorCollector
     */
    public function getErrorCollector()
    {
        return $this->errorCollector;
    }
}