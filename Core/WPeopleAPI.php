<?php

namespace Bims\Core;

use Google\Client;
use GuzzleHttp\Client as Http;
use Throwable;
use Bims\Core\Instance;
use Bims\Helpers\Arr;

class WPeopleAPI
{

    private $file_token,
        $base_url = false,
        $client = false,
        $message,
        $client_secret,
        $prefix;

    /**
     * Constructor of Bims\WPeopleAPI class.
     */
    public function __construct($client_id = false, $client_secret = false)
    {
        $init = Instance::init();
        $this->prefix           = $init;
        $this->file_token       = 'access_token_' . $init . '.json';
        $this->client_secret    = 'client_secret_' . $init . '.json';

        if ($client_id && $client_secret) {
            $this->storeClientSecret($client_id, $client_secret);
        }

        if (!is_file(dirname(__DIR__) . '/Config/' . $this->client_secret)) {
            $this->message = 'Client secret not found';
            return false;
        }

        $this->setBaseUrl();

        $client = new Client();
        $client->setAccessType('offline');
        $client->setAuthConfig(dirname(__DIR__) . '/Config/' . $this->client_secret);
        $client->addScope('https://www.googleapis.com/auth/contacts');
        $client->setIncludeGrantedScopes(true);
        $client->setRedirectUri($this->base_url);
        $client->setApprovalPrompt('force');
        $this->client =  $client;
    }

    /**
     * Get client instance of Goole\Client
     * 
     * @return Google\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Store contact to google contact using get request
     * @param $name string name
     * @param $phone string phone
     * @param $email string email
     * @param $token string access_token
     * @return GuzzleHttp\Client response
     */
    public function store($name = null, $phone = null, $email = null, $group = null, $address = [], $birth = [], $urls = [], $events = [], $userDefined = [])
    {
        $get_token = $this->getToken();
        $token = $get_token->access_token;
        if (!$token) {
            $this->message = 'Token invalid, please renew token';
            return false;
        }

        $http       = new Http();
        $endpoint   = 'https://people.googleapis.com/v1/people:createContact?personFields=names%2CphoneNumbers%2CemailAddresses%2Caddresses%2Curls%2Cevents%2Cbirthdays%2CuserDefined&sources=READ_SOURCE_TYPE_CONTACT&prettyPrint=true';
        $groupID    = $this->getGroupByName($group);
        $body  = [
            'names' => [
                ['givenName' => $name]
            ],
            'phoneNumbers' => [
                ['value' => $phone]
            ],
            'emailAddresses' => [
                [
                    'displayName' => $name,
                    'value' => $email
                ]
            ],
            'memberships' => [
                [
                    'contactGroupMembership' => [
                        'contactGroupResourceName' => (isset($groupID->resourceName)) ? $groupID->resourceName : 'contactGroups/myContacts'
                    ]
                ]
            ],
            'addresses' => [
                [
                    'city'              => ucwords($address['city'] ?? null),
                    'countryCode'       => strtoupper($address['countryCode'] ?? null),
                    'country'           => strtoupper($address['country'] ?? null),
                    'formattedValue'    => ucwords($address['city'] ?? null) . ' ' . strtoupper($address['countryCode'] ?? $address['country'] ?? null)
                ]
            ],
            'birthdays' => [
                [
                    'date'  => [
                        'year'  => $birth['year'] ?? null,
                        'month' => $birth['month'] ?? null,
                        'day'   => $birth['day'] ?? null
                    ]
                ]
            ],
            'urls'  => [
                (is_array($urls)) ? $urls : null
            ],
            'events' => [
                (is_array($events)) ? $events : null
            ],
            'userDefined' => [
                (is_array($userDefined)) ? $userDefined : null
            ],

        ];

        try {
            $response = $http->request('POST', $endpoint, [
                'body'      => json_encode($body),
                'headers'   => [
                    'content-type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json'
                ]
            ]);

            return json_decode($response->getBody()->getContents());
        } catch (Throwable $e) {
            $this->message = $e->getMessage();
            return $this->message;
        }
    }

