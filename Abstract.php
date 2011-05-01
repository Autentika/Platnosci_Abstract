<?php

/**
 * Abstrakcyjna klasa do obsługi komunikatów z platnosci.pl
 *
 * @copyright	Copyright (c) 2011, Autentika Sp. z o.o.
 * @license		New BSD License
 * @author		Daniel Kózka
 */
abstract class Platnosci_Abstract
{
	// stałe opisuące typy błędów
	const BAD_DETAILS_POS_ID = 0;
	const BAD_DETAILS_SESSION_ID = 1;
	const BAD_DETAILS_SIG = 2;
	const BAD_DETAILS_STATUS = 3;
	const BAD_INFO_POS_ID = 4;
	const BAD_INFO_SESSION_ID = 5;
	const BAD_INFO_SIG = 6;

	// adresy płatności.pl
	const PAYMENT_GET_ADDRESS = 'https://www.platnosci.pl/paygw/UTF/Payment/get';

	/**
	 * Identyfikator Posa
	 *
	 * @var int
	 */
	private $iPosId;

	/**
	 * Klucz do weryfikacji
	 *
	 * @var string
	 */
	private $sAuthKey;

	/**
	 * Klucz do tworzenia podpisów wysyłanych
	 *
	 * @var string
	 */
	private $sKey1;

	/**
	 * Klucz do sprawdzania podpisów otrzymanych
	 *
	 * @var string
	 */
	private $sKey2;

	/**
	 * Tablica na błędy
	 *
	 * @var array
	 */
	private $aErrors = array();

	/**
	 * Tablica błędnych parametrów
	 *
	 * @var array
	 */
	private $aErrorsParams = array();


	/**
	 * Metoda uruchamiana po poprawnej validacji komunikatu inicjującego
	 *
	 * @param	string	$sSessionId		weryfikowany session_id
	 * @return 	bool
	 */
	protected abstract function isSessionIdExist($sSessionId);

	/**
	 * Metoda reagująca na zmiane statusu transakcji
	 *
	 * @param 	array $aData	tablica z detalami transakcji
	 * @return	void
	 */
	protected abstract function onStatusChange(array $aData);

	/**
	 * Metoda reagująca na wystapienie błędu
	 *
	 * @param	string 	$sSessionId		identyfikator płatności
	 * @param	array	$aErrors		tablica z błędami
	 * @param	array	$aErrorsParams	tablica z błędnymi parametrami
	 * @return	void
	 */
	protected abstract function onError($sSessionId, $aErrors, $aErrorsParams);


	/**
	 * Konstruktor
	 *
	 * @param 	int		$iPosId		identyfikator Posa
	 * @param 	string	$sAuthKey	klucz do weryfikacji
	 * @param 	string	$sKey1		klucz do podpisów wysyłanych
	 * @param 	string	$sKey2		klucz do podpisów odbieranych
	 */
	public function __construct($iPosId, $sAuthKey, $sKey1, $sKey2)
	{
		$this->iPosId = $iPosId;
		$this->sAuthKey = $sAuthKey;
		$this->sKey1 = $sKey1;
		$this->sKey2 = $sKey2;
	}

	/**
	 * Rozpoczyna proces aktualizacji statusu transakcji
	 *
	 * @throws 	Platnosci_Exception
	 * @param 	array 	$aParams	tablica z komunikatem
	 * @return	void
	 */
	public function checkStatusChangeRequest(array $aParams)
	{
		$this->verifyInput($aParams); // weryfikacja poprawności otrzymanego komunikatu
		$this->checkStatus($aParams['session_id']); // odpytanie platnosci.pl o status transakcji
	}

	/**
	 * Sprawdza status transakcji (wywołuje metodę onStatusChange)
	 *
	 * @throws 	Platnosci_Exception
	 * @param 	string	$sSessionId		identyfikator transakcji
	 * @return	void
	 */
	public function checkStatus($sSessionId)
	{
		$sXML = $this->sendStatusRequest($sSessionId);	// wysłanie requesta z pytaniem o status
		$aData = $this->extractFromXml($sXML); 			// zamiana otrzymanego dokumentu XML na tablicę
		$this->verifyRequest($aData, $sSessionId);		// weryfikacja poprawności otrzymanego dokumentu
		$this->onStatusChange($aData['trans']);			// wywołanie metody reagującej na zmianę statusu
	}

	/**
	 * Funkcja dodaje bład do tablicy błędów
	 *
	 * @param 	int 	$iError		kod błędu
	 * @param	string	$sParam		opcjonalnie - wartośc błędnego parametru
	 * @return	void
	 */
	protected function error($iError, $sParam = '')
	{
		$this->aErrors[] = $iError;
		$this->aErrorsParams[$iError] = $sParam;
	}

