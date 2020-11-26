<?php

namespace Hj\Builder;

use Doctrine\Instantiator\Exception\ExceptionInterface;
use Hj\CellAdapterPusher;
use Hj\File\RowAdapter;
use Hj\Strategy\Header\HeaderExtraction;
use PhpOffice\PhpSpreadsheet\Worksheet\RowIterator;

/**
 * Class RowAdapterBuilder
 * @package Hj\Builder
 */
class RowAdapterBuilder
{
    /**
     * @var int
     */
    private int $currentRowIndex = 2;

    /**
     * @var CellAdapterPusher
     */
    private CellAdapterPusher $cellAdapterPusher;

    /**
     * RowAdapterBuilder constructor.
     * @param CellAdapterPusher $cellAdapterPusher
     */
    public function __construct(
        CellAdapterPusher $cellAdapterPusher
    )
    {
        $this->cellAdapterPusher = $cellAdapterPusher;
    }

    /**
     * @return int
     */
    public function getCurrentRowIndex(): int
    {
        return $this->currentRowIndex;
    }

    /**
     * @param int $currentRowIndex
     */
    public function setCurrentRowIndex(int $currentRowIndex): void
    {
        $this->currentRowIndex = $currentRowIndex;
    }

    /**
     * @param RowAdapter $rowAdapter
     * @param HeaderExtraction $headerExtractionStrategy
     * @param RowIterator $rowIterator
     *
     * @return RowAdapter
     *
     * @throws ExceptionInterface
     */
    public function build(
        RowAdapter $rowAdapter,
        HeaderExtraction $headerExtractionStrategy,
        RowIterator $rowIterator
    ): RowAdapter
    {
        $extractedHeaders = $headerExtractionStrategy->getExtractedHeader();

        $rowAdapter->setIndex($this->currentRowIndex);

        $currentRow = $rowIterator->current();
        $currentCellIterator = $currentRow->getCellIterator();

        $currentCellIndex = 0;

        while ($currentCellIterator->valid()) {
            $currentCell = $currentCellIterator->current();
            $currentColumnName = $currentCell->getColumn();

            $currentCellIndex = $this->cellAdapterPusher->push(
                $currentCellIndex,
                $rowAdapter,
                $currentCell,
                $currentColumnName,
                $extractedHeaders,
                $this->currentRowIndex
            );

            $currentCellIterator->next();
        }

        // we set the next current index for next iteration
        $this->currentRowIndex++;

        return $rowAdapter;
    }
}