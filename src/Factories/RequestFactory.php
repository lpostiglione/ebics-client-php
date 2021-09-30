<?php

namespace AndrewSvirin\Ebics\Factories;

use AndrewSvirin\Ebics\Builders\Request\BodyBuilder;
use AndrewSvirin\Ebics\Builders\Request\DataEncryptionInfoBuilder;
use AndrewSvirin\Ebics\Builders\Request\DataTransferBuilder;
use AndrewSvirin\Ebics\Builders\Request\HeaderBuilder;
use AndrewSvirin\Ebics\Builders\Request\MutableBuilder;
use AndrewSvirin\Ebics\Builders\Request\OrderDetailsBuilder;
use AndrewSvirin\Ebics\Builders\Request\RequestBuilder;
use AndrewSvirin\Ebics\Builders\Request\StaticBuilder;
use AndrewSvirin\Ebics\Builders\Request\TransferReceiptBuilder;
use AndrewSvirin\Ebics\Builders\Request\XmlBuilder;
use AndrewSvirin\Ebics\Contexts\BTFContext;
use AndrewSvirin\Ebics\Contexts\RequestContext;
use AndrewSvirin\Ebics\Contracts\OrderDataInterface;
use AndrewSvirin\Ebics\Contracts\SignatureInterface;
use AndrewSvirin\Ebics\Exceptions\EbicsException;
use AndrewSvirin\Ebics\Handlers\AuthSignatureHandler;
use AndrewSvirin\Ebics\Handlers\OrderDataHandler;
use AndrewSvirin\Ebics\Handlers\UserSignatureHandler;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\CustomerHIA;
use AndrewSvirin\Ebics\Models\CustomerINI;
use AndrewSvirin\Ebics\Models\Http\Request;
use AndrewSvirin\Ebics\Models\KeyRing;
use AndrewSvirin\Ebics\Models\User;
use AndrewSvirin\Ebics\Models\UserSignature;
use AndrewSvirin\Ebics\Services\CryptService;
use AndrewSvirin\Ebics\Services\DigestResolver;
use DateTimeInterface;

/**
 * Class RequestFactory represents producers for the @see Request.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author  Andrew Svirin
 */
abstract class RequestFactory
{
	/**
	 * @var RequestBuilder
	 */
	protected $requestBuilder;

	/**
	 * @var OrderDataHandler
	 */
	protected $orderDataHandler;

	/**
	 * @var DigestResolver
	 */
	protected $digestResolver;

	/**
	 * @var AuthSignatureHandler
	 */
	protected $authSignatureHandler;

	/**
	 * @var UserSignatureHandler
	 */
	protected $userSignatureHandler;

	/**
	 * @var CryptService
	 */
	protected $cryptService;

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
	 * Constructor.
	 *
	 * @param Bank $bank
	 * @param User $user
	 * @param KeyRing $keyRing
	 */
	public function __construct(Bank $bank, User $user, KeyRing $keyRing)
	{
		$this->requestBuilder = new RequestBuilder();
		$this->cryptService = new CryptService();
		$this->bank = $bank;
		$this->user = $user;
		$this->keyRing = $keyRing;
	}

	abstract protected function createRequestBuilderInstance(): RequestBuilder;

	abstract protected function addOrderType(
		OrderDetailsBuilder $orderDetailsBuilder,
		string $orderType
	): OrderDetailsBuilder;

	public function createHEV(): Request
	{
		$context = (new RequestContext())
			->setBank($this->bank);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerHEV(function (XmlBuilder $builder) use ($context) {
				$builder->addHostId($context->getBank()->getHostId());
			})
			->popInstance();

		return $request;
	}

