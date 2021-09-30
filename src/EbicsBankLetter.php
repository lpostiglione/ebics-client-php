<?php

namespace AndrewSvirin\Ebics;

use AndrewSvirin\Ebics\Contracts\BankLetter\FormatterInterface;
use AndrewSvirin\Ebics\Factories\BankLetterFactory;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\BankLetter;
use AndrewSvirin\Ebics\Models\KeyRing;
use AndrewSvirin\Ebics\Models\User;
use AndrewSvirin\Ebics\Services\BankLetter\HashGenerator\CertificateHashGenerator;
use AndrewSvirin\Ebics\Services\BankLetter\HashGenerator\PublicKeyHashGenerator;
use AndrewSvirin\Ebics\Services\BankLetterService;
use AndrewSvirin\Ebics\Services\DigestResolverV2;
use AndrewSvirin\Ebics\Services\DigestResolverV3;
use LogicException;

/**
 * EBICS bank letter prepare.
 * Initialization letter details.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author  Andrew Svirin
 */
final class EbicsBankLetter
{
	/**
	 * @var BankLetterService
	 */
	private $bankLetterService;

	/**
	 * @var BankLetterFactory
	 */
	private $bankLetterFactory;

	public function __construct()
	{
		$this->bankLetterService = new BankLetterService();
		$this->bankLetterFactory = new BankLetterFactory();
	}

	/**
	 * Prepare variables for bank letter.
	 * On this moment should be called INI and HEA.
	 *
	 * @param Bank $bank
	 * @param User $user
	 * @param KeyRing $keyRing
	 *
	 * @return BankLetter
	 */
	public function prepareBankLetter(Bank $bank, User $user, KeyRing $keyRing): BankLetter
	{
		if ($bank->isCertified()) {
			if (Bank::VERSION_25 === $bank->getVersion()) {
				$digestResolver = new DigestResolverV2();
			} elseif (Bank::VERSION_30 === $bank->getVersion()) {
				$digestResolver = new DigestResolverV3();
			} else {
				throw new LogicException(sprintf('Version "%s" is not implemented', $bank->getVersion()));
			}
			$hashGenerator = new CertificateHashGenerator($digestResolver);
		} else {
			$hashGenerator = new PublicKeyHashGenerator();
		}

		$bankLetter = $this->bankLetterFactory->create(
			$bank,
			$user,
			$this->bankLetterService->formatSignatureForBankLetter(
				$keyRing->getUserSignatureA(),
				$keyRing->getUserSignatureAVersion(),
				$hashGenerator
			),
			$this->bankLetterService->formatSignatureForBankLetter(
				$keyRing->getUserSignatureE(),
				$keyRing->getUserSignatureEVersion(),
				$hashGenerator
			),
			$this->bankLetterService->formatSignatureForBankLetter(
				$keyRing->getUserSignatureX(),
				$keyRing->getUserSignatureXVersion(),
				$hashGenerator
			)
		);

		return $bankLetter;
	}

	/**
	 * Format bank letter.
	 *
	 * @param BankLetter $bankLetter
	 * @param FormatterInterface $formatter
	 *
	 * @return mixed
	 */
	public function formatBankLetter(BankLetter $bankLetter, FormatterInterface $formatter)
	{
		return $formatter->format($bankLetter);
	}
}
