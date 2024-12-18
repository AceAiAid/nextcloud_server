<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\Conversion;

use OC\AppFramework\Bootstrap\Coordinator;
use OCP\Conversion\ConversionMimeTuple;
use OCP\Conversion\IConversionManager;
use OCP\Conversion\IConversionProvider;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\ITempManager;
use OCP\PreConditionNotMetException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class ConversionManager implements IConversionManager {
	/** @var ?IConversionProvider[] */
	private ?array $providers = null;

	public function __construct(
		private Coordinator $coordinator,
		private ContainerInterface $serverContainer,
		private IRootFolder $rootFolder,
		private ITempManager $tempManager,
		private LoggerInterface $logger,
	) {
	}

	public function hasProviders(): bool {
		$context = $this->coordinator->getRegistrationContext();
		return !empty($context->getConversionProviders());
	}

	public function getMimeTypes(): array {
		$mimeTypes = [];

		foreach ($this->getProviders() as $provider) {
			$mimeTypes[] = $provider->getSupportedMimeTypes();
		}

		return $mimeTypes;
	}

	public function convert(File $file, string $targetMimeType, ?string $destination = null): string {
		if (!$this->hasProviders()) {
			throw new PreConditionNotMetException('No conversion providers available');
		}

		$fileMimeType = $file->getMimetype();
		foreach ($this->getProviders() as $provider) {
			$availableProviderConversions = array_filter(
				$provider->getSupportedMimeTypes(),
				function (ConversionMimeTuple $mimeTuple) use ($fileMimeType, $targetMimeType) {
					['from' => $from, 'to' => $to] = $mimeTuple->jsonSerialize();

					return $from === $fileMimeType && $to === $targetMimeType;
				}
			);

			if (!empty($availableProviderConversions)) {
				$convertedFile = $provider->convertFile($file, $targetMimeType);

				if ($destination !== null) {
					$convertedFile = $this->writeToDestination($destination, $convertedFile);
					return $convertedFile->getInternalPath();
				}

				$tmp = $this->tempManager->getTemporaryFile();
				file_put_contents($tmp, $convertedFile);

				return $tmp;
			}
		}

		throw new RuntimeException('Could not convert file');
	}

	public function getProviders(): array {
		if ($this->providers !== null) {
			return $this->providers;
		}

		$context = $this->coordinator->getRegistrationContext();
		$this->providers = [];

		foreach ($context->getConversionProviders() as $providerRegistration) {
			$class = $providerRegistration->getService();

			try {
				$this->providers[$class] = $this->serverContainer->get($class);
			} catch (NotFoundExceptionInterface|ContainerExceptionInterface|Throwable $e) {
				$this->logger->error('Failed to load conversion provider ' . $class, [
					'exception' => $e,
				]);
			}
		}

		return $this->providers;
	}

	private function writeToDestination(string $destination, mixed $content): File {
		return $this->rootFolder->newFile($destination, $content);
	}
}
