<?php

declare(strict_types=1);

use App\Domain\ValueObjects\BlogPost;
use App\Domain\ValueObjects\BlogSection;
use App\Domain\ValueObjects\ContentMeta;
use App\Domain\ValueObjects\Flashcard;
use App\Domain\ValueObjects\HighlightMoment;
use App\Domain\ValueObjects\LinkedInPost;
use App\Domain\ValueObjects\SummaryChapter;
use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\TutorialStep;

it('stores introduction, keyPoints and conclusion', function (): void {
    $kp = new SummaryKeyPoint('01:30', 'Title', 'Details');
    $result = new SummaryResult('Intro text', [$kp], 'Final thought');

    expect($result->introduction())->toBe('Intro text')
        ->and($result->keyPoints())->toHaveCount(1)
        ->and($result->keyPoints()[0])->toBe($kp)
        ->and($result->conclusion())->toBe('Final thought');
});

it('defaults conclusion to null', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->conclusion())->toBeNull();
});

it('serializes to array via toArray()', function (): void {
    $kp = new SummaryKeyPoint('03:15', 'Setup', 'How to set up.');
    $result = new SummaryResult('Introduction text', [$kp], 'Closing remark');

    expect($result->toArray())->toBe([
        'introduction'      => 'Introduction text',
        'key_points'        => [
            ['timecode' => '03:15', 'title' => 'Setup', 'details' => 'How to set up.'],
        ],
        'conclusion'        => 'Closing remark',
        'resources'         => [],
        'clickbait_verdict' => null,
        'tutorial_steps'    => [],
        'chapters'          => [],
        'flashcards'        => [],
        'highlights'        => [],
        'content_meta'      => null,
        'topics'            => [],
        'blog_post'         => null,
        'linkedin_post'     => null,
    ]);
});

it('serializes with null conclusion', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->toArray()['conclusion'])->toBeNull();
});

it('deserializes from array via fromArray()', function (): void {
    $data = [
        'introduction' => 'Hello world',
        'key_points'   => [
            ['timecode' => '00:30', 'title' => 'Start', 'details' => 'First point.'],
        ],
        'conclusion'  => 'Done.',
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->introduction())->toBe('Hello world')
        ->and($result->keyPoints())->toHaveCount(1)
        ->and($result->keyPoints()[0]->timecode)->toBe('00:30')
        ->and($result->conclusion())->toBe('Done.');
});

it('fromArray handles missing conclusion key', function (): void {
    $data = [
        'introduction' => 'No conclusion here',
        'key_points'   => [],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->conclusion())->toBeNull();
});

it('round-trips through toArray and fromArray', function (): void {
    $kp = new SummaryKeyPoint('10:00', 'Chapter', 'Long detail.');
    $original = new SummaryResult('Into text', [$kp], 'Summary end');

    $roundTrip = SummaryResult::fromArray($original->toArray());

    expect($roundTrip->introduction())->toBe($original->introduction())
        ->and($roundTrip->conclusion())->toBe($original->conclusion())
        ->and($roundTrip->keyPoints()[0]->timecode)->toBe('10:00')
        ->and($roundTrip->keyPoints()[0]->title)->toBe('Chapter')
        ->and($roundTrip->resources())->toBe([])
        ->and($roundTrip->clickbaitVerdict())->toBeNull()
        ->and($roundTrip->tutorialSteps())->toBe([]);
});

it('stores and retrieves tutorial steps', function (): void {
    $step = new TutorialStep(step: 1, time: '01:00', action: 'Open settings');
    $result = new SummaryResult('Intro', [], tutorialSteps: [$step]);

    expect($result->tutorialSteps())->toHaveCount(1)
        ->and($result->tutorialSteps()[0]->step)->toBe(1)
        ->and($result->tutorialSteps()[0]->time)->toBe('01:00')
        ->and($result->tutorialSteps()[0]->action)->toBe('Open settings');
});

it('defaults tutorialSteps to empty array', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->tutorialSteps())->toBe([]);
});

it('stores and retrieves chapters', function (): void {
    $chapter = new SummaryChapter(
        title: 'Introduction',
        startTimecode: '00:00',
        endTimecode: '05:30',
    );
    $result = new SummaryResult('Intro', [], chapters: [$chapter]);

    expect($result->chapters())->toHaveCount(1)
        ->and($result->chapters()[0]->title)->toBe('Introduction')
        ->and($result->chapters()[0]->startTimecode)->toBe('00:00')
        ->and($result->chapters()[0]->endTimecode)->toBe('05:30');
});

it('defaults chapters to empty array', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->chapters())->toBe([]);
});