	public function createINI(SignatureInterface $certificateA, DateTimeInterface $dateTime): Request
	{
		$orderData = new CustomerINI();
		$this->orderDataHandler->handleINI(
			$orderData,
			$certificateA,
			$dateTime
		);

		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setDateTime($dateTime)
			->setOrderData($orderData);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerUnsecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this->addOrderType($orderDetailsBuilder, 'INI');
							})
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable();
				})->addBody(function (BodyBuilder $builder) use ($context) {
					$builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
						$builder->addOrderData($context->getOrderData());
					});
				});
			})
			->popInstance();

		return $request;
	}

	public function createHIA(
		SignatureInterface $certificateE,
		SignatureInterface $certificateX,
		DateTimeInterface $dateTime
	): Request {
		$orderData = new CustomerHIA();
		$this->orderDataHandler->handleHIA(
			$orderData,
			$certificateE,
			$certificateX,
			$dateTime
		);

		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setDateTime($dateTime)
			->setOrderData($orderData);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerUnsecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this->addOrderType($orderDetailsBuilder, 'HIA');
							})
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable();
				})->addBody(function (BodyBuilder $builder) use ($context) {
					$builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
						$builder->addOrderData($context->getOrderData());
					});
				});
			})
			->popInstance();

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createHPB(DateTimeInterface $dateTime): Request
	{
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setDateTime($dateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecuredNoPubKeyDigests(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this->addOrderType($orderDetailsBuilder, 'HPB');
							})
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable();
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createHPD(DateTimeInterface $dateTime): Request
	{
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this
									->addOrderType($orderDetailsBuilder, 'HPD')
									->addStandardOrderParams();
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createHKD(DateTimeInterface $dateTime): Request
	{
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this
									->addOrderType($orderDetailsBuilder, 'HKD')
									->addStandardOrderParams();
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createPTK(DateTimeInterface $dateTime): Request
	{
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this
									->addOrderType($orderDetailsBuilder, 'PTK')
									->addStandardOrderParams();
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createHTD(DateTimeInterface $dateTime): Request
	{
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this
									->addOrderType($orderDetailsBuilder, 'HTD')
									->addStandardOrderParams();
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 * @param string $fileFormat
	 * @param string $countryCode
	 * @param DateTimeInterface|null $startDateTime
	 * @param DateTimeInterface|null $endDateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createFDL(
		DateTimeInterface $dateTime,
		string $fileFormat,
		string $countryCode = 'FR',
		DateTimeInterface $startDateTime = null,
		DateTimeInterface $endDateTime = null
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setFileFormat($fileFormat)
			->setCountryCode($countryCode)
			->setStartDateTime($startDateTime)
			->setEndDateTime($endDateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
								$this
									->addOrderType($orderDetailsBuilder, 'FDL')
									->addOrderAttribute(OrderDetailsBuilder::ORDER_ATTRIBUTE_DZHNN)
									->addFDLOrderParams(
										$context->getFileFormat(),
										$context->getCountryCode(),
										$context->getStartDateTime(),
										$context->getEndDateTime()
									);
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createHAA(DateTimeInterface $dateTime): Request
	{
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this
									->addOrderType($orderDetailsBuilder, 'HAA')
									->addStandardOrderParams();
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 * @param BTFContext $btfContext
	 * @param DateTimeInterface|null $startDateTime
	 * @param DateTimeInterface|null $endDateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createBTD(
		DateTimeInterface $dateTime,
		BTFContext $btfContext,
		DateTimeInterface $startDateTime = null,
		DateTimeInterface $endDateTime = null
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setBTFContext($btfContext)
			->setStartDateTime($startDateTime)
			->setEndDateTime($endDateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
								$this
									->addOrderType($orderDetailsBuilder, 'BTD')
									->addBTDOrderParams(
										$context->getBTFContext(),
										$context->getStartDateTime(),
										$context->getEndDateTime()
									);
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param string $transactionId
	 * @param bool $acknowledged
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createTransferReceipt(string $transactionId, bool $acknowledged): Request
	{
		$context = (new RequestContext())
			->setBank($this->bank)
			->setTransactionId($transactionId)
			->setReceiptCode(
				true === $acknowledged ?
					TransferReceiptBuilder::CODE_RECEIPT_POSITIVE : TransferReceiptBuilder::CODE_RECEIPT_NEGATIVE
			);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addTransactionId($context->getTransactionId());
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_RECEIPT);
					});
				})->addBody(function (BodyBuilder $builder) use ($context) {
					$builder->addTransferReceipt(function (TransferReceiptBuilder $builder) use ($context) {
						$builder->addReceiptCode($context->getReceiptCode());
					});
				});
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param string $transactionId
	 * @param string $transactionKey
	 * @param OrderDataInterface $orderData
	 * @param int $segmentNumber
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createTransferTransfer(
		string $transactionId,
		string $transactionKey,
		OrderDataInterface $orderData,
		int $segmentNumber
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setTransactionId($transactionId)
			->setTransactionKey($transactionKey)
			->setOrderData($orderData)
			->setSegmentNumber($segmentNumber);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addTransactionId($context->getTransactionId());
					})->addMutable(function (MutableBuilder $builder) use ($context) {
						$builder
							->addTransactionPhase(MutableBuilder::PHASE_TRANSFER)
							->addSegmentNumber($context->getSegmentNumber());
					});
				})->addBody(function (BodyBuilder $builder) use ($context) {
					$builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
						$builder->addOrderData($context->getOrderData(), $context->getTransactionKey());
					});
				});
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 * @param DateTimeInterface|null $startDateTime
	 * @param DateTimeInterface|null $endDateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createVMK(
		DateTimeInterface $dateTime,
		DateTimeInterface $startDateTime = null,
		DateTimeInterface $endDateTime = null
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setStartDateTime($startDateTime)
			->setEndDateTime($endDateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
								$this
									->addOrderType($orderDetailsBuilder, 'VMK')
									->addStandardOrderParams($context->getStartDateTime(), $context->getEndDateTime());
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 * @param DateTimeInterface|null $startDateTime
	 * @param DateTimeInterface|null $endDateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createSTA(
		DateTimeInterface $dateTime,
		DateTimeInterface $startDateTime = null,
		DateTimeInterface $endDateTime = null
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setStartDateTime($startDateTime)
			->setEndDateTime($endDateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
								$this
									->addOrderType($orderDetailsBuilder, 'STA')
									->addStandardOrderParams($context->getStartDateTime(), $context->getEndDateTime());
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 * @param DateTimeInterface|null $startDateTime
	 * @param DateTimeInterface|null $endDateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createC52(
		DateTimeInterface $dateTime,
		DateTimeInterface $startDateTime = null,
		DateTimeInterface $endDateTime = null
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setStartDateTime($startDateTime)
			->setEndDateTime($endDateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
								$this
									->addOrderType($orderDetailsBuilder, 'C52')
									->addStandardOrderParams($context->getStartDateTime(), $context->getEndDateTime());
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 * @param DateTimeInterface|null $startDateTime
	 * @param DateTimeInterface|null $endDateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createC53(
		DateTimeInterface $dateTime,
		DateTimeInterface $startDateTime = null,
		DateTimeInterface $endDateTime = null
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setStartDateTime($startDateTime)
			->setEndDateTime($endDateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
								$this
									->addOrderType($orderDetailsBuilder, 'C53')
									->addStandardOrderParams($context->getStartDateTime(), $context->getEndDateTime());
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 * @param DateTimeInterface|null $startDateTime
	 * @param DateTimeInterface|null $endDateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createZ53(
		DateTimeInterface $dateTime,
		DateTimeInterface $startDateTime = null,
		DateTimeInterface $endDateTime = null
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setStartDateTime($startDateTime)
			->setEndDateTime($endDateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
								$this
									->addOrderType($orderDetailsBuilder, 'Z53')
									->addStandardOrderParams($context->getStartDateTime(), $context->getEndDateTime());
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	/**
	 * @param DateTimeInterface $dateTime
	 * @param DateTimeInterface|null $startDateTime
	 * @param DateTimeInterface|null $endDateTime
	 *
	 * @return Request
	 * @throws EbicsException
	 */
	public function createZ54(
		DateTimeInterface $dateTime,
		DateTimeInterface $startDateTime = null,
		DateTimeInterface $endDateTime = null
	): Request {
		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setStartDateTime($startDateTime)
			->setEndDateTime($endDateTime);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
								$this
									->addOrderType($orderDetailsBuilder, 'Z54')
									->addStandardOrderParams($context->getStartDateTime(), $context->getEndDateTime());
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody();
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	public function createCCT(
		DateTimeInterface $dateTime,
		int $numSegments,
		string $transactionKey,
		OrderDataInterface $orderData
	): Request {
		$signatureData = new UserSignature();
		$this->userSignatureHandler->handle($signatureData, $orderData);

		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setTransactionKey($transactionKey)
			->setNumSegments($numSegments)
			->setSignatureData($signatureData);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this
									->addOrderType($orderDetailsBuilder, 'CCT')
									->addStandardOrderParams();
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000)
							->addNumSegments($context->getNumSegments());
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody(function (BodyBuilder $builder) use ($context) {
					$builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
						$builder
							->addDataEncryptionInfo(function (DataEncryptionInfoBuilder $builder) use ($context) {
								$builder
									->addEncryptionPubKeyDigest($context->getKeyRing())
									->addTransactionKey($context->getTransactionKey(), $context->getKeyRing());
							})
							->addSignatureData($context->getSignatureData(), $context->getTransactionKey());
					});
				});
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}

	public function createCDD(
		DateTimeInterface $dateTime,
		int $numSegments,
		string $transactionKey,
		OrderDataInterface $orderData
	): Request {
		$signatureData = new UserSignature();
		$this->userSignatureHandler->handle($signatureData, $orderData);

		$context = (new RequestContext())
			->setBank($this->bank)
			->setUser($this->user)
			->setKeyRing($this->keyRing)
			->setDateTime($dateTime)
			->setTransactionKey($transactionKey)
			->setNumSegments($numSegments)
			->setSignatureData($signatureData);

		$request = $this
			->createRequestBuilderInstance()
			->addContainerSecured(function (XmlBuilder $builder) use ($context) {
				$builder->addHeader(function (HeaderBuilder $builder) use ($context) {
					$builder->addStatic(function (StaticBuilder $builder) use ($context) {
						$builder
							->addHostId($context->getBank()->getHostId())
							->addRandomNonce()
							->addTimestamp($context->getDateTime())
							->addPartnerId($context->getUser()->getPartnerId())
							->addUserId($context->getUser()->getUserId())
							->addProduct('Ebics client PHP', 'de')
							->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
								$this
									->addOrderType($orderDetailsBuilder, 'CDD')
									->addStandardOrderParams();
							})
							->addBankPubKeyDigests(
								$context->getKeyRing()->getBankSignatureXVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureX()),
								$context->getKeyRing()->getBankSignatureEVersion(),
								$this->digestResolver->digest($context->getKeyRing()->getBankSignatureE())
							)
							->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000)
							->addNumSegments($context->getNumSegments());
					})->addMutable(function (MutableBuilder $builder) {
						$builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
					});
				})->addBody(function (BodyBuilder $builder) use ($context) {
					$builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
						$builder
							->addDataEncryptionInfo(function (DataEncryptionInfoBuilder $builder) use ($context) {
								$builder
									->addEncryptionPubKeyDigest($context->getKeyRing())
									->addTransactionKey($context->getTransactionKey(), $context->getKeyRing());
							})
							->addSignatureData($context->getSignatureData(), $context->getTransactionKey());
					});
				});
			})
			->popInstance();

		$this->authSignatureHandler->handle($request);

		return $request;
	}
}
