<?php

use App\Models\Document;
use Tests\TestCase;

uses(TestCase::class);

it('marks approved pdfs with extracted content as searchable', function () {
    $document = new Document([
        'status' => 'approved',
        'mime_type' => 'application/pdf',
        'content' => 'Extracted text',
    ]);

    expect($document->shouldBeSearchable())->toBeTrue();
});

it('does not index pdfs without extracted content', function () {
    $document = new Document([
        'status' => 'approved',
        'mime_type' => 'application/pdf',
        'content' => null,
    ]);

    expect($document->shouldBeSearchable())->toBeFalse();
});

it('indexes description-only files when approved and described', function (string $mimeType) {
    $document = new Document([
        'status' => 'approved',
        'mime_type' => $mimeType,
        'description' => 'Summary of the file',
    ]);

    expect($document->shouldBeSearchable())->toBeTrue();
})->with([
    'word docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'powerpoint pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'excel xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]);

it('skips description-only files without descriptions', function (string $mimeType) {
    $document = new Document([
        'status' => 'approved',
        'mime_type' => $mimeType,
        'description' => null,
    ]);

    expect($document->shouldBeSearchable())->toBeFalse();
})->with([
    'word docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'powerpoint pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'excel xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]);