    /**
     * Store access token to json file with auth code
     * 
     * @param $code string of auth code
     */
    public function storeToken($code)
    {
        $client = $this->client;
        $client->fetchAccessTokenWithAuthCode($code);
        $token  = $client->getAccessToken();
        $json   = json_encode($token, JSON_PRETTY_PRINT);
        file_put_contents(dirname(__DIR__) . '/Config/' . $this->file_token, $json);
        return header('Location: ' . $this->base_url);
    }

    /**
     * Set file token name
     * 
     * @param $filename string of named file
     * @return Bims\WPeopleAPI $file_token
     */
    public function setFileToken($filename)
    {
        return $this->file_token = $filename;
    }

    /**
     * Set base_url to redirect url
     * 
     * @param $url string url
     * @return Bims\WPeopleAPI $base_url
     */
    public function setBaseUrl($url = false)
    {
        if (!$url) {
            $the_base  = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
            $the_base .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
            $the_base .= 'options-general.php?page=wpeopleapi-setting';
            return $this->base_url = $the_base;
        }
        return $this->base_url = $url;
    }

    /**
     * Get base url 
     * 
     * @return Bims\WPeopleAPI $base_url
     */
    public function getBaseUrl()
    {
        return $this->base_url;
    }

    /**
     * Get access token
     * 
     * @return json access token
     */
    public function getToken()
    {
        if (!is_file(dirname(__DIR__) . '/Config/' . $this->file_token)) {
            $this->message = 'File token not found';
            return false;
        }
        $file_data  = file_get_contents(dirname(__DIR__) . '/Config/' . $this->file_token);
        $json = json_decode($file_data);
        return (isset($json->access_token)) ? $json : false;
    }

    /**
     * Get message of error message or success
     * 
     * @return Bims\WPeopleAPI $message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get client secret file
     * 
     * @return Bims\WPeopleAPI $client_secret
     */
    public function getClientSecret()
    {
        return $this->client_secret;
    }

    /**
     * Get information of access token
     * 
     * @param $access_token string of access token
     * 
     * @return json token information
     */
    public function tokenInfo($access_token =  false)
    {
        if (!$access_token) {
            $access_token = $this->getToken()->access_token;
        }

        $http = new Http();
        try {
            $response = $http->request('GET', 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . $access_token);
            if ($response->getStatusCode() != '200') {
                return false;
            }

            return json_decode($response->getBody());
        } catch (Throwable $e) {
            $this->message = $e->getMessage();
            return false;
        }
    }

    /**
     * Store access token to json file, using to store refresh token
     * 
     * @param $token string of access token
     * 
     * @return bool
     */
    public function storeJsonToken($token)
    {
        $json = json_encode($token, JSON_PRETTY_PRINT);
        try {
            file_put_contents(dirname(__DIR__) . '/Config/' . $this->file_token, $json);
        } catch (Throwable $e) {
            $this->message = $e->getMessage();
            return false;
        }
    }

