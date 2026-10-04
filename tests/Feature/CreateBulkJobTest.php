<?php

namespace Tests\Feature;

use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\ConsentAction;
use App\Domains\Bulk\Messaging\BulkMessaging;
use App\Domains\Bulk\Models\BulkJob;
use App\Filament\Resources\BulkJobs\Pages\CreateBulkJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CreateBulkJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('minio');
    }

    public function test_guests_are_redirected_away_from_the_create_page(): void
    {
        $this->get('/admin/bulk-jobs/create')
            ->assertRedirect('/admin/login');
    }

    public function test_authenticated_upload_stores_the_csv_in_minio_and_returns_a_process_id(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateBulkJob::class)
            ->fillForm([
                'action' => ConsentAction::OptIn->value,
                'file' => $this->makeCsvUpload(),
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $job = BulkJob::query()->first();

        $this->assertNotNull($job);
        $this->assertSame(BulkJobStatus::Queued, $job->status);
        $this->assertSame(ConsentAction::OptIn, $job->action);
        $this->assertSame($user->email, $job->created_by);
        $this->assertNotSame('', $job->uuid);
        $this->assertTrue(Storage::disk('minio')->exists($job->input_path));
        $this->assertStringStartsWith('inputs/', $job->input_path);
        $this->assertStringEndsWith('.csv', $job->input_path);
        $this->assertDatabaseHas('outbox_messages', [
            'exchange' => BulkMessaging::EVENTS_EXCHANGE,
            'routing_key' => BulkMessaging::PARSE_REQUESTED,
        ]);
    }

    public function test_livewire_temporary_uploads_use_minio_outside_testing(): void
    {
        $this->assertSame('minio', config('livewire.temporary_file_upload.disk'));
    }

    public function test_create_form_rejects_a_non_csv_file(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create(
            'users.xlsx',
            1,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        Livewire::actingAs($user)
            ->test(CreateBulkJob::class)
            ->fillForm([
                'action' => ConsentAction::OptOut->value,
                'file' => $file,
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);

        $this->assertSame(0, BulkJob::query()->count());
    }

    private function makeCsvUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'users.csv',
            "userid,phonenumber\r\nu1,966500000001\r\n",
        );
    }
}
