<?php

namespace Saulmoralespa\WompiPa;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Utils;

class Client
{
    const API_BASE_URL = "https://api.wompi.pa/";
    const SANDBOX_API_BASE_URL = "https://api-sandbox.wompi.pa/";
    const API_VERSION = "v1";
    protected static bool $sandbox = false;

    public function __construct(
        private $keyPrivate,
        private $keyPublic,
        private $keyIntegrety
    ) {
    }

    public function sandbox(): self
    {
        self::$sandbox = true;
        return $this;
    }

    public static function getBaseURL(): string
    {
        return self::$sandbox ? self::SANDBOX_API_BASE_URL : self::API_BASE_URL;
    }
    public function client(): GuzzleClient
    {
        return new GuzzleClient([
            "base_uri" => self::getBaseURL() . self::API_VERSION . "/",
        ]);
    }

    /**
     * @throws \Exception|GuzzleException
     */
    public function cardToken(array $data): array
    {
        return $this->makeRequest("POST", "tokens/cards", [
            "json" => $data
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function nequiToken(array $data): array
    {
        return $this->makeRequest("POST", "tokens/nequi", [
            "json" => $data
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function getStatusSubscriptionNequi(string $token): array
    {
        return $this->makeRequest("GET", "tokens/nequi/$token");
    }

    /**
     * @throws GuzzleException
     */
    public function getAcceptanceTokens(): array
    {
        return $this->makeRequest("GET", "merchants/$this->keyPublic");
    }

    /**
     * @throws GuzzleException
     */
    public function createSource(array $data): array
    {
        return $this->makeRequest("POST", "payment_sources", [
            "headers" => [
                "Authorization" => "Bearer " . $this->keyPrivate,
            ],
            "json" => $data
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function cancelSubscription(int $sourceId): array
    {
        return $this->makeRequest("PUT", "payment_sources/$sourceId/void", [
            "headers" => [
                "Authorization" => "Bearer " . $this->keyPrivate,
            ]
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function transaction(array $data): array
    {
        $data["signature"] = $this->getSignature($data);

        return $this->makeRequest("POST", "transactions", [
            "headers" => [
                "Authorization" => "Bearer " . $this->keyPrivate,
            ],
            "json" => $data
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->makeRequest("GET", "transactions/$transactionId");
    }

    /**
     * @throws GuzzleException
     */
    public function createPaymentLink(array $data): array
    {
        return $this->makeRequest("POST", "payment_links", [
            "headers" => [
                "Authorization" => "Bearer " . $this->keyPrivate,
            ],
            "json" => $data
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function getPaymentLink(string $id): array
    {
        return $this->makeRequest("GET", "payment_links/$id");
    }

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    private function makeRequest(string $method, string $uri, array $newOptions = []): array
    {
        try {
            $options = [
                "headers" => [
                    "Authorization" => "Bearer " . $this->keyPublic,
                    "Content-Type" => "application/json"
                ]
            ];

            $options = [
                ...$options, ...$newOptions
            ];

            $res = $this->client()->request($method, $uri, $options);
            $content =  $res->getBody()->getContents();
            return self::responseArray($content);
        } catch (RequestException $exception) {
            $content = $exception->getResponse()->getBody()->getContents();
            $response = self::responseArray($content);
            $errorMessage = $this->handleErrors($response) ?? $exception->getMessage();
            throw new \Exception($errorMessage);
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    public static function responseArray(string $content): array
    {
        return Utils::jsonDecode($content, true);
    }

    public function handleErrors(array $response): ?string
    {
        if ((array_key_exists('error', $response) &&
                is_array($response['error']))
        ) {
            $errors = $response['error']['messages'] ?? $response['error']['reason']  ?? [];

            if(is_string($errors)) return $errors;

            $arr = [];

            foreach($errors as $index => $error) {
                $arr[] = "$index: " . implode(", ", $error);
            }

            return implode(PHP_EOL, $arr);
        }

        return null;
    }

    public function getSignature(array $data): string
    {
        $stringToEncrypt = "{$data['reference']}{$data['amount_in_cents']}{$data['currency']}$this->keyIntegrety";
        return hash("sha256", $stringToEncrypt);
    }
}