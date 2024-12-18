<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OC\Core\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Conversion\IConversionManager;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;

class ConversionApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private IConversionManager $conversionManager,
		private IRootFolder $rootFolder,
		private IL10N $l10n,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[UserRateLimit(limit: 25, period: 120)]
	#[ApiRoute(verb: 'POST', url: '/convert', root: '/conversion')]
	public function convert(int $fileId, string $targetMimeType, ?string $destination = null): DataResponse {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$file = $userFolder->getFirstNodeById($fileId);

		if (!($file instanceof File)) {
			return new DataResponse([
				'message' => $this->l10n->t('File not found'),
			], Http::STATUS_NOT_FOUND);
		}

		try {
			$destination = $userFolder->getFullpath($destination);
			$convertedFile = $this->conversionManager->convert($file, $targetMimeType, $destination);
		} catch (\Exception $e) {
			return new DataResponse([
				'message' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([
			'path' => $convertedFile,
		], Http::STATUS_CREATED);
	}
}
