<?php

namespace Tests\Unit\Services\Leave;

use App\Models\Duration;
use App\Models\Holiday;
use App\Services\Leave\LeaveRequestValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveRequestValidatorTest extends TestCase
{
    use RefreshDatabase;

    private LeaveRequestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the duration records required by the validator.
        Duration::create([
            'name' => 'Full Day',
            'days' => '1.00',
        ]);

        Duration::create([
            'name' => 'Half Day',
            'days' => '0.50',
        ]);

        Duration::create([
            'name' => 'Multiple Days',
            'days' => '1.00',
        ]);

        // Create a holiday used by the validation tests.
        Holiday::create([
            'holiday_date' => '2026-01-26',
            'name' => 'Republic Day',
        ]);

        $this->validator = app(LeaveRequestValidator::class);
    }

    public function test_full_day_on_a_working_day_is_valid(): void
    {
        $result = $this->validator->validate(
            '2026-10-26',
            '2026-10-26',
            $this->durationId('Full Day')
        );

        $this->assertSame(1, $result['working_days']);
        $this->assertSame('1.00', $result['deduct_days']);
        $this->assertSame('Full Day', $result['duration_name']);
    }

    public function test_first_half_day_is_valid(): void
    {
        $result = $this->validator->validate(
            '2026-10-26',
            '2026-10-26',
            $this->durationId('Half Day'),
            'first_half'
        );

        $this->assertSame(1, $result['working_days']);
        $this->assertSame('0.50', $result['deduct_days']);
        $this->assertSame('first_half', $result['half_day_period']);
    }

    public function test_second_half_day_is_valid(): void
    {
        $result = $this->validator->validate(
            '2026-10-26',
            '2026-10-26',
            $this->durationId('Half Day'),
            'second_half'
        );

        $this->assertSame('0.50', $result['deduct_days']);
        $this->assertSame('second_half', $result['half_day_period']);
    }

    public function test_multiple_days_excludes_weekends(): void
    {
        $result = $this->validator->validate(
            '2026-10-30',
            '2026-11-02',
            $this->durationId('Multiple Days')
        );

        $this->assertSame(2, $result['working_days']);
        $this->assertSame('2.00', $result['deduct_days']);
    }

    public function test_request_on_a_public_holiday_is_rejected(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-01-26',
                '2026-01-26',
                $this->durationId('Full Day')
            ),
            'The selected date range contains no eligible working days.'
        );
    }

    public function test_half_day_on_saturday_is_rejected(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-10-31',
                '2026-10-31',
                $this->durationId('Half Day'),
                'first_half'
            ),
            'The selected date range contains no eligible working days.'
        );
    }

    public function test_range_containing_only_weekends_is_rejected(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-10-31',
                '2026-11-01',
                $this->durationId('Multiple Days')
            ),
            'The selected date range contains no eligible working days.'
        );
    }

    public function test_invalid_calendar_date_is_rejected(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-02-30',
                '2026-02-30',
                $this->durationId('Full Day')
            ),
            'Enter a valid date in YYYY-MM-DD format.'
        );
    }

    public function test_half_day_requires_a_half_day_period(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-10-26',
                '2026-10-26',
                $this->durationId('Half Day')
            ),
            'Select either the first half or second half.'
        );
    }

    public function test_full_day_does_not_allow_a_half_day_period(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-10-26',
                '2026-10-26',
                $this->durationId('Full Day'),
                'first_half'
            ),
            'A half-day period is allowed only for half-day leave.'
        );
    }

    public function test_half_day_cannot_cover_multiple_dates(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-10-26',
                '2026-10-27',
                $this->durationId('Half Day'),
                'first_half'
            ),
            'Half-day leave must cover exactly one date.'
        );
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-11-02',
                '2026-10-30',
                $this->durationId('Multiple Days')
            ),
            'The end date must be on or after the start date.'
        );
    }

    public function test_nonexistent_duration_is_rejected(): void
    {
        $this->assertValidationMessage(
            fn() => $this->validator->validate(
                '2026-10-26',
                '2026-10-26',
                999
            ),
            'The selected duration is invalid.'
        );
    }

    /**
     * Assert that a validation exception contains the expected message.
     */
    private function assertValidationMessage(
        callable $callback,
        string $expectedMessage
    ): void {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $messages = collect($exception->errors())
                ->flatten()
                ->all();

            $this->assertContains($expectedMessage, $messages);

            return;
        }

        $this->fail(
            "Expected a ValidationException containing: {$expectedMessage}"
        );
    }

    /**
     * Get the database ID for a duration by its unique name.
     */
    private function durationId(string $name): int
    {
        $id = Duration::query()
            ->where('name', $name)
            ->value('id');

        $this->assertNotNull(
            $id,
            "The '{$name}' duration must exist in the test database."
        );

        return (int) $id;
    }
}
