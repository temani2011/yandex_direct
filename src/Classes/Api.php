<?php

namespace EO\YandexDirect\Classes;

use Exception;
use EOCache;

class Api
{
    private $token        = '';
    private $clientId     = '';
    private $clientSecret = '';
    private $clientLogin  = '';

    public function setApiCredentials($shopGroupId = 1)
    {
        if ($shopGroupId == 3) {
            $this->clientId     = '';
            $this->clientSecret = '';
            $this->clientLogin  = '';
            $this->token        = '';
        }
    }

    public function makeCurlRequest(string $url, array $data): array
    {
        $headers = [
            "Client-Login: {$this->clientLogin}",
            "Authorization: Bearer {$this->token}",
            'Content-Type: application/json',
        ];

        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => json_encode($data),
            ]);

            $response = json_decode(curl_exec($curl), true);
            curl_close($curl);
        } catch (\Exception $e) {
            throw new Exception("While curl request: {$e->getMessage()}");
        }

        if (!$this->isValidResponse($response)) {
            return [];
        }

        return $response;
    }

    public function isValidResponse(array $response): bool
    {
        // if (!$response) {
        //     throw new Exception("Empty response");
        //     return false;
        // }

        if ($response['error'] ?? false) {
            throw new Exception($this->buildErrorMessage($response['error']));
            return false;
        }

        if ($response['result'] ?? false) {
            $errors = [];
            foreach (end($response['result']) ?: [] as $item) {
                if ($item['Errors'] ?? false) {
                    $error = end($item['Errors']);
                    $errors[] = $this->buildErrorMessage($error);
                }
            }

            if ($errors) {
                throw new Exception(implode(';\n', $errors));
                return false;
            }
        }

        return true;
    }


    /**
     * buildErrorMessage
     *
     * @param array
     * @return string
     */
    private function buildErrorMessage($errorArray): string
    {
        $errorMessage  = [];

        foreach ($errorArray as $key => $value) {
            if ($key !== 'Details') {
                continue;
            }

            $errorMessage[] = "{$key}: {$value}";
        }

        return implode(', ', $errorMessage);
    }

    /**
     * Получение токена
     *
     * @return string|null
     */
    protected function getAuthToken(): ?string
    {
        $response = [];

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://oauth.yandex.ru/authorize?response_type=token&client_id=' . $this->clientId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'GET',
            ]);

            $response = json_decode(curl_exec($curl), true);
            curl_close($curl);
        } catch (\Exception $e) {
            throw new Exception("While curl request: {$e->getMessage()}");
        }

        if (!$response) {
            $this->token = $response;
            EOCache::getInstance()->set('token-yandex-direct', $this->token, 3600);
        }

        return $response;
    }
}
