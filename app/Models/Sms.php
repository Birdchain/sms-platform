<?php

namespace App\Models;

use GuzzleHttp\Client;

class Sms
{
    const AUTH_TOKEN = 'B88shQSjJqMmFe0h8aWMAhHvJBjvht6S6sPxS3X2';
    const SENDER_ID = 'birdchain';

    /**
     * @param string $number
     * @param string $text
     * @return bool
     */
    public static function send($number, $text)
    {
        $data = [
            'to' => str_replace('+', '', $number),
            'from' => self::SENDER_ID,
            'message' => $text
        ];

        $client = new Client();
        try {
            $result = $client->post('https://api.vertexsms.com/sms', [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-VertexSMS-Token' => self::AUTH_TOKEN
                ]
            ]);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

}