it('serializes chapters in toArray', function (): void {
    $chapter = new SummaryChapter(
        title: 'Core',
        startTimecode: '05:30',
        endTimecode: '12:00',
    );
    $result = new SummaryResult('Intro', [], chapters: [$chapter]);

    expect($result->toArray()['chapters'])->toBe([
        ['title' => 'Core', 'start_timecode' => '05:30', 'end_timecode' => '12:00'],
    ]);
});

it('deserializes chapters from fromArray', function (): void {
    $data = [
        'introduction' => 'Intro',
        'key_points'   => [],
        'chapters'     => [
            ['title' => 'Wrap Up', 'start_timecode' => '12:00', 'end_timecode' => '15:00'],
        ],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->chapters())->toHaveCount(1)
        ->and($result->chapters()[0]->title)->toBe('Wrap Up')
        ->and($result->chapters()[0]->startTimecode)->toBe('12:00')
        ->and($result->chapters()[0]->endTimecode)->toBe('15:00');
});

it('fromArray handles missing chapters key', function (): void {
    $data = [
        'introduction' => 'No chapters',
        'key_points'   => [],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->chapters())->toBe([]);
});

it('round-trips chapters through toArray and fromArray', function (): void {
    $chapter = new SummaryChapter(
        title: 'Setup',
        startTimecode: '00:00',
        endTimecode: '03:00',
    );
    $original = new SummaryResult('Intro', [], chapters: [$chapter]);

    $roundTrip = SummaryResult::fromArray($original->toArray());

    expect($roundTrip->chapters())->toHaveCount(1)
        ->and($roundTrip->chapters()[0]->title)->toBe('Setup')
        ->and($roundTrip->chapters()[0]->startTimecode)->toBe('00:00')
        ->and($roundTrip->chapters()[0]->endTimecode)->toBe('03:00');
});

it('serializes tutorial steps in toArray', function (): void {
    $step = new TutorialStep(step: 1, time: '02:00', action: 'Run npm install');
    $result = new SummaryResult('Intro', [], tutorialSteps: [$step]);

    expect($result->toArray()['tutorial_steps'])->toBe([
        ['step' => 1, 'time' => '02:00', 'action' => 'Run npm install'],
    ]);
});

it('deserializes tutorial steps from fromArray', function (): void {
    $data = [
        'introduction'   => 'Intro',
        'key_points'     => [],
        'tutorial_steps' => [
            ['step' => 1, 'time' => '03:00', 'action' => 'Do something'],
        ],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->tutorialSteps())->toHaveCount(1)
        ->and($result->tutorialSteps()[0]->step)->toBe(1)
        ->and($result->tutorialSteps()[0]->time)->toBe('03:00')
        ->and($result->tutorialSteps()[0]->action)->toBe('Do something');
});

it('fromArray handles missing tutorial_steps', function (): void {
    $data = [
        'introduction' => 'No tutorial here',
        'key_points'   => [],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->tutorialSteps())->toBe([]);
});

it('stores and retrieves flashcards', function (): void {
    $card = new Flashcard('Q?', 'A.', '00:01:00', 'easy');
    $result = new SummaryResult('Intro', [], flashCards: [$card]);

    expect($result->flashCards())->toHaveCount(1)
        ->and($result->flashCards()[0]->question)->toBe('Q?')
        ->and($result->flashCards()[0]->answer)->toBe('A.')
        ->and($result->flashCards()[0]->sourceTimecode)->toBe('00:01:00')
        ->and($result->flashCards()[0]->difficulty)->toBe('easy');
});

it('defaults flashCards to empty array', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->flashCards())->toBe([]);
});

it('serializes flashcards in toArray', function (): void {
    $card = new Flashcard('Q', 'A', '00:30', 'medium');
    $result = new SummaryResult('Intro', [], flashCards: [$card]);

    expect($result->toArray()['flashcards'])->toBe([
        ['question' => 'Q', 'answer' => 'A', 'source_timecode' => '00:30', 'difficulty' => 'medium'],
    ]);
});

it('deserializes flashcards from fromArray', function (): void {
    $data = [
        'introduction' => 'Intro',
        'key_points'   => [],
        'flashcards'   => [
            ['question' => 'Q1', 'answer' => 'A1', 'source_timecode' => '01:00', 'difficulty' => 'hard'],
        ],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->flashCards())->toHaveCount(1)
        ->and($result->flashCards()[0]->question)->toBe('Q1')
        ->and($result->flashCards()[0]->answer)->toBe('A1');
});

it('fromArray handles missing flashcards key', function (): void {
    $data = ['introduction' => 'No cards', 'key_points' => []];

    $result = SummaryResult::fromArray($data);

    expect($result->flashCards())->toBe([]);
});

