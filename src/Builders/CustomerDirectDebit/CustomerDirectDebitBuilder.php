<?php

namespace AndrewSvirin\Ebics\Builders\CustomerDirectDebit;

use AndrewSvirin\Ebics\Handlers\Traits\XPathTrait;
use AndrewSvirin\Ebics\Models\CustomerDirectDebit;
use AndrewSvirin\Ebics\Services\DOMHelper;
use AndrewSvirin\Ebics\Services\RandomService;
use DateTime;

/**
 * Class CustomerDirectDebitBuilder builder for model @see \AndrewSvirin\Ebics\Models\CustomerDirectDebit
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author  Andrew Svirin
 */
class CustomerDirectDebitBuilder
{

	use XPathTrait;

	/**
	 * @var RandomService
	 */
	private $randomService;

	/**
	 * @var CustomerDirectDebit|null
	 */
	private $instance;

	public function __construct()
	{
		$this->randomService = new RandomService();
	}

	public function createInstance(
		string $creditorFinInstBIC,
		string $creditorIBAN,
		string $creditorName,
		string $creditorId
	): CustomerDirectDebitBuilder {
		$this->instance = new CustomerDirectDebit();
		$now = new DateTime();

		$xmDocument = $this->instance->createElementNS(
			'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02',
			'Document'
		);
		$xmDocument->setAttributeNS(
			'http://www.w3.org/2000/xmlns/',
			'xmlns:xsi',
			'http://www.w3.org/2001/XMLSchema-instance'
		);
		$xmDocument->setAttributeNS(
			'http://www.w3.org/2001/XMLSchema-instance',
			'xsi:schemaLocation',
			'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02.xsdpain.008.001.02'
		);
		$this->instance->appendChild($xmDocument);

		$xmlCstmrDrctDbtInitn = $this->instance->createElement('CstmrDrctDbtInitn');
		$xmDocument->appendChild($xmlCstmrDrctDbtInitn);

		$xmlGrpHdr = $this->instance->createElement('GrpHdr');
		$xmlCstmrDrctDbtInitn->appendChild($xmlGrpHdr);

		$xmlMsgId = $this->instance->createElement('MsgId');
		$xmlMsgId->nodeValue = $this->randomService->uniqueIdWithDate('msg');
		$xmlGrpHdr->appendChild($xmlMsgId);

		$xmlMsgId = $this->instance->createElement('CreDtTm');
		$xmlMsgId->nodeValue = $now->format('Y-m-d\TH:i:s\.vP');
		$xmlGrpHdr->appendChild($xmlMsgId);

		$xmlNbOfTxs = $this->instance->createElement('NbOfTxs');
		$xmlNbOfTxs->nodeValue = '0';
		$xmlGrpHdr->appendChild($xmlNbOfTxs);

		$xmlCtrlSum = $this->instance->createElement('CtrlSum');
		$xmlCtrlSum->nodeValue = '0';
		$xmlGrpHdr->appendChild($xmlCtrlSum);

		$xmlInitgPty = $this->instance->createElement('InitgPty');
		$xmlGrpHdr->appendChild($xmlInitgPty);

		$xmlNm = $this->instance->createElement('Nm');
		$xmlNm->nodeValue = $creditorName;
		$xmlInitgPty->appendChild($xmlNm);

		$xmlPmtInf = $this->instance->createElement('PmtInf');
		$xmlCstmrDrctDbtInitn->appendChild($xmlPmtInf);

		$xmlPmtInfId = $this->instance->createElement('PmtInfId');
		$xmlPmtInfId->nodeValue = $this->randomService->uniqueIdWithDate('pmt');
		$xmlPmtInf->appendChild($xmlPmtInfId);

		$xmlPmtMtd = $this->instance->createElement('PmtMtd');
		$xmlPmtMtd->nodeValue = 'DD';
		$xmlPmtInf->appendChild($xmlPmtMtd);

		$xmlNbOfTxs = $this->instance->createElement('NbOfTxs');
		$xmlNbOfTxs->nodeValue = '0';
		$xmlPmtInf->appendChild($xmlNbOfTxs);

		$xmlCtrlSum = $this->instance->createElement('CtrlSum');
		$xmlCtrlSum->nodeValue = '0';
		$xmlPmtInf->appendChild($xmlCtrlSum);

		$xmlPmtTpInf = $this->instance->createElement('PmtTpInf');
		$xmlPmtInf->appendChild($xmlPmtTpInf);

		$xmlSvcLvl = $this->instance->createElement('SvcLvl');
		$xmlPmtTpInf->appendChild($xmlSvcLvl);

		$xmlCd = $this->instance->createElement('Cd');
		$xmlCd->nodeValue = 'SEPA';
		$xmlSvcLvl->appendChild($xmlCd);

		$xmlLclInstrm = $this->instance->createElement('LclInstrm');
		$xmlPmtTpInf->appendChild($xmlLclInstrm);

		$xmlCd = $this->instance->createElement('Cd');
		$xmlCd->nodeValue = 'CORE';
		$xmlLclInstrm->appendChild($xmlCd);

		$xmlSeqTp = $this->instance->createElement('SeqTp');
		$xmlSeqTp->nodeValue = 'FRST';
		$xmlPmtTpInf->appendChild($xmlSeqTp);

		$xmlReqdColltnDt = $this->instance->createElement('ReqdColltnDt');
		$xmlReqdColltnDt->nodeValue = $now->format('Y-m-d');
		$xmlPmtInf->appendChild($xmlReqdColltnDt);

		$xmlCdtr = $this->instance->createElement('Cdtr');
		$xmlPmtInf->appendChild($xmlCdtr);

		$xmlNm = $this->instance->createElement('Nm');
		$xmlNm->nodeValue = $creditorName;
		$xmlCdtr->appendChild($xmlNm);

		$xmlCdtrAcct = $this->instance->createElement('CdtrAcct');
		$xmlPmtInf->appendChild($xmlCdtrAcct);

		$xmlId = $this->instance->createElement('Id');
		$xmlCdtrAcct->appendChild($xmlId);

		$xmlIBAN = $this->instance->createElement('IBAN');
		$xmlIBAN->nodeValue = $creditorIBAN;
		$xmlId->appendChild($xmlIBAN);

		$xmlCdtrAgt = $this->instance->createElement('CdtrAgt');
		$xmlPmtInf->appendChild($xmlCdtrAgt);

		$xmlFinInstnId = $this->instance->createElement('FinInstnId');
		$xmlCdtrAgt->appendChild($xmlFinInstnId);

		$xmlCdtrSchmeId = $this->instance->createElement('CdtrSchmeId');
		$xmlPmtInf->appendChild($xmlCdtrSchmeId);

		$xmlCdtrSchmeIdId = $this->instance->createElement('Id');
		$xmlCdtrSchmeId->appendChild($xmlCdtrSchmeIdId);

		$xmlPrvtId = $this->instance->createElement('PrvtId');
		$xmlCdtrSchmeIdId->appendChild($xmlPrvtId);

		$xmlCdtrSchmeIdOthr = $this->instance->createElement('Othr');
		$xmlPrvtId->appendChild($xmlCdtrSchmeIdOthr);

		$xmlCdtrSchmeIdIdId = $this->instance->createElement('Id');
		$xmlCdtrSchmeIdOthr->nodeValue = $creditorId;
		$xmlCdtrSchmeIdOthr->appendChild($xmlCdtrSchmeIdIdId);

		$xmlBIC = $this->instance->createElement('BIC');
		$xmlBIC->nodeValue = $creditorFinInstBIC;
		$xmlFinInstnId->appendChild($xmlBIC);

		$xmlChrgBr = $this->instance->createElement('ChrgBr');
		$xmlChrgBr->nodeValue = 'SLEV';
		$xmlPmtInf->appendChild($xmlChrgBr);

		return $this;
	}

