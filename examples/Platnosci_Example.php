<?php

/**
 * Przykładowa klasa implementująca Platnosci_Abstract
 * Do obsługi bazy danych i logowania użyto pseudokodu
 */
class Platnosci_Example extends Platnosci_Abstract
{
	// dodatkowy kod błędu 
	const BAD_CASH_AMOUNT = 101;

	/**
	 * Tablica tłumacząca kody błędów na komunikaty
	 *
	 * @var array
	 */
	protected $aMessages = array(
		self::BAD_DETAILS_POS_ID		=> 'Otrzymano dane z błędnym pos_id: ',
		self::BAD_DETAILS_SESSION_ID	=> 'Otrzymano dane z błędnym session_id: ',
		self::BAD_DETAILS_SIG			=> 'Błędny podpis dokumentu z detalami transakcji',
		self::BAD_DETAILS_STATUS		=> 'Status przesłanych danych inny niż OK: ',
		self::BAD_INFO_POS_ID			=> 'Otrzymano komunikat z błędnym pos_id: ',
		self::BAD_INFO_SIG				=> 'Błędny podpis komunikatu',
		self::BAD_INFO_SESSION_ID		=> 'Info o transakcji nieistniejącej w bazie: ',
		self::BAD_CASH_AMOUNT			=> 'Rozbieżność między kwotami: '
	);

	/**
	 * (non-PHPdoc)
	 * @see Platnosci_Abstract::isSessionIdExist()
	 */
	protected function isSessionIdExist($sSessionId)
	{
		// sprawdzenie, czy transakcja istnieje w bazie danych
		return Payments::exists($sSessionId);
	}

	/**
	 * (non-PHPdoc)
	 * @see Platnosci_Abstract::onStatusChange()
	 */
	protected function onStatusChange(array $aData)
	{
		try
		{
			// pobranie płatności z bazy danych
			$aPayment = Payments::get($aData['session_id']); 

			// sprawdzamy na wszelki wypadek, czy kwoty zgadzają się
			if($aPayment['amount'] != $aData['amount']) 
			{
				// nie zgadzają się
				// dodajemy info o błędzie, a jako parametr dodatkowy
				// podajemy kwotę, która się nie zgadzała
				$this->error(self::BAD_CASH_AMOUNT, $aData['amount']);

				// wyrzucenie wyjątku (wcześniej zostanie wywołany onError)
				$this->throwException($aData['session_id']);
			}

			if($aData['status'] == 99) // jeśli płatność została odebrana
			{
				// obsługa zakończonej transakcji
				// UWAGA: MOŻE PRZYJŚĆ KILKA KOMUNIKATÓW Z PLATNOSCI.PL ZE ZMIANĄ STANU NA 99
			}

			// aktualizacja statusu transakcji
			$aPayment['status'] = $aData['status'];
			Payments::set($aPayment);
		}
		// z ewentualnymi wyjątkami nie robimy nic. Zalogujemy je w onError
		catch(Core_Platnosci_Exception $e) {}
	}

	/**
	 * (non-PHPdoc)
	 * @see Platnosci_Abstract::onError()
	 */
	protected function onError($sSessionId, $aErrors, $aErrorsParams)
	{
		// logowanie błędów
		$oLog = new Log();

		// wpisywanie błędów do loga
		foreach($aErrors as $iErrorCode)
		{
			// komunikad błędu i parametry dodatkowe
			$oLog->add(
				$this->aMessages[$iError] . $aErrorsParams[$iError]
			);
		}
	}
}
