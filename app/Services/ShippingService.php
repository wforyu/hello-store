<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShippingService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected int $originCityId;

    protected array $couriers;

    protected array $cityIds;

    protected array $fallbackRates;

    public function __construct()
    {
        $this->apiKey = config('rajaongkir.api_key');
        $this->baseUrl = config('rajaongkir.base_url');
        $this->originCityId = (int) config('rajaongkir.origin_city_id');
        $this->couriers = config('rajaongkir.couriers');
        $this->cityIds = config('rajaongkir.city_ids');
        $this->fallbackRates = config('rajaongkir.fallback_rates');
    }

    public function getRates(string $destinationCity, int $weightInGrams): array
    {
        $destinationId = $this->cityIds[$destinationCity] ?? null;

        if ($this->apiKey && $destinationId) {
            $apiRates = $this->fetchFromApi($destinationId, $weightInGrams);
            if ($apiRates) {
                return $apiRates;
            }
        }

        return $this->getFallbackRates($weightInGrams);
    }

    protected function fetchFromApi(int $destinationId, int $weightInGrams): ?array
    {
        try {
            $responses = [];

            foreach ($this->couriers as $code => $name) {
                $response = Http::withHeaders([
                    'key' => $this->apiKey,
                ])->post($this->baseUrl.'/cost', [
                    'origin' => $this->originCityId,
                    'destination' => $destinationId,
                    'weight' => max($weightInGrams, 1000),
                    'courier' => $code,
                ]);

                if ($response->successful()) {
                    $results = $response->json('rajaongkir.results');
                    if ($results) {
                        foreach ($results as $result) {
                            $courierCode = $result['code'];
                            $responses[$courierCode] = [
                                'code' => $courierCode,
                                'name' => $this->couriers[$courierCode] ?? strtoupper($courierCode),
                                'rates' => [],
                            ];

                            foreach ($result['costs'] as $cost) {
                                $responses[$courierCode]['rates'][] = [
                                    'service' => $cost['service'],
                                    'description' => $cost['description'],
                                    'cost' => $cost['cost'][0]['value'],
                                    'etd' => $cost['cost'][0]['etd'] ?? '-',
                                ];
                            }
                        }
                    }
                }
            }

            return ! empty($responses) ? array_values($responses) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getFallbackRates(int $weightInGrams): array
    {
        $rates = [];

        foreach ($this->fallbackRates as $code => $services) {
            $courierRates = [];

            foreach ($services as $service) {
                $courierRates[] = [
                    'service' => $service['service'],
                    'description' => $service['description'],
                    'cost' => $service['cost'],
                    'etd' => $service['etd'],
                ];
            }

            $rates[] = [
                'code' => $code,
                'name' => $this->couriers[$code] ?? strtoupper($code),
                'rates' => $courierRates,
            ];
        }

        return $rates;
    }

    public function getCityId(string $cityName): ?int
    {
        return $this->cityIds[$cityName] ?? null;
    }
}
