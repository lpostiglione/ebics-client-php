<?php

namespace AndrewSvirin\Ebics\Handlers;

use AndrewSvirin\Ebics\Contracts\SignatureInterface;
use AndrewSvirin\Ebics\Exceptions\EbicsException;
use AndrewSvirin\Ebics\Factories\CertificateX509Factory;
use AndrewSvirin\Ebics\Factories\SignatureFactory;
use AndrewSvirin\Ebics\Handlers\Traits\XPathTrait;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\CustomerHIA;
use AndrewSvirin\Ebics\Models\CustomerINI;
use AndrewSvirin\Ebics\Models\KeyRing;
use AndrewSvirin\Ebics\Models\OrderData;
use AndrewSvirin\Ebics\Models\User;
use AndrewSvirin\Ebics\Services\CryptService;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Class OrderDataHandler manages OrderData DOM elements.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author  Andrew Svirin
 *
 * @internal
 */
abstract class OrderDataHandler
{
	use XPathTrait;

	/**
	 * @var Bank
	 */
	private $bank;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @var KeyRing
	 */
	private $keyRing;

	/**
	 * @var CryptService
	 */
	protected $cryptService;

	/**
	 * @var SignatureFactory
	 */
	protected $certificateFactory;

	/**
	 * @var CertificateX509Factory
	 */
	private $certificateX509Factory;

	public function __construct(Bank $bank, User $user, KeyRing $keyRing)
	{
		$this->bank = $bank;
		$this->user = $user;
		$this->keyRing = $keyRing;
		$this->cryptService = new CryptService();
		$this->certificateFactory = new SignatureFactory();
		$this->certificateX509Factory = new CertificateX509Factory();
	}

	abstract protected function createSignaturePubKeyOrderData(CustomerINI $xml): DOMElement;

	/**
	 * @return void
	 */
	abstract protected function handleINISignaturePubKey(
		DOMElement $xmlSignaturePubKeyInfo,
		CustomerINI $xml,
		SignatureInterface $certificateA,
		DateTimeInterface $dateTime
	);

	/**
	 * Adds OrderData DOM elements to XML DOM for INI request.
	 *
	 * @param CustomerINI $xml
	 * @param SignatureInterface $certificateA
	 * @param DateTimeInterface $dateTime
	 *
	 * @throws EbicsException
	 */
	public function handleINI(CustomerINI $xml, SignatureInterface $certificateA, DateTimeInterface $dateTime): void
	{
		// Add SignaturePubKeyOrderData to root.
		$xmlSignaturePubKeyOrderData = $this->createSignaturePubKeyOrderData($xml);
		$xmlSignaturePubKeyOrderData->setAttributeNS(
			'http://www.w3.org/2000/xmlns/',
			'xmlns:ds',
			'http://www.w3.org/2000/09/xmldsig#'
		);
		$xml->appendChild($xmlSignaturePubKeyOrderData);

		// Add SignaturePubKeyInfo to SignaturePubKeyOrderData.
		$xmlSignaturePubKeyInfo = $xml->createElement('SignaturePubKeyInfo');
		$xmlSignaturePubKeyOrderData->appendChild($xmlSignaturePubKeyInfo);

		if ($this->bank->isCertified()) {
			$this->handleX509Data($xmlSignaturePubKeyInfo, $xml, $certificateA);
		}

		$this->handleINISignaturePubKey($xmlSignaturePubKeyInfo, $xml, $certificateA, $dateTime);

		// Add SignatureVersion to SignaturePubKeyInfo.
		$xmlSignatureVersion = $xml->createElement('SignatureVersion');
		$xmlSignatureVersion->nodeValue = $this->keyRing->getUserSignatureAVersion();
		$xmlSignaturePubKeyInfo->appendChild($xmlSignatureVersion);

		// Add PartnerID to SignaturePubKeyOrderData.
		$this->handlePartnerId($xmlSignaturePubKeyOrderData, $xml);

		// Add UserID to SignaturePubKeyOrderData.
		$this->handleUserId($xmlSignaturePubKeyOrderData, $xml);
	}

	abstract protected function createHIARequestOrderData(CustomerHIA $xml): DOMElement;

	/**
	 * @return void
	 */
	abstract protected function handleHIAAuthenticationPubKey(
		DOMElement $xmlAuthenticationPubKeyInfo,
		CustomerHIA $xml,
		SignatureInterface $certificateX,
		DateTimeInterface $dateTime
	);

	/**
	 * @return void
	 */
	abstract protected function handleHIAEncryptionPubKey(
		DOMElement $xmlEncryptionPubKeyInfo,
		CustomerHIA $xml,
		SignatureInterface $certificateE,
		DateTimeInterface $dateTime
	);

