<?php
/**
 * Created by PhpStorm.
 * User: zygimantaszilevicius
 * Date: 31/01/2018
 * Time: 15:57
 */

namespace App\Ethereum;

use App\Contract;
use Graze\GuzzleHttp\JsonRpc\Client;
use phpseclib\Math\BigInteger;

class Ethereum
{
    protected $client;
    protected $id = 0;

    protected $abi;
    protected $contractAddress;
    protected $defaultAccount;

    public $defaultBlock = self::DEFAULT_BLOCK_LATEST;

    CONST DEFAULT_BLOCK_EARLIEST = "earliest";
    CONST DEFAULT_BLOCK_LATEST = "latest";
    CONST DEFAULT_BLOCK_PENDING = "pending";


    /**
     * Ethereum constructor.
     * @param string $url
     * @param string|array $cAbi
     * @param string $cAddress
     * @param string $ownersAddress
     */
    public function __construct($url, $cAbi, $cAddress, $ownersAddress)
    {
        $this->client = Client::factory($url, ["rpc_error" => true]);
        $this->abi = $cAbi;
        $this->contractAddress = $cAddress;
        $this->defaultAccount = $ownersAddress;
    }

    /**
     * @param string $method
     * @param null|array $params
     * @return mixed
     */
    public function request($method, $params = null)
    {
        $this->id++;
        $response = $this->client->send($this->client->request($this->id, $method, $params));
        $array = \Graze\GuzzleHttp\JsonRpc\json_decode($response->getBody(), true);
        return $array["result"];
    }

    /**
     * @param string $address
     * @param string $passPhrase
     * @param int $duration
     * @return mixed
     */
    public function unlockAccount($address, $passPhrase, $duration = 0)
    {
        return $this->request("personal_unlockAccount", [$address, $passPhrase, $duration]);
    }

    /**
     * @param array $object
     * @return string
     */
    public function sendTransaction(array $object)
    {
        $object["data"] = isset($object["data"]) ? $object["data"] : "0x";
        $object["to"] = isset($object["to"]) ? $object["to"] : Contract::WHITELISTER_ADDRESS;
        $object["from"] = isset($object["from"]) ? $object["from"] : $this->defaultAccount;

        if (isset($object["value"])) {
            $object["value"] = $this->toHex($object["value"]);
        }
        if (isset($object["gas"])) {
            $object["gas"] = $this->toHex($object["gas"]);
        }
        if (isset($object["gasPrice"])) {
            $object["gasPrice"] = $this->toHex($object["gasPrice"]);
        }
        if (isset($object["nonce"])) {
            $object["nonce"] = $this->toHex($object["nonce"]);
        }

        return $this->request("eth_sendTransaction", [$object]);
    }

    /**
     * @param array $object
     * @param null|string $defaultBlock
     * @return string
     */
    public function call(array $object, $defaultBlock = null)
    {
        $object["data"] = isset($object["data"]) ? $object["data"] : $this->toHex(0);
        if (isset($object["value"])) {
            $object["value"] = $this->toHex($object["value"]);
        }
        if (isset($object["gas"])) {
            $object["gas"] = $this->toHex($object["gas"]);
        }
        if (isset($object["gasPrice"])) {
            $object["gasPrice"] = $this->toHex($object["gasPrice"]);
        }
        if (isset($object["nonce"])) {
            $object["nonce"] = $this->toHex($object["nonce"]);
        }
        if (!$defaultBlock) {
            $defaultBlock = $this->defaultBlock;
        }

        return $this->request("eth_call", [$object, $defaultBlock]);
    }

    /**
     * @param string|integer|BigInteger $value
     * @return string
     */
    public function toHex($value)
    {
        if ($value instanceof BigInteger) {
            return "0x" . $value->toHex();
        }
        if (substr($value, 0, 2) == "0x") {
            return $value;
        }
        //return "0x" . dechex($value);
        return "0x" . (new BigInteger($value))->toHex();
    }

    /**
     * @param $string
     * @return mixed
     */
    public function sha3($string)
    {
        return $this->request("web3_sha3", ["0x".$this->_string2Hex($string)]);
    }

    /**
     * @param $string
     * @return string
     */
    protected function _string2Hex($string)
    {
        $hex='';
        for ($i=0; $i < strlen($string); $i++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }

    /**
     * @param $name
     * @param $arguments
     * @return null|string
     */
    public function contract_at_call($name, $arguments)
    {
        foreach ($this->abi as $code){
            if (isset($code["constant"]) && isset($code["name"]) && $code["name"] == $name) {
                $payload = [];
                if(count($arguments) > count($code["inputs"])){
                    $payload = $arguments[count($code["inputs"])];
                }
                $payload["to"] = $this->contractAddress;
                $payload["data"] = '0x' . $this->_signature($code["name"], $code["inputs"])
                    . $this->_encodeParams($arguments, $code["inputs"]);
                if ($code["constant"]) {
                    return $this->call($payload);
                } else {
                    return $this->sendTransaction($payload);
                }
            }
        }

        return null;
    }

    /**
     * @param $name
     * @param array $inputs
     * @return string
     */
    protected function _signature($name, array $inputs)
    {
        $fullName = $name;
        if($inputs){
            $fullName .= "(";
            for ($c=0; $c<count($inputs); $c++) {
                $fullName .= $inputs[$c]["type"];
                if(count($inputs) - 1 != $c){
                    $fullName .= ",";
                }
            }
            $fullName .= ")";
        }

        return substr($this->sha3($fullName), 2, 8);
    }

    /**
     * @param array $params
     * @param array $inputs
     * @return string
     */
    protected function _encodeParams(array $params, array $inputs)
    {
        $result = "";
        for ($c=0; $c<count($params); $c++) {
            $param = $params[$c];
            if (isset($inputs[$c])) {
                $input = $inputs[$c];
                if (isset($input["type"])) {
                    switch ($input["type"]) {
                        case "address":
                            $result .= str_pad(substr($param, 2), 64, "0", STR_PAD_LEFT);
                            break;

                        case "uint256":
                            $result .= str_pad(substr($this->toHex($param), 2), 64, "0", STR_PAD_LEFT);
                            break;

                        case "uint8":
                            $result .= str_pad(substr($this->toHex($param), 2), 64, "0", STR_PAD_LEFT);
                            break;
                    }
                }
            }
        }

        return $result;
    }
}