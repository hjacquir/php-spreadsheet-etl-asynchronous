<?php

namespace Hj\Strategy;

use Doctrine\Instantiator\Exception\ExceptionInterface;
use Hj\Builder\RowAdapterBuilder;
use Hj\Directory\BaseDirectory;
use Hj\Directory\WaitingDirectory;
use Hj\Error\File\GettingSheetFromFileError;
use Hj\Error\File\LoadingFileError;
use Hj\File\RowAdapter;
use Hj\Helper\CatchedErrorHandler;
use Hj\Parser\Parser;
use Hj\Strategy\Header\HeaderExtraction;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;

/**
 * Class RowExtractor
 * @package Hj\Handler
 */
class RowExtractor implements Strategy
{
    /**
     * @var BaseDirectory
     */
    private BaseDirectory $inProcessingDirectory;

    /**
     * @var CatchedErrorHandler
     */
    private CatchedErrorHandler $catchedErrorHandler;

    /**
     * @var GettingSheetFromFileError
     */
    private GettingSheetFromFileError $gettingSheetFromFileError;

    /**
     * @var WaitingDirectory
     */
    private WaitingDirectory $waitingDirectory;

    /**
     * @var LoadingFileError
     */
    private LoadingFileError $loadingFileError;

    /**
     * @var HeaderExtraction
     */
    private HeaderExtraction $headerExtractionStrategy;

    /**
     * @var RowAdapter
     */
    private RowAdapter $rowAdapter;

    /**
     * @var RowAdapterBuilder
     */
    private RowAdapterBuilder $rowAdapterBuilder;

    /**
     * @var Worksheet|null
     */
    private ?Worksheet $currentWorksheet = null;

    /**
     * @var Parser[]
     */
    private array $parsers;

    /**
     * @var IReader|null
     */
    private ?IReader $appropriateReader = null;

    /**
     * @var int
     */
    private int $maxRowSize;

    /**
     * @var DoctrineTransport
     */
    private DoctrineTransport $transport;

    /**
     * RowExtractor constructor.
     * @param DoctrineTransport $transport
     * @param array $parsers
     * @param RowAdapterBuilder $rowAdapterBuilder
     * @param RowAdapter $rowAdapter
     * @param BaseDirectory $inProcessingDirectory
     * @param CatchedErrorHandler $catchedErrorHandler
     * @param GettingSheetFromFileError $gettingSheetFromFileError
     * @param WaitingDirectory $waitingDirectory
     * @param LoadingFileError $loadingFileError
     * @param HeaderExtraction $headerExtractionStrategy
     */
    public function __construct(
        DoctrineTransport $transport,
        array $parsers,
        RowAdapterBuilder $rowAdapterBuilder,
        RowAdapter $rowAdapter,
        BaseDirectory $inProcessingDirectory,
        CatchedErrorHandler $catchedErrorHandler,
        GettingSheetFromFileError $gettingSheetFromFileError,
        WaitingDirectory $waitingDirectory,
        LoadingFileError $loadingFileError,
        HeaderExtraction $headerExtractionStrategy
    )
    {
        $this->transport = $transport;
        $this->parsers = $parsers;
        $this->rowAdapterBuilder = $rowAdapterBuilder;
        $this->rowAdapter = $rowAdapter;
        $this->inProcessingDirectory = $inProcessingDirectory;
        $this->catchedErrorHandler = $catchedErrorHandler;
        $this->gettingSheetFromFileError = $gettingSheetFromFileError;
        $this->waitingDirectory = $waitingDirectory;
        $this->loadingFileError = $loadingFileError;
        $this->headerExtractionStrategy = $headerExtractionStrategy;
    }

    public function isAppropriate()
    {
        return $this->inProcessingDirectory->hasFiles()
            && false === $this->catchedErrorHandler->getErrorCollector()->hasError();
    }

    public function apply()
    {
        $continue = false;

        $this->initializeReader();
        $this->loadCurrentWorkSheet();
        $this->maxRowSize = (int) $this->currentWorksheet->getHighestRow();

        $rowAdapter = $this->extract();

        if (false === empty($rowAdapter->getCellAdapters())) {
            $continue = true;
            $this->transport->send(new Envelope($rowAdapter));
        }

        if ($continue) {
            $this->apply();
        }
    }

    /**
     * @return RowAdapter|mixed
     *
     * @throws ExceptionInterface
     */
    public function extract(): RowAdapter
    {
        $this->rowAdapter->rewindCellAdapters();
        $currentRowIndex = $this->rowAdapterBuilder->getCurrentRowIndex();

        if ($currentRowIndex <= $this->maxRowSize) {
            try {
                $rowIterator = $this->currentWorksheet
                    ->getRowIterator($currentRowIndex);

                if ($rowIterator->valid()) {
                    $this->rowAdapter = $this->rowAdapterBuilder->build(
                        $this->rowAdapter,
                        $this->headerExtractionStrategy,
                        $rowIterator
                    );
                }
            } catch (\Exception $e) {
                $this->catchedErrorHandler->handleErrorOnGettingSheetFailure(
                    $e,
                    $this->gettingSheetFromFileError,
                    $this->waitingDirectory
                );
            }
        }

        return $this->rowAdapter;
    }

    private function initializeReader()
    {
        if (null === $this->appropriateReader) {
            foreach ($this->parsers as $parser) {
                if ($parser->isAppropriate()) {
                    $this->appropriateReader = $parser->getReader();
                }
            }
        }
    }

    private function loadCurrentWorkSheet()
    {
        $filePath = $this->inProcessingDirectory
            ->getCurrentPoppedFileName();

        if (null === $this->currentWorksheet) {
            try {
                $spreadSheet = $this->appropriateReader->load($filePath);
                $this->currentWorksheet = $spreadSheet->getActiveSheet();
            } catch (\Exception $exception) {
                $this->catchedErrorHandler->handleErrorOnFileLoadingFailure(
                    $exception,
                    $this->loadingFileError,
                    $this->waitingDirectory
                );
            }
        }
    }
}