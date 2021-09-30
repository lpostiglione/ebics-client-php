<?php

namespace AndrewSvirin\Ebics\Exceptions;

/**
 * AccountAuthorisationFailedException used for 091302 EBICS error
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author  Guillaume Sainthillier
 */
class AccountAuthorisationFailedException extends EbicsResponseException
{

	public function __construct($responseMessage = null)
	{
		parent::__construct(
			'091302',
			$responseMessage,
			'Preliminary verification of the account authorization has failed.'
		);
	}
}
