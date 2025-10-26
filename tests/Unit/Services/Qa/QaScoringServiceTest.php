<?php

namespace Tests\Unit\Services\Qa;

use App\Models\Call;
use App\Models\QaScore;
use App\Models\User;
use App\Repositories\Contracts\QARepositoryInterface;
use App\Services\Qa\QaScoringService;
use App\Services\Settings\SettingsService;
use Mockery;
use Tests\TestCase;

class QaScoringServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calculate_score_handles_yes_no_and_scale(): void
    {
        $rubric = [[
            'id' => 'cat',
            'name' => 'Quality',
            'weight' => 30,
            'questions' => [
                ['id' => 'q_yes', 'type' => 'yes_no', 'weight' => 10],
                ['id' => 'q_scale', 'type' => 'scale', 'weight' => 20, 'scale_min' => 0, 'scale_max' => 5],
            ],
        ]];

        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')->with('qa.rubric')->andReturn($rubric);
        $settings->shouldReceive('get')->with('qa.pass_threshold')->andReturn(80);
        $repository = Mockery::mock(QARepositoryInterface::class);

        $service = new QaScoringService($settings, $repository);
        $result = $service->calculateScore($rubric, [
            'q_yes' => true,
            'q_scale' => 5,
        ]);

        $this->assertSame(30, $result['possible']);
        $this->assertSame(100, $result['score']);
        $this->assertSame(100.0, $result['percent']);
    }

    public function test_save_draft_sanitizes_payload_and_persists(): void
    {
        $rubric = [[
            'id' => 'cat',
            'name' => 'Interactions',
            'weight' => 20,
            'questions' => [
                ['id' => 'q_yes', 'type' => 'yes_no', 'weight' => 10],
                ['id' => 'q_scale', 'type' => 'scale', 'weight' => 10, 'scale_min' => 1, 'scale_max' => 5],
            ],
        ]];

        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')->with('qa.rubric')->andReturn($rubric);
        $settings->shouldReceive('get')->with('qa.pass_threshold')->andReturn(75);
        $repository = Mockery::mock(QARepositoryInterface::class);
        $repository->shouldReceive('nextVersionForCall')->once()->with(42)->andReturn(3);

        $repository->shouldReceive('saveDraft')
            ->once()
            ->with(42, 7, Mockery::on(function (array $attributes): bool {
                $this->assertSame(3, $attributes['version']);
                $this->assertSame('draft', $attributes['status']);
                $this->assertSame(1, $attributes['rubric_version']);
                $this->assertSame(['q_yes', 'q_scale'], array_keys($attributes['responses']));
                $this->assertSame('Coached on closing', $attributes['comments']);
                $this->assertSame(['coaching'], $attributes['tags']);
                $this->assertTrue($attributes['passed']);
                $this->assertArrayHasKey('score_breakdown', $attributes);

                return true;
            }))
            ->andReturn(new QaScore([
                'version' => 3,
                'status' => 'draft',
                'total_score' => 88,
                'possible_score' => 100,
                'passed' => true,
            ]));

        $service = new QaScoringService($settings, $repository);
        $result = $service->saveDraft(
            $this->fakeCallId(42),
            $this->fakeUserId(7),
            [
                'responses' => [
                    'q_yes' => true,
                    'q_scale' => 4.2,
                    'ignored' => true,
                ],
                'comment' => '  Coached on closing  ',
                'tags' => ['coaching', 'coaching', '  '],
                'passed' => true,
                'rubric_version' => 1,
            ]
        );

        $this->assertInstanceOf(QaScore::class, $result);
        $this->assertSame('draft', $result->status);
    }

    public function test_submit_marks_score_as_submitted(): void
    {
        $rubric = [[
            'id' => 'cat',
            'questions' => [['id' => 'q_yes', 'type' => 'yes_no', 'weight' => 10]],
        ]];

        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')->with('qa.rubric')->andReturn($rubric);
        $settings->shouldReceive('get')->with('qa.pass_threshold')->andReturn(80);

        $repository = Mockery::mock(QARepositoryInterface::class);
        $repository->shouldReceive('saveDraft')->once()->andReturn(new QaScore([
            'version' => 1,
            'status' => 'draft',
            'total_score' => 100,
            'possible_score' => 100,
            'passed' => true,
        ]));
        $repository->shouldReceive('markSubmitted')
            ->once()
            ->andReturn(new QaScore([
                'version' => 1,
                'status' => 'submitted',
                'total_score' => 100,
                'possible_score' => 100,
                'passed' => true,
            ]));

        $service = new QaScoringService($settings, $repository);
        $result = $service->submit($this->fakeCallId(1), $this->fakeUserId(2), [
            'responses' => ['q_yes' => true],
            'rubric_version' => 1,
            'version' => 1,
        ]);

        $this->assertSame('submitted', $result->status);
    }

    private function fakeCallId(int $id): Call
    {
        $call = new Call();
        $call->id = $id;

        return $call;
    }

    private function fakeUserId(int $id): User
    {
        $user = new User();
        $user->id = $id;

        return $user;
    }
}