    /**
     * Show lists contact in google contact
     * 
     * @param $access_token string of access token
     * @return GuzzleHttp\Client response
     */
    public function lists($access_token=false)
    {
        if (!$access_token) {
            $access_token = $this->getToken()->access_token;
        }

        $http = new Http();
        try {
            $response = $http->request('GET', 'https://people.googleapis.com/v1/people/me/connections?personFields=names%2CphoneNumbers%2CemailAddresses%2Cphotos', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json'
                ]
            ]);
            return json_decode($response->getBody()->getContents());
        } catch (Throwable $e) {
            $this->message = $e->getMessage();
            return false;
        }
    }

    /**
     * Show specific contact in google contact
     * 
     * @param $access_token string of access token
     * @param string $resourceName | e.g people/[string $resourceName]
     * @return GuzzleHttp\Client response
     */
    public function list($resourceName, $access_token=false)
    {
        if (!$access_token) {
            $access_token = $this->getToken()->access_token;
        }

        $http = new Http();
        try {
            $response = $http->request('GET', 'https://people.googleapis.com/v1/'. $resourceName .'?personFields=names%2CphoneNumbers%2CemailAddresses%2Cphotos', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json'
                ]
            ]);
            return json_decode($response->getBody()->getContents());
        } catch (Throwable $e) {
            $this->message = $e->getMessage();
            return false;
        }
    }

    /**
     * Store client_id and client_secret to json file
     * 
     * @param $client_id string of client_id
     * @param $client_secret string of client_secret
     * 
     * @return redirect to base_url
     */
    public function storeClientSecret($client_id = false, $client_secret = false)
    {

        if (!$client_id && !$client_secret) {
            $this->message = 'Provide an client id and client secret';
            return false;
        }

        $data = [
            "web" => [
                "client_id"     => $client_id,
                "client_secret" => $client_secret
            ]
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);
        try {
            file_put_contents(dirname(__DIR__) . '/Config/' . $this->client_secret, $json);
            return header('location:' . $this->base_url);
        } catch (Throwable $e) {
            $this->message = $e->getMessage();
            return false;
        }
    }

    /**
     * Remove authorization, unlink json file of access_token and client_secret and set null prefix of Filename.php
     * 
     * @return redirect to base_url
     */
    public function removeAuthorization()
    {
        $the_token      = dirname(__DIR__) . '/Config/' . $this->file_token;
        $the_client     = dirname(__DIR__) . '/Config/' . $this->client_secret;
        $the_filename   = dirname(__FILE__) . '/' . 'Filename.php';

        if (is_file($the_token) && is_file($the_client)) {
            try {
                unlink($the_token);
                unlink($the_client);

                // read filename
                $file = file_get_contents($the_filename);
                $file = str_replace('$prefix = "' . $this->prefix . '"', '$prefix = NULL', $file);
                file_put_contents($the_filename, $file);

                return header('location:' . $this->base_url);
            } catch (Throwable $e) {
                $this->message = $e->getMessage();
                return false;
            }
        }
    }

    /**
     * Update profile picture
     * 
     * @param string $resourceName | e.g people/[string $resourceName]
     * @param base64 encoded $photoBytes
     */
    public function updateContactPhoto($resourceName, $photoBytes, $access_token=false)
    {
        if (!$access_token) {
            $access_token = $this->getToken()->access_token;
        }

        $http = new Http();
        try{
            $response = $http->request('PATCH', 'https://people.googleapis.com/v1/' .$resourceName. ':updateContactPhoto', [
                'body' => json_encode([
                    'photoBytes' => $photoBytes
                ]),
                'headers' => [
                    'content-type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json'
                ]
            ]);
            return json_decode($response->getBody()->getContents());
        }catch(Throwable $e) {
            $this->message = $e->getMessage();
            return false;
        }
    }

    /**
     * check is client_secret file valid
     * 
     * @return bool is_file()
     */
    public function isValidFile()
    {
        return is_file(dirname(__DIR__) . '/Config/' . $this->getClientSecret());
    }

    /**
     * get group(contactGroups) by name
     * 
     * @param $name string of name
     * @return Bims\Helpers\Arr
     */
    public function getGroupByName($name, $access_token = false)
    {
        if (!$access_token) {
            $access_token = $this->getToken()->access_token;
        }

        $http = new Http();
        try {
            $response = $http->request('GET', 'https://people.googleapis.com/v1/contactGroups', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json'
                ]
            ]);

            $array  = json_decode($response->getBody()->getContents(), TRUE);
            $filter = Arr::filter($array, $name)::toObject()::result();

            if (is_object($filter)) {
                return $filter;
            } else{
                return $this->createGroup($name);
            }

        } catch (Throwable $e) {
            $this->message = $e->getMessage();
            return false;
        }

    }

    /**
     * Create Group
     * @param $name string
     */

     public function createGroup($name, $access_token=false)
     {
        if (!$access_token) {
            $access_token = $this->getToken()->access_token;
        }

        $http = new Http();
        try{
            $response = $http->request('POST', 'https://people.googleapis.com/v1/contactGroups', [
                'body' => json_encode([
                    'contactGroup' => [
                        'name' => $name
                    ]
                ]),
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json',
                    'content-type'  => 'application/json',
                ]
            ]);

            return json_decode($response->getBody()->getContents());

        } catch (Throwable $e) {
            $this->message = $e->getMessage();
            return false;
        }
     }
}
