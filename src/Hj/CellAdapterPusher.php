<?php

namespace Hj;

use Doctrine\Instantiator\Exception\ExceptionInterface;
use Hj\Builder\CellAdapterBuilder;
use Hj\File\RowAdapter;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

/**
 * Class CellAdapterPusher
 * @package Hj
 */
class CellAdapterPusher
{
    /**
     * @var CellAdapterBuilder
     */
    private CellAdapterBuilder $cellAdapterBuilder;

    /**
     * CellAdapterPusher constructor.
     * @param CellAdapterBuilder $cellAdapterBuilder
     */
    public function __construct(CellAdapterBuilder $cellAdapterBuilder)
    {
        $this->cellAdapterBuilder = $cellAdapterBuilder;
    }

    /**
     * @param int $currentCellIndex
     * @param RowAdapter $rowAdapter
     * @param Cell $currentCell
     * @param string $currentColumnName
     * @param array $extractedHeaders
     * @param int $currentRowIndex
     *
     * @return int
     * @throws ExceptionInterface
     */
    public function push(
        int $currentCellIndex,
        RowAdapter $rowAdapter,
        Cell $currentCell,
        string $currentColumnName,
        array $extractedHeaders,
        int $currentRowIndex
    ) : int
    {
        // we add the cell only if the header exist
        if (true === $this->currentCellMustBePushed($currentColumnName, $extractedHeaders)) {
            // build the cell adapter
            $cellAdapter = $this->cellAdapterBuilder->build(
                $currentColumnName,
                $currentRowIndex,
                $currentCell,
                $rowAdapter
            );

            // and add it to current row adapter
            $rowAdapter->addCellAdapter($cellAdapter);

            $currentCellIndex++;
        }

        return $currentCellIndex;
    }

    /**
     * @param string $currentColumnName
     * @param array $extractedHeaders
     *
     * @return bool
     */
    private function currentCellMustBePushed(
        string $currentColumnName,
        array $extractedHeaders
    ) : bool
    {
        return array_key_exists($currentColumnName, $extractedHeaders);
    }
}