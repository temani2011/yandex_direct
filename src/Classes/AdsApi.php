<?php

namespace EO\YandexDirect\Classes;

use Exception;
use EO\YandexDirect\Classes\Interfaces\AdsInterface;

final class AdsApi extends Api implements AdsInterface
{
    private $url = 'https://api.direct.yandex.com/json/v5/ads';

    public function add(array $params): array
    {
        return [];
    }

    public function update(array $adsList): array
    {
        $postData = [
            'method' => 'update',
            'params' => [
                'Ads' => $adsList
            ],
        ];

        $response = $this->makeCurlRequest($this->url, $postData);

        return $response['result']['UpdateResults'];
    }

    public function delete(array $params): array
    {
        return [];
    }

    /**
     * get ads
     *
     * @info must contain fields: FieldNames, TextAdFieldNames, SelectionCriteria
     * @see https://yandex.ru/dev/direct/doc/ref-v5/ads/get.html
     *
     * @param  array $params
     * @return array
     */
    public function get(array $params): array
    {
        $postData = [
            'method' => 'get',
            'params' => $params,
        ];

        try {
            $response = $this->makeCurlRequest($this->url, $postData);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $response['result']['Ads'];
    }
    public function archive(array $params): array
    {
        return [];
    }
    public function moderate(array $params): array
    {
        return [];
    }

    /**
     * resume ads
     *
     * @info must contain fields: SelectionCriteria
     * @see https://yandex.ru/dev/direct/doc/ref-v5/ads/resume.html
     *
     * @param  array $adIds
     * @return array
     */
    public function resume(array $adIds): array
    {
        $postData = [
            'method' => 'resume',
            'params' => [
                'SelectionCriteria' => (object) [
                    'Ids' => $adIds,
                ],
            ]
        ];

        try {
            $response = $this->makeCurlRequest($this->url, $postData);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $response['result']['ResumeResults'];
    }

    /**
     * suspend ads
     *
     * @info must contain fields: SelectionCriteria
     * @see https://yandex.ru/dev/direct/doc/ref-v5/ads/suspend.html
     *
     * @param  array $adIds
     * @return array
     */
    public function suspend(array $adIds): array
    {
        $postData = [
            'method' => 'suspend',
            'params' => [
                'SelectionCriteria' => (object) [
                    'Ids' => $adIds,
                ],
            ]
        ];

        try {
            $response = $this->makeCurlRequest($this->url, $postData);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $response['result']['SuspendResults'];
    }

    public function unarchive(array $params): array
    {
        return [];
    }
}
