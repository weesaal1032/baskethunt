<?php

namespace Tests\Unit\Services\Qa;

use App\Repositories\Contracts\QARepositoryInterface;
use App\Services\Qa\QaReportingService;
use App\Services\Qa\QaScoringService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Mockery;
use Tests\TestCase;

class QaReportingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_summary_resolves_date_range_and_returns_metrics(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2024-05-20 12:00:00'));

        $repository = Mockery::mock(QARepositoryInterface::class);
        $repository->shouldReceive('agentSummaries')
            ->once()
            ->withArgs(function (CarbonImmutable $from, CarbonImmutable $to, $team): bool {
                $this->assertSame('2024-04-22', $from->toDateString());
                $this->assertSame('2024-05-20', $to->toDateString());
                $this->assertNull($team);

                return true;
            })
            ->andReturn(collect([['agent_id' => 1, 'agent_name' => 'Agent A', 'team' => 'Support', 'evaluations' => 2, 'average_score' => 90, 'pass_rate' => 100]]));
        $repository->shouldReceive('teamSummaries')
            ->once()
            ->andReturn(collect([['team' => 'Support', 'evaluations' => 2, 'average_score' => 90, 'pass_rate' => 100]]));
        $repository->shouldReceive('trendline')
            ->once()
            ->andReturn(collect([['date' => '2024-05-20', 'evaluations' => 1, 'average_score' => 90]]));

        $scoring = Mockery::mock(QaScoringService::class);
        $scoring->shouldReceive('passThreshold')->andReturn(85);

        $service = new QaReportingService($repository, $scoring);
        $summary = $service->summary(null, null);

        $this->assertSame('2024-04-22', $summary['from']->toDateString());
        $this->assertSame('2024-05-20', $summary['to']->toDateString());
        $this->assertSame(85, $summary['pass_threshold']);
        $this->assertCount(1, $summary['agents']);
        $this->assertCount(1, $summary['teams']);
        $this->assertCount(1, $summary['trendline']);
    }

    public function test_agent_trend_uses_resolved_range(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2024-05-20 12:00:00'));

        $repository = Mockery::mock(QARepositoryInterface::class);
        $repository->shouldReceive('trendline')
            ->once()
            ->withArgs(function (CarbonImmutable $from, CarbonImmutable $to, $team, $agentId): bool {
                $this->assertSame('2024-04-22', $from->toDateString());
                $this->assertSame('2024-05-20', $to->toDateString());
                $this->assertNull($team);
                $this->assertSame(5, $agentId);

                return true;
            })
            ->andReturn(collect());

        $scoring = Mockery::mock(QaScoringService::class);

        $service = new QaReportingService($repository, $scoring);
        $service->agentTrend(5, null, null);
    }

    public function test_export_scores_streams_lazy_collection(): void
    {
        $repository = Mockery::mock(QARepositoryInterface::class);
        $expected = LazyCollection::make([['version' => 1]]);
        $repository->shouldReceive('exportScores')->once()->andReturn($expected);

        $scoring = Mockery::mock(QaScoringService::class);

        $service = new QaReportingService($repository, $scoring);
        $result = $service->exportScores('2024-05-01', '2024-05-10', 7, 'Support');

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertSame($expected, $result);
    }
}
