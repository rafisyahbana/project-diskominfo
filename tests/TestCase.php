<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nilai API key yang digunakan di semua test.
     * Harus sesuai dengan AGENT_API_KEY di phpunit.xml / phpunit env.
     */
    protected string $agentApiKey = 'test-agent-api-key-for-phpunit';

    /**
     * Helper: kirim GET request dengan X-Agent-Api-Key header yang valid.
     */
    protected function agentGetJson(string $uri, array $headers = [])
    {
        return $this->getJson($uri, array_merge(
            ['X-Agent-Api-Key' => $this->agentApiKey],
            $headers
        ));
    }

    /**
     * Helper: kirim POST request dengan X-Agent-Api-Key header yang valid.
     */
    protected function agentPostJson(string $uri, array $data = [], array $headers = [])
    {
        return $this->postJson($uri, $data, array_merge(
            ['X-Agent-Api-Key' => $this->agentApiKey],
            $headers
        ));
    }
}
