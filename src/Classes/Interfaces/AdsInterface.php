<?php

namespace EO\YandexDirect\Classes\Interfaces;

interface AdsInterface extends ApiInterface
{
    public function archive(array $params): array;
    public function moderate(array $params): array;
    public function resume(array $params): array;
    public function suspend(array $params): array;
    public function unarchive(array $params): array;
}
