<?php

/*
 * Przykładowy skrypt odbierający komunikaty od płatności.pl przy użyciu
 * wcześniej przygotowanej klasy Platnosci_Example
 */

// czy przesłano odpowiednie dane
if(	isset($_POST['pos_id']) && 
	isset($_POST['session_id']) && 
	isset($_POST['ts']) && 
	isset($_POST['sig']))
{
	// dołączenie niezbędnych plików kodem klas

	try
	{
		// utworzenie obiektu i przekazanie danych niezbędnych do weryfikacji komunikatów
		$oPay = new Platnosci_Example(
				'tutaj_numer_punktu_platnosci',
				'klucz_auth_key',
				'klucz_1_(haszyk)',
				'klucz_2_(haszyk)'
			);
		$oPay->checkStatusChangeRequest($_POST);

	}
	// nie wypuszczamy żadnego błędu, powinniśmy je wszystkie logować wewnątrz
	catch(Exception $oExp){}

	// odpowiadamy do platnosci.pl zgodnie ze specyfikacją
	echo 'OK';
}
// a jeśli brak odpowiednich parametrów - rzucamy 404
else
{
	header("HTTP/1.0 404 Not Found");
	exit();
}
