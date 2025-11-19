<?php

use App\Models\Chat;
use App\Models\ChatMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

uses(TestCase::class);

it('allows mass assignment for required chat attributes', function () {
    $chat = new Chat([
        'created_by' => 1,
        'name' => 'Product Updates',
    ]);

    expect($chat->created_by)->toBe(1)
        ->and($chat->name)->toBe('Product Updates');
});

it('defines members relationship on chat', function () {
    $chat = new Chat;

    $relationship = $chat->members();

    expect($relationship)->toBeInstanceOf(HasMany::class)
        ->and($relationship->getModel())->toBeInstanceOf(ChatMember::class);
});

it('casts chat member flags to booleans', function () {
    $member = new ChatMember([
        'is_admin' => 1,
        'is_pinned' => 0,
        'is_seen' => '1',
    ]);

    expect($member->is_admin)->toBeTrue()
        ->and($member->is_pinned)->toBeFalse()
        ->and($member->is_seen)->toBeTrue();
});

it('defines inverse relationships on chat member', function () {
    $member = new ChatMember;

    expect($member->chat())->toBeInstanceOf(BelongsTo::class)
        ->and($member->user())->toBeInstanceOf(BelongsTo::class);
});
