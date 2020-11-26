<?php
/**
 * User: h.jacquir
 * Date: 18/02/2020
 * Time: 13:16
 */

namespace Hj\File;

use Hj\File\Field\AbstractField;

/**
 * Class RowAdapter
 * @package Hj\File
 */
class RowAdapter
{
    /**
     * @var CellAdapter[]
     */
    private array $cellAdapters = [];

    /**
     * @var int
     */
    private $index;

    public function rewindCellAdapters()
    {
        $this->cellAdapters = [];
    }

    /**
     * @param CellAdapter $cellAdapter
     */
    public function addCellAdapter(CellAdapter $cellAdapter)
    {
        array_push($this->cellAdapters, $cellAdapter);
    }

    /**
     * @return CellAdapter[]
     */
    public function getCellAdapters()
    {
        return $this->cellAdapters;
    }

    /**
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @param int $index
     */
    public function setIndex($index)
    {
        $this->index = $index;
    }

    /**
     * @param AbstractField $field
     * @return string|null
     */
    public function getCellNormalizedValueByField(AbstractField $field)
    {
        foreach ($this->getCellAdapters() as $cellAdapter) {
            if ($cellAdapter->getAssociatedHeader() === $field->getExpectedHeaderValue()) {
                return $cellAdapter->getNormalizedValue();
            }
        }

        return null;
    }

    /**
     * @param AbstractField $field
     * @return string|null
     */
    public function getCellInitialValueByField(AbstractField $field)
    {
        foreach ($this->getCellAdapters() as $cellAdapter) {
            if ($cellAdapter->getAssociatedHeader() === $field->getExpectedHeaderValue()) {
                return $cellAdapter->getCell()->getValue();
            }
        }

        return null;
    }
}