<?php

namespace EO\YandexDirect\Classes\Interfaces;

interface ApiInterface
{
    public function add(array $params): array;
    public function update(array $params): array;
    public function delete(array $params): array;
    public function get(array $params): array;
}
