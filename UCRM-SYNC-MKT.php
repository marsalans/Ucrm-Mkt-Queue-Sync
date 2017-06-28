<?php
/*
Script to sync UCRM services with Mikrotik Queues. Searching in queues in Mikrotik by UCRM Ip Address Associated.
This Script is intended to replace de Mikrotik "queue type" values due i work with pcq queue types, but is easly to modify to just edit queue "max-limit" instead.
Actually V 1.0 designed by me, i'm php noob but this script works fine.
UCRM connect script copied from UBNT-Ondra on https://community.ubnt.com/t5/UCRM-Complete-WISP-Management/UCRM-API-example-script-to-reset-invoice-options-for-all-clients/td-p/1833615

Feel free to edit & improve this script, only one requirement, after improved upload and share it ;)
V1.0 Franco Gampel - Argentina

*/
ini_set('display_errors', 'On');
// Calling Mikrotik Library which must be on same directory than this script
require('routeros_api.class.php');

$API = new routeros_api();

$API->debug = false;

class UcrmApiAccess
{
	// here goes your ucrm url
    const API_URL = 'http://xxx.xxx.xxx.xxx:8080/api/v1.0';
    // Here goes your ApiKey
	const APP_KEY = 'your_api_key_between_this_quotes';

// Declaration of Connect function
    /**
     * @param string $url
     * @param string $method
     * @param array  $post
     *
     * @return array|null
     */
    public static function doRequest($url, $method = 'GET', $post = [])
    {
        $method = strtoupper($method);

        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            sprintf(
                '%s/%s',
                self::API_URL,
                $url
            )
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                sprintf('X-Auth-App-Key: %s', self::APP_KEY),
            ]
        );

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (! empty($post)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
        }

        $response = curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            echo sprintf('Curl error: %s', curl_error($ch)) . PHP_EOL;
        }

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 400) {
            echo sprintf('API error: %s', $response) . PHP_EOL;
            $response = false;
        }

        curl_close($ch);

        return $response !== false ? json_decode($response, true) : null;
    }
}

// Setting unlimited time limit (updating lots of clients can take a long time).
set_time_limit(0);

if (php_sapi_name() !== 'cli') {
    echo '<pre>';
}

// Get collection of all Clients.
$clients = UcrmApiAccess::doRequest('clients') ?: [];
//var_dump($clients);
echo sprintf('Found %d clients.', count($clients)) . PHP_EOL;


//Connect Mkt API
if ($API->connect('192.168.xxx.xxx', 'UCRMAPI', 'xxxxxxx')) {

// Go through all Clients to read Client Services.

foreach ($clients as $client) 
{
	/* echo only for test prupouses
	echo "ID: ";
	echo $client['id'];
	echo "<br/>";
	echo "Cliente: ";
	echo $client['firstName'];
	echo " ";
	echo $client['lastName'];
	echo "<br/>";
	echo "Servicios: ";
	echo "<br/>";
	*/

	$services = UcrmApiAccess::doRequest(
        	sprintf('clients/%d/services', $client['id'])
	);
// Go through all services of actual client
	foreach ($services as $service)
	{
		/*
		echo $service['name'];
		echo "<br/>";
		*/
		//Get service download speed and round it.
		$downloadSpeed=$service['downloadSpeed'];
		$downloadQueue=round($downloadSpeed,3);
		/*
		echo $downloadQueue;
		echo "<br/>";
		*/
		//Get service upload speed and round it.
		$uploadSpeed=$service['uploadSpeed'];
		$uploadQueue=round($uploadSpeed,3);
		/*
		echo $uploadQueue;
		echo "<br/>";
		*/
		//Get service IP, if service ip is just ip with no subnet fo example 192.168.0.1, script adds /32 at end, if you have a range ip in UCRM such as 192.168.0.0/28 the string still untouched
		$ipaddress=$service['ipRanges'][0];
		if (strlen($ipaddress) <= 15)
			{
			$ipaddress .="/32";
			} 
		/*
		echo $ipaddress;
		echo "<br/>";
		*/
		// 
		//Get ID number of Mikrotik "simple queue" with same IP Address than UCRM
		$API->write('/queue/simple/print',false);
		$API->write('?target='.$ipaddress,true);
			$READ = $API->read(false);
			$ARRAY = $API->parse_response($READ);
		$id = $ARRAY[0]['.id'];
		/*
		echo $id;
		echo "<br/>";
		echo "<br/>";
		*/
		//Cambio tipo de Cola
		/*
		Force fixed download/upload speed, commented, just for test prupouses
		$downloadQueue="0.256";
		$uploadQueue="0.256";
		*/
		/*My queue types are named for example: 0.256M-upload / 0.256M-download, so with the downlaod speed i got and rounded before i set in desired queue ID the complete name of queue type.
		Here you can change Mikrotik API commands to edit max-limit, instead of queue types. is up to you and your working methods
		*/
		$API->write('/queue/simple/set',false);
		$API->write('=.id='.$id,false);
		$API->write('=queue='.$uploadQueue.'M-upload/'.$downloadQueue.'M-download',true);
		$READ = $API->read(false);
			$ARRAY = $API->parse_response($READ);
		//   print_r($ARRAY);

	}
	//var_dump($services);

	/*
	Verifing if customer was succesfully updated
	*/
	$response = UcrmApiAccess::doRequest(
        	sprintf('clients/%d', $client['id'],'/services')
	);

	if ($response !== null) {
        	echo sprintf('Client ID %d successfully updated.', $client['id']) . PHP_EOL;
        	//var_dump($response);
    	} else {
        	echo sprintf('There was an error in updating client ID %d.', $client['id']) . PHP_EOL;
    	}
}

//Mkt Disconnect
$API->disconnect();
}
echo 'Done.' . PHP_EOL;