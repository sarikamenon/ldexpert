<?php

declare(strict_types=1);

namespace Tests\Browser\Admin;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class StudentImportTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->school = School::factory()->create();
    }

    public function test_admin_can_view_import_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.import'))
                ->assertSee('Import Students from CSV')
                ->assertSee('Required Columns')
                ->assertSee('Download CSV Template')
                ->assertSee('School')
                ->assertSee('CSV File');
        });
    }

    public function test_admin_can_download_template(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.import'))
                ->clickLink('Download CSV Template')
                ->pause(500); // Wait for download to start

            // Note: Dusk cannot directly verify file downloads,
            // but we can verify the link exists and is clickable
        });
    }

    public function test_import_form_validates_required_fields(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.import'))
                ->press('Import Students')
                ->pause(500)
                ->assertSee('Please select a CSV file')
                ->orSee('Please select a school');
        });
    }

    public function test_import_displays_results_after_successful_import(): void
    {
        $csvContent = $this->generateCsvContent([
            [
                'first_name' => 'Test',
                'last_name' => 'Student',
                'email' => 'test@example.com',
                'gender' => 'Male',
                'date_of_birth' => '2010-01-01',
                'school_id' => (string) $this->school->id,
                'id_number' => 'STU001',
                'timezone' => 'America/New_York',
                'grade_level' => '8',
                'city' => 'New York',
                'state' => 'NY',
                'zip_code' => '10001',
            ],
        ]);

        $filePath = storage_path('app/test-import.csv');
        file_put_contents($filePath, $csvContent);

        $this->browse(function (Browser $browser) use ($filePath) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.import'))
                ->select('school_id', (string) $this->school->id)
                ->attach('file', $filePath)
                ->press('Import Students')
                ->waitFor('#importResults', 10)
                ->assertSee('Import Results')
                ->assertSee('Total Rows')
                ->assertSee('Successfully Imported');
        });

        // Cleanup
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    private function generateCsvContent(array $rows): string
    {
        $requiredColumns = [
            'first_name',
            'last_name',
            'email',
            'gender',
            'date_of_birth',
            'school_id',
            'id_number',
            'timezone',
            'grade_level',
            'city',
            'state',
            'zip_code',
        ];

        $optionalColumns = [
            'middle_name',
            'address',
            'parent_guardian_name',
            'parent_guardian_email',
            'parent_guardian_phone',
        ];

        $allColumns = array_merge($requiredColumns, $optionalColumns);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $allColumns);

        foreach ($rows as $row) {
            $line = [];
            foreach ($allColumns as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
