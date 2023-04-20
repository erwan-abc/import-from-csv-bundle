<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Contao\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use League\Csv\Exception;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;

class MountAppAjaxController extends AbstractController
{

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly RequestStack $requestStack,
        private readonly string $projectDir,
        private readonly string $csrfTokenName,
        private readonly int $perRequest,
    )
    {

    }

    /**
     * @throws Exception
     */
    public function appMountAction(): JsonResponse
    {
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $importFromCsvModelAdapter = $this->framework->getAdapter(ImportFromCsvModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $readerAdapter = $this->framework->getAdapter(Reader::class);

        $request = $this->requestStack->getCurrentRequest();
        $token = $request->query->get('token');
        $id = $request->query->get('id');
        $taskId = $request->query->get('taskId');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new \Exception('Invalid token!');
        }

        $objModel = $importFromCsvModelAdapter->findByPk($id);

        if (null === $objModel) {
            throw new \Exception('Import from csv model not found.');
        }

        $arrData['model'] = $objModel->row();

        $objFile = $filesModelAdapter->findByUuid($objModel->fileSRC);

        $arrData['model']['fileSRC'] = $objFile ? $objFile->path : '';
        $arrData['model']['selectedFields'] = $stringUtilAdapter->deserialize($objModel->selectedFields, true);
        $arrData['model']['skipValidationFields'] = $stringUtilAdapter->deserialize($objModel->skipValidationFields, true);

        $count = 0;
        $offset = (int) $objModel->offset;
        $limit = (int) $objModel->limit;

        if ($objFile) {
            $objCsvReader = $readerAdapter->createFromPath($this->projectDir.'/'.$objFile->path, 'r');
            $objCsvReader->setHeaderOffset(0);
            $count = (int) $objCsvReader->count();
        }

        $intRows = $offset > $count ? 0 : $count - $offset;

        if ($limit > 0) {
            $intRows = min($intRows, $limit);
        }

        $arrUrl = [];
        $countRequests = ceil($intRows / $this->perRequest);

        for ($i = 0; $i < $countRequests; ++$i) {
            $pending = $intRows - $i * $this->perRequest;

            if ($pending < $this->perRequest) {
                $limit = $pending;
            } else {
                $limit = $this->perRequest;
            }

            $arrUrl[] = sprintf(
                'contao?do=import_from_csv&key=importAction&id=%s&taskId=%s&offset=%s&limit=%s&req_num=%stoken=%s',
                $id,
                $taskId,
                $offset + $i * $this->perRequest,
                $limit,
                $i + 1,
                $token,
            );
        }

        $arrData['model']['limit'] = $objModel->limit;
        $arrData['model']['offset'] = $objModel->offset;
        $arrData['model']['count'] = $count;
        $arrData['urlStack'] = $arrUrl;

        $json = ['data' => $arrData];

        $response = new JsonResponse($json);

        throw new ResponseException($response);
    }
}