	/**
	 * Adds OrderData DOM elements to XML DOM for HIA request.
	 *
	 * @param CustomerHIA $xml
	 * @param SignatureInterface $certificateE
	 * @param SignatureInterface $certificateX
	 * @param DateTimeInterface $dateTime
	 *
	 * @throws EbicsException
	 */
	public function handleHIA(
		CustomerHIA $xml,
		SignatureInterface $certificateE,
		SignatureInterface $certificateX,
		DateTimeInterface $dateTime
	): void {
		// Add HIARequestOrderData to root.
		$xmlHIARequestOrderData = $this->createHIARequestOrderData($xml);
		$xmlHIARequestOrderData->setAttributeNS(
			'http://www.w3.org/2000/xmlns/',
			'xmlns:ds',
			'http://www.w3.org/2000/09/xmldsig#'
		);

		$xml->appendChild($xmlHIARequestOrderData);

		// Add AuthenticationPubKeyInfo to HIARequestOrderData.
		$xmlAuthenticationPubKeyInfo = $xml->createElement('AuthenticationPubKeyInfo');
		$xmlHIARequestOrderData->appendChild($xmlAuthenticationPubKeyInfo);

		if ($this->bank->isCertified()) {
			$this->handleX509Data($xmlAuthenticationPubKeyInfo, $xml, $certificateX);
		}

		$this->handleHIAAuthenticationPubKey($xmlAuthenticationPubKeyInfo, $xml, $certificateX, $dateTime);

		// Add AuthenticationVersion to AuthenticationPubKeyInfo.
		$xmlAuthenticationVersion = $xml->createElement('AuthenticationVersion');
		$xmlAuthenticationVersion->nodeValue = $this->keyRing->getUserSignatureXVersion();
		$xmlAuthenticationPubKeyInfo->appendChild($xmlAuthenticationVersion);

		// Add EncryptionPubKeyInfo to HIARequestOrderData.
		$xmlEncryptionPubKeyInfo = $xml->createElement('EncryptionPubKeyInfo');
		$xmlHIARequestOrderData->appendChild($xmlEncryptionPubKeyInfo);

		if ($this->bank->isCertified()) {
			$this->handleX509Data($xmlEncryptionPubKeyInfo, $xml, $certificateE);
		}

		$this->handleHIAEncryptionPubKey($xmlEncryptionPubKeyInfo, $xml, $certificateE, $dateTime);

		// Add EncryptionVersion to EncryptionPubKeyInfo.
		$xmlEncryptionVersion = $xml->createElement('EncryptionVersion');
		$xmlEncryptionVersion->nodeValue = $this->keyRing->getUserSignatureEVersion();
		$xmlEncryptionPubKeyInfo->appendChild($xmlEncryptionVersion);

		// Add PartnerID to HIARequestOrderData.
		$this->handlePartnerId($xmlHIARequestOrderData, $xml);

		// Add UserID to HIARequestOrderData.
		$this->handleUserId($xmlHIARequestOrderData, $xml);
	}

	/**
	 * Add ds:X509Data to PublicKeyInfo XML Node.
	 *
	 * @param DOMNode $xmlPublicKeyInfo
	 * @param DOMDocument $xml
	 * @param SignatureInterface $certificate
	 *
	 * @throws EbicsException
	 */
	private function handleX509Data(DOMNode $xmlPublicKeyInfo, DOMDocument $xml, SignatureInterface $certificate): void
	{
		if (!($certificateContent = $certificate->getCertificateContent())) {
			throw new EbicsException('Certificate X509 is empty.');
		}
		$certificateX509 = $this->certificateX509Factory->createFromContent($certificateContent);

		// Add ds:X509Data to Signature.
		$xmlX509Data = $xml->createElement('ds:X509Data');
		$xmlPublicKeyInfo->appendChild($xmlX509Data);

		// Add ds:X509IssuerSerial to ds:X509Data.
		$xmlX509IssuerSerial = $xml->createElement('ds:X509IssuerSerial');
		$xmlX509Data->appendChild($xmlX509IssuerSerial);

		// Add ds:X509IssuerName to ds:X509IssuerSerial.
		$xmlX509IssuerName = $xml->createElement('ds:X509IssuerName');
		$xmlX509IssuerName->nodeValue = $certificateX509->getInsurerName();
		$xmlX509IssuerSerial->appendChild($xmlX509IssuerName);

		// Add ds:X509SerialNumber to ds:X509IssuerSerial.
		$xmlX509SerialNumber = $xml->createElement('ds:X509SerialNumber');
		$xmlX509SerialNumber->nodeValue = $certificateX509->getSerialNumber();
		$xmlX509IssuerSerial->appendChild($xmlX509SerialNumber);

		// Add ds:X509Certificate to ds:X509Data.
		$xmlX509Certificate = $xml->createElement('ds:X509Certificate');
		$certificateContent = $certificate->getCertificateContent();
		$certificateContent = trim(
			str_replace(
				['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"],
				'',
				$certificateContent
			)
		);
		$xmlX509Certificate->nodeValue = $certificateContent;
		$xmlX509Data->appendChild($xmlX509Certificate);
	}

	/**
	 * Add PartnerID to OrderData XML Node.
	 *
	 * @param DOMNode $xmlOrderData
	 * @param DOMDocument $xml
	 */
	private function handlePartnerId(DOMNode $xmlOrderData, DOMDocument $xml): void
	{
		$xmlPartnerID = $xml->createElement('PartnerID');
		$xmlPartnerID->nodeValue = $this->user->getPartnerId();
		$xmlOrderData->appendChild($xmlPartnerID);
	}

	/**
	 * Add UserID to OrderData XML Node.
	 *
	 * @param DOMNode $xmlOrderData
	 * @param DOMDocument $xml
	 */
	private function handleUserId(DOMNode $xmlOrderData, DOMDocument $xml): void
	{
		$xmlUserID = $xml->createElement('UserID');
		$xmlUserID->nodeValue = $this->user->getUserId();
		$xmlOrderData->appendChild($xmlUserID);
	}

	/**
	 * Extract Authentication Certificate from the $orderData.
	 *
	 * @param OrderData $orderData
	 *
	 * @return SignatureInterface
	 */
	abstract public function retrieveAuthenticationSignature(OrderData $orderData): SignatureInterface;

	/**
	 * Extract Encryption Certificate from the $orderData.
	 *
	 * @param OrderData $orderData
	 *
	 * @return SignatureInterface
	 */
	abstract public function retrieveEncryptionSignature(OrderData $orderData): SignatureInterface;
}