it('stores and retrieves highlights', function (): void {
    $highlight = new HighlightMoment('00:10:00', 'Big moment', 'Very notable.', 'surprise');
    $result = new SummaryResult('Intro', [], highlights: [$highlight]);

    expect($result->highlights())->toHaveCount(1)
        ->and($result->highlights()[0]->timecode)->toBe('00:10:00')
        ->and($result->highlights()[0]->title)->toBe('Big moment')
        ->and($result->highlights()[0]->whyNotable)->toBe('Very notable.')
        ->and($result->highlights()[0]->category)->toBe('surprise');
});

it('defaults highlights to empty array', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->highlights())->toBe([]);
});

it('serializes highlights in toArray', function (): void {
    $highlight = new HighlightMoment('05:00', 'Funny bit', 'Laughs.', 'humor');
    $result = new SummaryResult('Intro', [], highlights: [$highlight]);

    expect($result->toArray()['highlights'])->toBe([
        ['timecode' => '05:00', 'title' => 'Funny bit', 'why_notable' => 'Laughs.', 'category' => 'humor'],
    ]);
});

it('deserializes highlights from fromArray', function (): void {
    $data = [
        'introduction' => 'Intro',
        'key_points'   => [],
        'highlights'   => [
            ['timecode' => '03:00', 'title' => 'Wow', 'why_notable' => 'Shocking.', 'category' => 'revelation'],
        ],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->highlights())->toHaveCount(1)
        ->and($result->highlights()[0]->title)->toBe('Wow')
        ->and($result->highlights()[0]->category)->toBe('revelation');
});

it('fromArray handles missing highlights key', function (): void {
    $data = ['introduction' => 'No highlights', 'key_points' => []];

    $result = SummaryResult::fromArray($data);

    expect($result->highlights())->toBe([]);
});

it('stores and retrieves content meta', function (): void {
    $meta = new ContentMeta('beginner', 5, 'low', 'Anyone');
    $result = new SummaryResult('Intro', [], contentMeta: $meta);

    $retrieved = $result->contentMeta();
    \PHPUnit\Framework\Assert::assertNotNull($retrieved);
    expect($retrieved->complexity)->toBe('beginner')
        ->and($retrieved->readingTimeMinutes)->toBe(5);
});

it('defaults contentMeta to null', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->contentMeta())->toBeNull();
});

it('serializes contentMeta in toArray', function (): void {
    $meta = new ContentMeta('advanced', 30, 'high', 'Experts');
    $result = new SummaryResult('Intro', [], contentMeta: $meta);

    expect($result->toArray()['content_meta'])->toBe([
        'complexity' => 'advanced', 'reading_time_minutes' => 30,
        'jargon_density' => 'high', 'target_audience' => 'Experts',
    ]);
});

it('serializes null contentMeta in toArray', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->toArray()['content_meta'])->toBeNull();
});

it('deserializes contentMeta from fromArray', function (): void {
    $data = [
        'introduction' => 'Intro',
        'key_points'   => [],
        'content_meta' => [
            'complexity' => 'intermediate', 'reading_time_minutes' => 12,
            'jargon_density' => 'moderate', 'target_audience' => 'Devs',
        ],
    ];

    $result = SummaryResult::fromArray($data);

    $retrieved = $result->contentMeta();
    \PHPUnit\Framework\Assert::assertNotNull($retrieved);
    expect($retrieved->complexity)->toBe('intermediate')
        ->and($retrieved->readingTimeMinutes)->toBe(12);
});

it('fromArray handles missing content_meta key', function (): void {
    $data = ['introduction' => 'No meta', 'key_points' => []];

    $result = SummaryResult::fromArray($data);

    expect($result->contentMeta())->toBeNull();
});

it('stores and retrieves blog post', function (): void {
    $sections = [
        new BlogSection('Intro', 'Body 1'),
        new BlogSection('Main', 'Body 2'),
    ];
    $blogPost = new BlogPost('My Article', $sections);
    $result = new SummaryResult('Intro', [], blogPost: $blogPost);

    $retrieved = $result->blogPost();
    \PHPUnit\Framework\Assert::assertNotNull($retrieved);
    expect($retrieved->title)->toBe('My Article')
        ->and($retrieved->sections)->toHaveCount(2)
        ->and($retrieved->sections[0]->heading)->toBe('Intro');
});

it('defaults blogPost to null', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->blogPost())->toBeNull();
});

it('serializes blogPost in toArray', function (): void {
    $sections = [new BlogSection('H1', 'B1')];
    $blogPost = new BlogPost('Title', $sections);
    $result = new SummaryResult('Intro', [], blogPost: $blogPost);

    expect($result->toArray()['blog_post'])->toBe([
        'title'    => 'Title',
        'sections' => [
            ['heading' => 'H1', 'body' => 'B1'],
        ],
    ]);
});

