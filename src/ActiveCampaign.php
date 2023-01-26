<?php 

namespace BlackSpot\ActiveCampaign;

use Exception;

class ActiveCampaign
{
    public static $apiUrl     = null;
    public static $apiToken   = null;
    public static $apiVersion = 3;
    public static $dictionary = [];

    public $lastUrl;
    private $constructorApiToken = null;

    public function __construct($apiToken = null)
    {
        if ($apiToken !== null) {
            $this->constructorApiToken = $apiToken;       
        }else{
            if (static::$apiToken == null || static::$apiToken == '') {
                throw new Exception("ActiveCampaign connection requires an apiToken", 1);
            }
        }

        if (static::$apiUrl == null || static::$apiUrl == '') {
            throw new Exception("ActiveCampaign connection requires an apiUrl", 1);            
        }
        if (static::$apiVersion == null || static::$apiVersion == '') {
            throw new Exception("ActiveCampaign connection requires an apiVersion", 1);            
        }
    }

    public static function setCredentials($apiUrl, $apiToken = null) 
    {
        static::$apiUrl   = $apiUrl;
        static::$apiToken = $apiToken;
    }

    /**
     * Allow define a dictionary of fields
     * 
     * Thats is usefully for replace the id field with a custom label like : FirstName => 1 or LastName => 2, etc
     * 
     * [
     *    'fields' => [ 'Label' => $id ],
     *    'tags'   => [ 'Label' => $id ],
     *    'lists'  => [ 'Label' => $id ]
     * ]
     * 
     * @param string $dictionary
     * @param array $values
     */
    public static function setDictionary($dictionary, array $values)
    {
        static::$dictionary[$dictionary] = $values;
    }

    /**
     * Get values of dictionary
     * 
     * @param string $dictionary
     * @param mixed $value
     */
    public static function dictionaryOf($dictionary, $value = null)
    {
        $values = static::$dictionary[$dictionary];

        if ($value) {
            return $values[$value];
        }

        return $values;
    }

    /**
     * Create a new instance of active campaign
     * 
     * @param string|null $apiToken
     */
    public static function connect($apiToken = null)
    {
        return new self($apiToken);
    }

    private function responseIsSuccess($response)
    {
        $statusCode = $response->getStatus();
        return $statusCode == 200 || $statusCode == 201;
    }

    private function responseFails($response)
    {        
        return $response->getStatus() >= 400;
    }
    
    private function responseArray($response)
    {
        return json_decode($response->getBody()->getContents(), true);
    }

    public function throwIfEnabled($response, $returnsBoolean = false)
    {
        if ($response == false) {
            if ($returnsBoolean) {
                return false;
            }else{
                throw new Exception('[Active Campaign] : Something was wrong', 1);
            }
        }else if ($this->responseFails($response)) {
            if ($returnsBoolean) {
                return false;
            }else{
                throw new Exception('[Active Campaign] : Something was wrong', 1);
            }
        }else{
            return true;
        }
    }    

    public function getApiToken()
    {
        if ($this->constructorApiToken != null) {
            return $this->constructorApiToken;
        }

        return static::$apiToken;
    }

    private function apiRequestor($method, $url, $data = [], $returnsBoolean = false)
    {        
        $guzzleHttp = new \GuzzleHttp\Client([
            'base_uri' => static::$apiUrl,
            'headers'  => [
                'Api-Token' => $this->getApiToken(),
                'accept'    => 'application/json',
            ]
        ]);

        $url           = '/api/'.static::$apiVersion.$url;
        $this->lastUrl = ['method' => $method, 'url' => $url]; 
        $data          = $this->buildData($method, $data);
        $response      = $guzzleHttp->{$method}($url, $data);

        if ($this->throwIfEnabled($response, $returnsBoolean) == false) {
            return false;
        }
         
        if ($returnsBoolean) {
            return $this->responseIsSuccess($response);
        }
    
        return $this->responseArray($response);
    }

    private function buildData($method, $data = [])
    {
        if ($method == 'get' && !empty($data)) {
            $data = ['query' => $data];
        }elseif ($method == 'post') {                    
            $data = ['body' => json_encode($data)];
        }
        
        return $data;
    }

    /**
     * Writing methods
     */
    public function createContact($data, $returnsBoolean = true)
    {
        return $this->apiRequestor('post', '/contacts', ['contact' => $data], $returnsBoolean);
    }

    public function createOrUpdateContact($data, $returnsBoolean = true)
    {
        return $this->apiRequestor('post', '/contact/sync', ['contact' => $data], $returnsBoolean);
    }

    public function firstOrCreateContact($emailToSearch, $newData, $returnsBoolean = true)
    {
        $result = $this->getContactsBy(['email' => $emailToSearch]);

        if ($result['contacts'] != []) {
            if ($returnsBoolean) {
                return true;
            }else{
                return ['contact' => $result['contacts'][0]];
            }
        }else{            
            return $this->createContact($newData, $returnsBoolean);
        }
    }

    /**
     * Updating methods
     */    
    public function updateContact($contactId, $data, $returnsBoolean = true)
    {
        return $this->apiRequestor('put', "/contacts/{$contactId}", ['contact' => $data], $returnsBoolean);
    }

    /**
     * Relating methods
     */
    public function addTagToContact($data, $returnsBoolean = true)
    {
        return $this->apiRequestor('post', '/contactTags', [
            'contactTag' => $data
        ], $returnsBoolean);
    }

    public function addContactToList($data, $returnsBoolean = true)
    {
        return $this->apiRequestor('post', '/contactLists', [
            'contactList' => $data
        ], $returnsBoolean);
    }

    /**
     * Fetching methods
     */
    public function getAllFields($query = [])
    {
        return $this->apiRequestor('get', '/fields', $query);
    }

    public function getAllCustomFields($query = [])
    {
        return $this->apiRequestor('get', '/fieldValues', $query);
    }

    public function getAllTags($query = [])
    {
        return $this->apiRequestor('get', '/tags', $query);
    }

    public function getAllLists($query = [])
    {
        return $this->apiRequestor('get', '/lists', $query);
    }
    
    public function getContactFieldValues($contactId, $query = [])
    {
        return $this->apiRequestor('get', "/contacts/{$contactId}/fieldValues", $query);
    }

    public function getContact($contactId)
    {
        return $this->apiRequestor('get', "/contacts/{$contactId}");
    }
    
    public function getContactsBy($query = [])
    {
        return $this->apiRequestor('get', '/contacts', $query);
    }   


}