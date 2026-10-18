<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Http\Controllers\PrivateFileDownloadController;
use App\Services\Security\SecureUploadService;
use App\Services\Security\SsrfValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdorTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $studentA;

    protected User $studentB;

    protected User $teacherA;

    protected User $teacherB;

    protected Course $course;

    protected Batch $batchA;

    protected Batch $batchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Tapovan Rishikesh',
            'code' => 'TAP',
            'city' => 'Rishikesh',
            'state' => 'Uttarakhand',
            'country' => 'IN',
            'postal_code' => '249192',
        ]);

        $this->studentA = User::create([
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $this->studentB = User::create([
            'first_name' => 'Priya',
            'last_name' => 'Nair',
            'email' => 'priya@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $this->teacherA = User::create([
            'first_name' => 'Acharya',
            'last_name' => 'Vasudeva',
            'email' => 'vasudeva@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $this->teacherB = User::create([
            'first_name' => 'Acharya',
            'last_name' => 'Ananda',
            'email' => 'ananda@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $category = Category::create([
            'name' => 'Hatha Yoga',
            'slug' => 'hatha-yoga',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'category_id' => $category->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->teacherA->id,
            'name' => '200H Yoga Teacher Training',
            'slug' => '200h-yoga-teacher-training',
            'default_delivery_mode' => DeliveryMode::Offline,
            'type' => CourseType::LongTerm,
            'difficulty_level' => DifficultyLevel::Beginner,
            'status' => CourseStatus::Published,
            'base_fee_amount' => 15000000,
            'base_fee_currency' => 'INR',
        ]);

        $this->batchA = Batch::create([
            'course_id' => $this->course->id,
            'branch_id' => $this->branch->id,
            'name' => 'Batch Alpha',
            'delivery_mode' => DeliveryMode::Offline,
            'status' => BatchStatus::InProgress,
            'capacity' => 30,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addMonths(1)->endOfDay(),
        ]);

        $this->batchB = Batch::create([
            'course_id' => $this->course->id,
            'branch_id' => $this->branch->id,
            'name' => 'Batch Beta',
            'delivery_mode' => DeliveryMode::Offline,
            'status' => BatchStatus::InProgress,
            'capacity' => 30,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addMonths(1)->endOfDay(),
        ]);

        $this->course->teachers()->attach($this->teacherA->id);
    }

    /**
     * IDOR Test 1: Student Portal Route Group
     * Student A cannot access Student B's invoices or billing documents.
     */
    public function test_idor_student_cannot_access_another_students_invoice(): void
    {
        $invoiceB = Invoice::create([
            'student_id' => $this->studentB->id,
            'invoice_number' => 'INV-2027-B-001',
            'financial_year' => '2026-2027',
            'sequence_number' => 1,
            'subtotal_amount' => 5000000,
            'discount_amount' => 0,
            'tax_amount' => 900000,
            'total_amount' => 5900000,
            'currency' => 'INR',
            'status' => InvoicePaymentStatus::Paid,
            'due_date' => now()->addDays(7),
            'issued_at' => now(),
            'billing_name' => 'Priya Nair',
            'billing_email' => 'priya@pramana.edu',
        ]);

        // Student A attempts to access Student B's invoice status
        $response = $this->actingAs($this->studentA)->get(route('invoices.status', $invoiceB->ulid));
        $response->assertForbidden();

        // Student A attempts to view Student B's invoice details
        $viewResponse = $this->actingAs($this->studentA)->get(route('invoices.view', $invoiceB->ulid));
        $viewResponse->assertForbidden();
    }

    /**
     * IDOR Test 2: Signed Private File URL Group
     * Student A cannot download Student B's assignment submission.
     */
    public function test_idor_student_cannot_download_another_students_submission(): void
    {
        Storage::fake('local');
        $fakePath = 'submissions/assignment-1-student-b.pdf';
        Storage::disk('local')->put($fakePath, '%PDF-1.4 Mock submission content');

        $assignment = Assignment::create([
            'course_id' => $this->course->id,
            'batch_id' => $this->batchA->id,
            'title' => 'Anatomy Practical Paper',
            'submission_types' => ['file'],
            'max_score' => 100,
            'due_at' => now()->addDays(7),
        ]);

        $submissionB = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->studentB->id,
            'status' => 'submitted',
            'file_path' => $fakePath,
            'submitted_at' => now(),
        ]);

        $validSignedUrl = PrivateFileDownloadController::signedDownloadUrl('submission', $submissionB->id);

        // Student A attempts to download Student B's submission using valid signature
        $response = $this->actingAs($this->studentA)->get($validSignedUrl);
        $response->assertForbidden();

        // Teacher A (assigned to Batch A) CAN download it
        $teacherResponse = $this->actingAs($this->teacherA)->get($validSignedUrl);
        $teacherResponse->assertStatus(200);
    }

    /**
     * IDOR Test 3: Teacher Portal Route Group
     * Teacher A cannot modify or mark attendance for Teacher B's batch sessions.
     */
    public function test_idor_teacher_cannot_access_unassigned_batch_sessions(): void
    {
        $sessionB = Session::create([
            'batch_id' => $this->batchB->id,
            'title' => 'Pranayama Masterclass Beta',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'status' => SessionStatus::Scheduled,
        ]);

        // Teacher A attempts to access attendance marker for Teacher B's session
        $response = $this->actingAs($this->teacherA)->get(route('teacher.attendance', ['session' => $sessionB->id]));
        // Should either 403 or redirect with authorization error
        $this->assertTrue(in_array($response->status(), [403, 404, 302], true));
    }

    /**
     * IDOR Test 4: Privilege Escalation
     * Students cannot access Teacher or Moderator routes.
     */
    public function test_idor_student_cannot_access_teacher_or_moderator_portals(): void
    {
        $teacherResponse = $this->actingAs($this->studentA)->get(route('teacher.dashboard'));
        $this->assertTrue(in_array($teacherResponse->status(), [403, 302], true));

        $moderatorResponse = $this->actingAs($this->studentA)->get(route('moderator.console'));
        $this->assertTrue(in_array($moderatorResponse->status(), [403, 302], true));

        $outboxResponse = $this->actingAs($this->studentA)->get(route('moderator.communication.outbox'));
        $this->assertTrue(in_array($outboxResponse->status(), [403, 302], true));
    }

    /**
     * IDOR Test 5: Tampered & Expired Signed URL Protection
     * Altering signed query parameters or using expired URLs is strictly rejected with 403.
     */
    public function test_signed_urls_reject_tampering_and_expiry(): void
    {
        $url = URL::temporarySignedRoute(
            'secure.download',
            now()->subMinutes(5), // Already expired
            ['type' => 'invoice', 'id' => 1]
        );

        $response = $this->get($url);
        $response->assertForbidden();

        // Tamper with parameter ID in path
        $validUrl = URL::temporarySignedRoute(
            'secure.download',
            now()->addMinutes(10),
            ['type' => 'invoice', 'id' => 1]
        );
        $tamperedUrl = str_replace('/invoice/1', '/invoice/2', $validUrl);

        $tamperedResponse = $this->get($tamperedUrl);
        $tamperedResponse->assertForbidden();
    }

    /**
     * Security Test 6: SSRF Protection Validator
     * Disallows internal IPs, cloud metadata endpoints, and invalid protocols.
     */
    public function test_ssrf_validator_blocks_malicious_internal_destinations(): void
    {
        $ssrf = new SsrfValidator;

        // Cloud metadata endpoints
        $this->assertFalse($ssrf->isSafeUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse($ssrf->isSafeUrl('http://metadata.google.internal/computeMetadata/v1/'));

        // Localhost and loopback
        $this->assertFalse($ssrf->isSafeUrl('http://localhost:8000/admin'));
        $this->assertFalse($ssrf->isSafeUrl('http://127.0.0.1:3306'));
        $this->assertFalse($ssrf->isSafeUrl('http://[::1]:80/'));

        // RFC 1918 Private networks
        $this->assertFalse($ssrf->isSafeUrl('http://10.0.0.1/internal-api'));
        $this->assertFalse($ssrf->isSafeUrl('http://192.168.1.1/router-status'));
        $this->assertFalse($ssrf->isSafeUrl('http://172.16.0.5/secrets'));

        // Dangerous URI schemes
        $this->assertFalse($ssrf->isSafeUrl('file:///etc/passwd'));
        $this->assertFalse($ssrf->isSafeUrl('gopher://127.0.0.1:6379/_flushall'));

        // Legitimate public domain
        $this->assertTrue($ssrf->isSafeUrl('https://example.com/webhook'));
        $this->assertTrue($ssrf->isSafeUrl('https://api.stripe.com/v1/charges'));
    }

    /**
     * Security Test 7: Upload Validation (MIME Sniffing & Antivirus Hook)
     * Polyglot script files and EICAR malware test strings are rejected.
     */
    public function test_upload_validator_blocks_polyglot_and_malware_signatures(): void
    {
        $uploadService = new SecureUploadService;

        // 1. Executable PHP script disguised with .pdf extension
        $fakePdf = UploadedFile::fake()->createWithContent('malicious.pdf', "<?php echo shell_exec('id'); ?>");
        $this->expectException(ValidationException::class);
        $uploadService->inspect($fakePdf);
    }

    public function test_upload_validator_blocks_eicar_test_signature(): void
    {
        $uploadService = new SecureUploadService;

        // 2. EICAR test string
        $eicarFile = UploadedFile::fake()->createWithContent('test_virus.txt', SecureUploadService::EICAR_SIGNATURE);
        $this->expectException(ValidationException::class);
        $uploadService->inspect($eicarFile);
    }
}