it('serializes null blogPost in toArray', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->toArray()['blog_post'])->toBeNull();
});

it('deserializes blogPost from fromArray', function (): void {
    $data = [
        'introduction' => 'Intro',
        'key_points'   => [],
        'blog_post'    => [
            'title'    => 'Deserialized',
            'sections' => [
                ['heading' => 'S1', 'body' => 'B1'],
            ],
        ],
    ];

    $result = SummaryResult::fromArray($data);

    $retrieved = $result->blogPost();
    \PHPUnit\Framework\Assert::assertNotNull($retrieved);
    expect($retrieved->title)->toBe('Deserialized')
        ->and($retrieved->sections)->toHaveCount(1)
        ->and($retrieved->sections[0]->heading)->toBe('S1');
});

it('fromArray handles missing blog_post key', function (): void {
    $data = ['introduction' => 'No blog', 'key_points' => []];

    $result = SummaryResult::fromArray($data);

    expect($result->blogPost())->toBeNull();
});

it('round-trips blogPost through toArray and fromArray', function (): void {
    $sections = [
        new BlogSection('Setup', 'How to begin.'),
        new BlogSection('Core', 'Main content.'),
    ];
    $blogPost = new BlogPost('Round Trip', $sections);
    $original = new SummaryResult('Intro', [], blogPost: $blogPost);

    $roundTrip = SummaryResult::fromArray($original->toArray());

    $retrieved = $roundTrip->blogPost();
    \PHPUnit\Framework\Assert::assertNotNull($retrieved);
    expect($retrieved->title)->toBe('Round Trip')
        ->and($retrieved->sections)->toHaveCount(2)
        ->and($retrieved->sections[1]->heading)->toBe('Core');
});

// ─── LinkedIn Post ────────────────────────────────────────────────────────────

it('stores and retrieves linkedInPost', function (): void {
    $post = new LinkedInPost(
        hook: 'Most teams ship slow. Here is why.',
        body: 'The real bottleneck is invisible hand-offs.' . "\n\n" . 'Second paragraph.',
        callToAction: 'Full AI summary → [URL]',
    );
    $result = new SummaryResult('Intro', [], linkedInPost: $post);

    $retrieved = $result->linkedInPost();
    \PHPUnit\Framework\Assert::assertNotNull($retrieved);
    expect($retrieved->hook())->toBe('Most teams ship slow. Here is why.')
        ->and($retrieved->callToAction())->toBe('Full AI summary → [URL]');
});

it('defaults linkedInPost to null', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->linkedInPost())->toBeNull();
});

it('serializes linkedInPost in toArray', function (): void {
    $post = new LinkedInPost('Hook text', 'Body text', 'CTA → [URL]');
    $result = new SummaryResult('Intro', [], linkedInPost: $post);

    expect($result->toArray()['linkedin_post'])->toBe([
        'hook'           => 'Hook text',
        'body'           => 'Body text',
        'call_to_action' => 'CTA → [URL]',
    ]);
});

it('serializes null linkedInPost in toArray', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->toArray()['linkedin_post'])->toBeNull();
});

it('deserializes linkedInPost from fromArray', function (): void {
    $data = [
        'introduction'  => 'Intro',
        'key_points'    => [],
        'linkedin_post' => [
            'hook'           => 'Deserialized hook',
            'body'           => 'Deserialized body',
            'call_to_action' => 'Deserialized CTA → [URL]',
        ],
    ];

    $result = SummaryResult::fromArray($data);

    $retrieved = $result->linkedInPost();
    \PHPUnit\Framework\Assert::assertNotNull($retrieved);
    expect($retrieved->hook())->toBe('Deserialized hook')
        ->and($retrieved->body())->toBe('Deserialized body')
        ->and($retrieved->callToAction())->toBe('Deserialized CTA → [URL]');
});

it('fromArray handles missing linkedin_post key', function (): void {
    $data = ['introduction' => 'No LinkedIn', 'key_points' => []];

    $result = SummaryResult::fromArray($data);

    expect($result->linkedInPost())->toBeNull();
});

it('round-trips linkedInPost through toArray and fromArray', function (): void {
    $post     = new LinkedInPost('Hook', "Body\n\nParagraph two.", 'CTA → [URL]');
    $original = new SummaryResult('Intro', [], linkedInPost: $post);

    $roundTrip = SummaryResult::fromArray($original->toArray());

    $retrieved = $roundTrip->linkedInPost();
    \PHPUnit\Framework\Assert::assertNotNull($retrieved);
    expect($retrieved->hook())->toBe('Hook')
        ->and($retrieved->body())->toBe("Body\n\nParagraph two.")
        ->and($retrieved->callToAction())->toBe('CTA → [URL]');
});
