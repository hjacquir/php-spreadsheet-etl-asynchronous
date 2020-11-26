<?php

namespace Hj\Builder;

use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Instantiator\Instantiator;
use Hj\File\CellAdapter;
use Hj\File\RowAdapter;
use Hj\Normalizer\AccentsRemoverNormalizer;
use Hj\Normalizer\DateStringExcelNormalizer;
use Hj\Normalizer\ToUpperNormalizer;
use Hj\Normalizer\TrimNormalizer;
use Hj\Strategy\Header\HeaderExtraction;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

/**
 * Class CellAdapterBuilder
 * @package Hj\Builder
 */
class CellAdapterBuilder
{
    /**
     * @var DateStringExcelNormalizer
     */
    private DateStringExcelNormalizer $dateStringExcelNormalizer;

    /**
     * @var TrimNormalizer
     */
    private TrimNormalizer  $trimNormalizer;

    /**
     * @var ToUpperNormalizer
     */
    private ToUpperNormalizer $toUpperNormalizer;

    /**
     * @var AccentsRemoverNormalizer
     */
    private AccentsRemoverNormalizer $accentsRemoverNormalizer;

    /**
     * @var Instantiator
     */
    private Instantiator $instantiator;

    /**
     * @var HeaderExtraction
     */
    private HeaderExtraction $headerExtractionStrategy;

    /**
     * CellAdapterBuilder constructor.
     * @param HeaderExtraction $headerExtractionStrategy
     * @param DateStringExcelNormalizer $dateStringExcelNormalizer
     * @param TrimNormalizer $trimNormalizer
     * @param ToUpperNormalizer $toUpperNormalizer
     * @param AccentsRemoverNormalizer $accentsRemoverNormalizer
     * @param Instantiator $instantiator
     */
    public function __construct(
        HeaderExtraction $headerExtractionStrategy,
        DateStringExcelNormalizer $dateStringExcelNormalizer,
        TrimNormalizer $trimNormalizer,
        ToUpperNormalizer $toUpperNormalizer,
        AccentsRemoverNormalizer $accentsRemoverNormalizer,
        Instantiator $instantiator
    )
    {
        $this->headerExtractionStrategy = $headerExtractionStrategy;
        $this->dateStringExcelNormalizer = $dateStringExcelNormalizer;
        $this->trimNormalizer = $trimNormalizer;
        $this->toUpperNormalizer = $toUpperNormalizer;
        $this->accentsRemoverNormalizer = $accentsRemoverNormalizer;
        $this->instantiator = $instantiator;
    }

    /**
     * @param string $currentColumnName
     * @param int $associatedRowIndex
     * @param Cell $currentCell
     * @param RowAdapter $rowAdapter
     *
     * @return CellAdapter
     *
     * @throws ExceptionInterface
     */
    public function build(
        string $currentColumnName,
        int $associatedRowIndex,
        Cell $currentCell,
        RowAdapter $rowAdapter
    ) : CellAdapter
    {
        // we need to use a clone of original object to avoid to create an new instance
        /** @var CellAdapter $cellAdapter */
        $cellAdapter = $this->instantiator
            ->instantiate(CellAdapter::class);

        $cellAdapter->setRowIndex($associatedRowIndex);
        $cellAdapter->setColumnName($currentColumnName);
        $cellAdapter->setCell($currentCell);

        $associatedNormalizedHeader = $this->headerExtractionStrategy
            ->getNormalizedHeaderByColumnName($currentColumnName);
        $cellAdapter->setAssociatedHeader($associatedNormalizedHeader);

        $associatedInitialHeader = $this->headerExtractionStrategy
            ->getInitialHeaderByColumnName($currentColumnName);
        $cellAdapter->setInitialAssociatedHeader($associatedInitialHeader);

        $dateNormalizedValue = $this->dateStringExcelNormalizer
            ->normalize($cellAdapter);
        $trimmedValue = $this->trimNormalizer
            ->normalize($dateNormalizedValue);
        $accentRemovedValue = $this->accentsRemoverNormalizer
            ->normalize($trimmedValue);
        $upperCasedValue = $this->toUpperNormalizer
            ->normalize($accentRemovedValue);

        $cellAdapter->setNormalizedValue($upperCasedValue);

        $cellAdapter->setRowAdapter($rowAdapter);

        return $cellAdapter;
    }
}