<?php

declare(strict_types=1);

use App\Infrastructure\Workflow\Activities\SubtitleExtractorActivity;
use App\Infrastructure\Workflow\Activities\DownloadAudioActivity;
use App\Infrastructure\Workflow\Activities\GroqTranscriberActivity;
use App\Infrastructure\Workflow\Activities\AiSummaryActivity;
use App\Infrastructure\Workflow\Activities\PersistResultActivity;
use App\Infrastructure\Workflow\Activities\CleanupActivity;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    Schema::create('workflows', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->text('class');
        $blueprint->text('arguments')->nullable();
        $blueprint->text('output')->nullable();
        $blueprint->string('status')->default('pending')->index();
        $blueprint->timestamps(6);
    });

    Schema::create('workflow_logs', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('stored_workflow_id')->index();
        $blueprint->unsignedBigInteger('index');
        $blueprint->timestamp('now', 6);
        $blueprint->text('class');
        $blueprint->text('result')->nullable();
        $blueprint->timestamp('created_at', 6)->nullable();
        $blueprint->unique(['stored_workflow_id', 'index']);
        $blueprint->foreign('stored_workflow_id')->references('id')->on('workflows');
    });

    Schema::create('workflow_signals', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('stored_workflow_id')->index();
        $blueprint->text('method');
        $blueprint->text('arguments')->nullable();
        $blueprint->timestamp('created_at', 6)->nullable();
        $blueprint->index(['stored_workflow_id', 'created_at']);
        $blueprint->foreign('stored_workflow_id')->references('id')->on('workflows');
    });

    Schema::create('workflow_exceptions', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('stored_workflow_id')->index();
        $blueprint->text('class');
        $blueprint->text('exception');
        $blueprint->timestamp('created_at', 6)->nullable();
        $blueprint->foreign('stored_workflow_id')->references('id')->on('workflows');
    });

    Schema::create('workflow_timers', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('stored_workflow_id')->index();
        $blueprint->integer('index');
        $blueprint->timestamp('stop_at', 6);
        $blueprint->timestamp('created_at', 6)->nullable();
        $blueprint->index(['stored_workflow_id', 'created_at']);
        $blueprint->foreign('stored_workflow_id')->references('id')->on('workflows');
    });

    Schema::create('workflow_relationships', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('parent_workflow_id')->nullable()->index();
        $blueprint->unsignedBigInteger('parent_index');
        $blueprint->timestamp('parent_now');
        $blueprint->foreignId('child_workflow_id')->nullable()->index();
        $blueprint->foreign('parent_workflow_id')->references('id')->on('workflows');
        $blueprint->foreign('child_workflow_id')->references('id')->on('workflows');
    });

    WorkflowStub::fake();
});

afterEach(function (): void {
    Schema::dropIfExists('workflow_relationships');
    Schema::dropIfExists('workflow_timers');
    Schema::dropIfExists('workflow_exceptions');
    Schema::dropIfExists('workflow_signals');
    Schema::dropIfExists('workflow_logs');
    Schema::dropIfExists('workflows');
});

it('runs subtitle -> summary -> persist flow without audio download when subtitles exist', function (): void {
    WorkflowStub::mock(SubtitleExtractorActivity::class, 'Hello world transcript');
    WorkflowStub::mock(AiSummaryActivity::class, 'Short summary');
    WorkflowStub::mock(PersistResultActivity::class, null);

    $workflow = WorkflowStub::make(TranscribeVideoWorkflow::class);
    $workflow->start('task-123', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    WorkflowStub::assertDispatched(SubtitleExtractorActivity::class);
    WorkflowStub::assertDispatched(AiSummaryActivity::class);
    WorkflowStub::assertDispatched(PersistResultActivity::class);
    WorkflowStub::assertNotDispatched(DownloadAudioActivity::class);
    WorkflowStub::assertNotDispatched(GroqTranscriberActivity::class);
    WorkflowStub::assertNotDispatched(CleanupActivity::class);
});

it(
    'runs subtitle -> download -> transcribe -> summary -> persist -> cleanup when subtitles are missing'
    , function (): void {
    WorkflowStub::mock(SubtitleExtractorActivity::class, null);
    WorkflowStub::mock(DownloadAudioActivity::class, new DownloadedAudioResult('/tmp/task-123.mp3'));
    WorkflowStub::mock(
        GroqTranscriberActivity::class,
        new WorkflowTranscriptionResult('Full transcript from audio', 321),
    );
    WorkflowStub::mock(AiSummaryActivity::class, 'Short summary');
    WorkflowStub::mock(PersistResultActivity::class, null);
    WorkflowStub::mock(CleanupActivity::class, null);

    $workflow = WorkflowStub::make(TranscribeVideoWorkflow::class);
    $workflow->start('task-123', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    WorkflowStub::assertDispatched(SubtitleExtractorActivity::class);
    WorkflowStub::assertDispatched(DownloadAudioActivity::class);
    WorkflowStub::assertDispatched(GroqTranscriberActivity::class);
    WorkflowStub::assertDispatched(AiSummaryActivity::class);
    WorkflowStub::assertDispatched(PersistResultActivity::class);
    WorkflowStub::assertDispatched(CleanupActivity::class);
});
