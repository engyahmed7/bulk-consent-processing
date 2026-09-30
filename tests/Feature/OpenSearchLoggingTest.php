<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpenSearchLoggingTest extends TestCase
{
    public function test_default_stack_writes_redacted_json_with_authenticated_user(): void
    {
        $this->assertContains('opensearch-json', config('logging.channels.stack.channels'));

        $path = tempnam(sys_get_temp_dir(), 'opensearch-log-');
        $this->assertNotFalse($path);

        $user = new User;
        $user->setAttribute('id', 42);
        Auth::setUser($user);

        config([
            'logging.channels.stack.channels' => ['opensearch-json'],
            'logging.channels.opensearch-json.driver' => 'single',
            'logging.channels.opensearch-json.path' => $path,
        ]);
        Log::forgetChannel('stack');
        Log::forgetChannel('opensearch-json');

        try {
            Log::channel('stack')->info('RabbitMQ consumer started', [
                'access_token' => 'must-not-be-written',
            ]);

            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $record = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('[REDACTED]', $record['context']['access_token']);
            $this->assertSame(42, $record['extra']['authenticated_user']['id']);
            $this->assertStringContainsString('RabbitMQ consumer started', $record['message']);
        } finally {
            @unlink($path);
            Auth::forgetGuards();
        }
    }
}
