<?php

/**
 * Klasa wyjątku dla platnosci.pl
 *
 * @copyright	Copyright (c) 2011, Autentika Sp. z o.o.
 * @license		New BSD License
 * @author		Daniel Kózka
 */
class Platnosci_Exception extends Exception
{
	/**
	 * Tablica na błędy
	 *
	 * @var array
	 */
	private $aErrors = array();

	/**
	 * Tablica na błędne parametry
	 *
	 * @var array
	 */
	private $aParams = array();

	/**
	 * Identyfikator płatności której dotycza błędy
	 *
	 * @var	string
	 */
	private $sSessionId;

	/**
	 * Tworzy nowy wyjątek
	 *
	 * @param	string	$sSessionId	identyfikator płatności
	 * @param 	mixed 	$mError		string | array - typ błędu
	 * @param 	mixed 	$mParams	string | array - błędny parametr
	 */
	public function __construct($sSessioId = '', $mError = '', $mParams = '')
	{
		parent::__construct();

		$this->sSessionId = $sSessioId;

		if(is_array($mError))
		{
			$this->aErrors = $mError;
			$this->aParams = $mParams;
		}
		else
		{
			$this->aErrors[] = $mError;
			$this->aParams[$mError] = $mParams;
		}
	}

	/**
	 * Zwraca tablice z typem błędów
	 *
	 * @return 	array
	 */
	public function getErrors()
	{
		return $this->aErrors;
	}

	/**
	 * Zwraca parametr, który spowodował błąd
	 *
	 * @param 	int		$iError		kod błędu
	 * @return 	string
	 */
	public function getParam($iError)
	{
		return $this->aParams[$iError];
	}

	/**
	 * Zwraca identyfikator transakcji
	 *
	 * @return	string
	 */
	public function getSessionId()
	{
		return $this->sSessionId;
	}

	/**
	 * Sprawdza czy podany kod błędu był przekazany przez ten wyjatek
	 *
	 * @param	int 	$iError		kod błędu
	 * @return	bool
	 */
	public function isError($iError)
	{
		return in_array($iError, $this->aErrors);
	}
}
