<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\HttpFoundation\BinaryFileResponse as BinaryFile;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use DatabaseMigrations; // VACUUM cannot run inside the transaction RefreshDatabase opens

    public function test_the_backup_is_a_sqlite_file_with_the_data_and_nothing_is_left_on_disk(): void
    {
        $this->actingAs(User::factory()->create());
        Company::create(['name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 'secret-token']]);

        $response = $this->get('/backup');

        $response->assertOk();
        $this->assertStringContainsString('ksef-app-backup-', (string) $response->headers->get('Content-Disposition'));
        $copy = tempnam(sys_get_temp_dir(), 'bk');
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFile::class, $file);
        file_put_contents($copy, file_get_contents($file->getFile()->getPathname()));
        $this->assertStringStartsWith('SQLite format 3', (string) file_get_contents($copy, false, null, 0, 15));
        $pdo = new \PDO('sqlite:'.$copy);
        $this->assertSame('Seller sp. z o.o.', $pdo->query('select name from companies')->fetchColumn());
        $this->assertStringNotContainsString('secret-token', (string) file_get_contents($copy), 'Logins stay encrypted in the copy.');
        unlink($copy);
        @unlink($file->getFile()->getPathname()); // a real response deletes it after sending; this one is never sent
    }

    public function test_it_needs_a_login(): void
    {
        $this->get('/backup')->assertRedirect('/login');
    }
}
