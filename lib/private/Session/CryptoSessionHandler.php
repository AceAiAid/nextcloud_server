<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OC\Session;

use Exception;
use OCP\IRequest;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;
use SessionHandler;
use function explode;
use function implode;
use function json_decode;
use function OCP\Log\logger;
use function session_decode;
use function session_encode;
use function strlen;

class CryptoSessionHandler extends SessionHandler {

	public function __construct(private ISecureRandom $secureRandom,
		private ICrypto $crypto,
		private LoggerInterface $logger,
		private IRequest $request) {
	}

	public function create_sid(): string {
		$id = parent::create_sid();
		$passphrase = $this->secureRandom->generate(128);
		return implode('|', [$id, $passphrase]);
	}

	/**
	 * Read and decrypt session data
	 *
	 * @param string $id
	 *
	 * @return false|string
	 */
	public function read(string $id): false|string {
		[$sessionId, $passphrase] = self::parseId($id);
		if ($passphrase === null) {
			$passphrase = $this->request->getCookie(CryptoWrapper::COOKIE_NAME);
			if ($passphrase === null) {
				$this->logger->debug('Reading unencrypted session data', [
					'sessionId' => $id,
				]);
				return parent::read($sessionId);
			}
		}

		$encryptedData = parent::read($sessionId);
		if ($encryptedData === '') {
			return '';
		}
		return $this->crypto->decrypt($encryptedData, $passphrase);
	}

	/**
	 * Encrypt and write session data
	 *
	 * @param string $id
	 * @param string $data
	 *
	 * @return bool
	 */
	public function write(string $id, string $data): bool {
		[$sessionId, $passphrase] = self::parseId($id);

		if ($passphrase === null) {
			$passphrase = $this->request->getCookie(CryptoWrapper::COOKIE_NAME);
			if ($passphrase === null) {
				$this->logger->warning('Can not write session because there is no passphrase', [
					'sessionId' => $id,
					'dataLength' => strlen($data),
				]);
				return false;
			}
		}

		$encryptedData = $this->crypto->encrypt($data, $passphrase);

		return parent::write($sessionId, $encryptedData);
	}

	public function close(): bool {
		return parent::close();
	}

	/**
	 * @param string $id
	 *
	 * @return array{0: string, 1: ?string}
	 */
	public static function parseId(string $id): array {
		$parts = explode('|', $id, 2);
		return [$parts[0], $parts[1] ?? null];
	}

}