	public function addTransaction(
		string $debitorFinInstBIC,
		string $debitorIBAN,
		string $debitorName,
		float $amount,
		string $currency,
		string $purpose
	): CustomerDirectDebitBuilder {
		$xpath = $this->prepareXPath($this->instance);
		$nbOfTxsList = $xpath->query('//CstmrDrctDbtInitn/PmtInf/NbOfTxs');
		$nbOfTxs = (int)DOMHelper::safeItemValue($nbOfTxsList);
		$nbOfTxs++;

		$pmtInfList = $xpath->query('//CstmrDrctDbtInitn/PmtInf');
		$xmlPmtInf = DOMHelper::safeItem($pmtInfList);

		$reqdColltnDtList = $xpath->query('//CstmrDrctDbtInitn/PmtInf/ReqdColltnDt');
		$reqdColltnDt = DOMHelper::safeItemValue($reqdColltnDtList);

		$xmlDrctDbtTxInf = $this->instance->createElement('DrctDbtTxInf');
		$xmlPmtInf->appendChild($xmlDrctDbtTxInf);

		$xmlPmtId = $this->instance->createElement('PmtId');
		$xmlDrctDbtTxInf->appendChild($xmlPmtId);

		$xmlInstrId = $this->instance->createElement('InstrId');
		$xmlInstrId->nodeValue = $this->randomService->uniqueIdWithDate(
			'pii' . str_pad((string)$nbOfTxs, 2, '0')
		);
		$xmlPmtId->appendChild($xmlInstrId);

		$xmlEndToEndId = $this->instance->createElement('EndToEndId');
		$xmlEndToEndId->nodeValue = $this->randomService->uniqueIdWithDate(
			'pete' . str_pad((string)$nbOfTxs, 2, '0')
		);
		$xmlPmtId->appendChild($xmlEndToEndId);

		$xmlInstdAmt = $this->instance->createElement('InstdAmt');
		$xmlInstdAmt->setAttribute('Ccy', $currency);
		$xmlInstdAmt->nodeValue = number_format($amount, 2, '.', '');
		$xmlDrctDbtTxInf->appendChild($xmlInstdAmt);

		$xmlDrctDbtTx = $this->instance->createElement('DrctDbtTx');
		$xmlDrctDbtTxInf->appendChild($xmlDrctDbtTx);

		$xmlMndtRltdInf = $this->instance->createElement('MndtRltdInf');
		$xmlDrctDbtTx->appendChild($xmlMndtRltdInf);

		$xmlMndtId = $this->instance->createElement('MndtId');
		$xmlMndtId->nodeValue = '1';
		$xmlMndtRltdInf->appendChild($xmlMndtId);

		$xmlDtOfSgntr = $this->instance->createElement('DtOfSgntr');
		$xmlDtOfSgntr->nodeValue = $reqdColltnDt;
		$xmlMndtRltdInf->appendChild($xmlDtOfSgntr);

		$xmlDbtrAgt = $this->instance->createElement('DbtrAgt');
		$xmlDrctDbtTxInf->appendChild($xmlDbtrAgt);

		$xmlFinInstnId = $this->instance->createElement('FinInstnId');
		$xmlDbtrAgt->appendChild($xmlFinInstnId);

		$xmlBIC = $this->instance->createElement('BIC');
		$xmlBIC->nodeValue = $debitorFinInstBIC;
		$xmlFinInstnId->appendChild($xmlBIC);

		$xmlDbtr = $this->instance->createElement('Dbtr');
		$xmlDrctDbtTxInf->appendChild($xmlDbtr);

		$xmlNm = $this->instance->createElement('Nm');
		$xmlNm->nodeValue = $debitorName;
		$xmlDbtr->appendChild($xmlNm);

		$xmlDbtrAcct = $this->instance->createElement('DbtrAcct');
		$xmlDrctDbtTxInf->appendChild($xmlDbtrAcct);

		$xmlId = $this->instance->createElement('Id');
		$xmlDbtrAcct->appendChild($xmlId);

		$xmlIBAN = $this->instance->createElement('IBAN');
		$xmlIBAN->nodeValue = $debitorIBAN;
		$xmlId->appendChild($xmlIBAN);

		$xmlRmtInf = $this->instance->createElement('RmtInf');
		$xmlDrctDbtTxInf->appendChild($xmlRmtInf);

		$xmlUstrd = $this->instance->createElement('Ustrd');
		$xmlUstrd->nodeValue = $purpose;
		$xmlRmtInf->appendChild($xmlUstrd);

		$xmlNbOfTxs = DOMHelper::safeItem($nbOfTxsList);
		$xmlNbOfTxs->nodeValue = (string)$nbOfTxs;

		$nbOfTxsList = $xpath->query('//CstmrDrctDbtInitn/GrpHdr/NbOfTxs');
		$xmlNbOfTxs = DOMHelper::safeItem($nbOfTxsList);
		$xmlNbOfTxs->nodeValue = (string)$nbOfTxs;

		$ctrlSumList = $xpath->query('//CstmrDrctDbtInitn/GrpHdr/CtrlSum');
		$ctrlSum = (float)DOMHelper::safeItemValue($ctrlSumList);
		$xmlCtrlSum = DOMHelper::safeItem($ctrlSumList);
		$xmlCtrlSum->nodeValue = number_format($ctrlSum + $amount, 2, '.', '');

		$ctrlSumList = $xpath->query('//CstmrDrctDbtInitn/PmtInf/CtrlSum');
		$ctrlSum = (float)DOMHelper::safeItemValue($ctrlSumList);
		$xmlCtrlSum = DOMHelper::safeItem($ctrlSumList);
		$xmlCtrlSum->nodeValue = number_format($ctrlSum + $amount, 2, '.', '');

		return $this;
	}

	public function popInstance(): CustomerDirectDebit
	{
		$instance = $this->instance;
		$this->instance = null;

		return $instance;
	}
}
