<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Import;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\FilesModel;
use Contao\StringUtil;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ImportFromCsv.
 */
class ImportFromCsvHelper
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ImportFromCsv
     */
    private $importFromCsv;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, ImportFromCsv $importFromCsv, string $projectDir)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->importFromCsv = $importFromCsv;
        $this->projectDir = $projectDir;
    }

    public function countRows(ImportFromCsvModel $model): ?int
    {
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $objFile = $filesModelAdapter->findByUuid($model->fileSRC);

        if ($objFile) {
            $objCsvReader = Reader::createFromPath($this->projectDir.'/'.$objFile->path, 'r');
            $objCsvReader->setHeaderOffset(0);
            $count = (int) $objCsvReader->count();
            $count -= (int) $model->offset;
            $limit = (int) $model->limit;

            if ($count < 1) {
                return 0;
            }

            if ($limit > $count) {
                return $count;
            }

            return $limit;
        }
    }

    public function importFromModel(ImportFromCsvModel $model, bool $isTestMode = false): bool
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $strTable = $model->importTable;
        $importMode = $model->importMode;
        $arrSelectedFields = $stringUtilAdapter->deserialize($model->selectedFields, true);
        $strDelimiter = $model->fieldSeparator;
        $strEnclosure = $model->fieldEnclosure;
        $intOffset = (int) $model->offset;
        $intLimit = (int) $model->limit;
        $arrSkipValidationFields = $stringUtilAdapter->deserialize($model->skipValidationFields, true);
        $objFile = $filesModelAdapter->findByUuid($model->fileSRC);

        // Call the import class if file exists
        if (is_file($this->projectDir.'/'.$objFile->path)) {
            $objFile = new File($objFile->path);

            if ('csv' === strtolower($objFile->extension)) {
                $this->importFromCsv->importCsv($objFile, $strTable, $importMode, $arrSelectedFields, $strDelimiter, $strEnclosure, '||', $isTestMode, $arrSkipValidationFields, $intOffset, $intLimit);

                return true;
            }
        }

        return false;
    }

    public function getReport()
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        $bag = $session->getBag('markocupic_import_from_csv');

        return $bag->all();
    }
}