	/**
	 * Uruchamian funkcję onErrors która domyślie rzuca wyjątkiem z błędami
	 *
	 * @throws	Platnosci_Exception
	 * @param	string	$sSessionId		identyfikator transakcji
	 * @return 	void
	 */
	protected function throwException($sSessionId)
	{
		if(!empty($this->aErrors))
		{
			$this->onError($sSessionId, $this->aErrors, $this->aErrorsParams);
			// rzuca wyjątkiem
			throw new Platnosci_Exception($sSessionId, $this->aErrors, $this->aErrorsParams);
		}
	}

	/**
	 * Wypakowuje dane z dokumentu XML do tablicy
	 *
	 * @param	string 	$sXml	dokument XML z detalami transakcji
	 * @return	array
	 */
	private function extractFromXml($sXml)
	{
		$oXml = new DOMDocument();
		$oXml->loadXML($sXml);
		$oXpath = new DOMXPath($oXml);

		// wyciąga podstawowe dane
	  	$aResult = array(
	  		'status' => $oXpath->query('/response/status')->item(0)->nodeValue,
	  		'trans' => array()
	  	);

	  	// wyciąga wszystkie dane dotyczace transakcji
	  	$aNodelist =  $oXpath->query('/response/trans/*');
		foreach($aNodelist as $oNode)
		{
			$aResult['trans'][$oNode->nodeName] = $oNode->nodeValue;
		}

		unset($oXml);

		return $aResult;
	}

	/**
	 * Funkcja wysyłająca zapytanie o detale transakcji do płatności.pl
	 * Zwraca dokumenet XML z opisem transakcji
	 *
	 * @param 	string	$sSessionId		identyfikator sesji
	 * @return	string
	 */
	private function sendStatusRequest($sSessionId)
	{
		$iTs = $_SERVER['REQUEST_TIME'];
		$sSig = md5($this->iPosId . $sSessionId . $iTs . $this->sKey1);

		$rCurl = curl_init(self::PAYMENT_GET_ADDRESS);

		curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($rCurl, CURLOPT_HEADER, 0);
		curl_setopt($rCurl, CURLOPT_POST, 1);
		curl_setopt($rCurl, CURLOPT_POSTFIELDS,
			'pos_id=' . $this->iPosId .
			'&session_id='. $sSessionId .
			'&ts=' . $iTs .
			'&sig=' . $sSig
		);

		$sResult = curl_exec($rCurl);
		curl_close($rCurl);

		return $sResult;
	}

	/**
	 * Weryfikuje poprawność otrzymanego komunikatu
	 *
	 * @throws 	Platnosci_Exception
	 * @param 	array	$aParams	tablica z paramterami komunikatu
	 * @return 	void
	 */
	private function verifyInput($aParams)
	{
		$sSig = md5(
			$aParams['pos_id'] .
			$aParams['session_id'] .
			$aParams['ts'] .
			$this->sKey2
		);

		if($sSig != $aParams['sig']) // czy poprawny podpis komunikatu
		{
			$this->error(self::BAD_INFO_SIG);
		}

		if($aParams['pos_id'] != $this->iPosId) // czy poprawny numer Posa
		{
			$this->error(self::BAD_INFO_POS_ID, $aParams['pos_id']);
		}

		// wywołanie metody abstrakcyjnej sprawdzającej istnienie transakcji w bazie sklepu
		if(!$this->isSessionIdExist($aParams['session_id']))
		{
			$this->error(self::BAD_INFO_SESSION_ID, $aParams['session_id']);
		};

		$this->throwException($aParams['session_id']);
	}

	/**
	 * Weryfikuje poprawnosc detali otrzymanych z platnosci.pl
	 *
	 * @throws 	Platnosci_Exception
	 * @param 	array	$aData			tablica z detalami transakcji
	 * @param	string	$sSessionId		identyfikator transakcji
	 * @return	void
	 */
	private function verifyRequest($aData, $sSessionId)
	{
		$aTrans = $aData['trans'];

		$sSig = md5(	// wyliczenie podpisu
			$aTrans['pos_id'] . $aTrans['session_id'] . $aTrans['order_id'] .
			$aTrans['status'] . $aTrans['amount'] . $aTrans['desc'] .
			$aTrans['ts'] . $this->sKey2
		);

		if($aData['status'] != 'OK')	// czy status dokumentu jest poprawny
		{
			$this->error(self::BAD_DETAILS_STATUS, $aData['status']);
		}

		if($sSig != $aTrans['sig'])		// czy podpis jest poprawny
		{
			$this->error(self::BAD_DETAILS_SIG);
		}

		if($aTrans['pos_id'] != $this->iPosId)	// czy zgadza się numer pos_id
		{
			$this->error(self::BAD_DETAILS_POS_ID, $aTrans['pos_id']);
		}

		if($aTrans['session_id'] != $sSessionId)	// czy zgadza się session_id
		{
			$this->error(self::BAD_DETAILS_SESSION_ID, $aTrans['session_id']);
		}

		$this->throwException($sSessionId);
	}
}
