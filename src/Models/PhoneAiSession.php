<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope_key
 * @property ?string $scope_type
 * @property ?string $scope_id
 * @property string $provider
 * @property ?int $phone_call_id
 * @property ?string $provider_session_sid
 * @property string $mode
 * @property string $status
 * @property ?string $websocket_url
 * @property ?Carbon $started_at
 * @property ?Carbon $ended_at
 * @property ?string $transcript
 * @property ?string $summary
 * @property ?string $handoff_reason
 * @property ?array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PhoneAiSession extends Model
{
    protected $table = 'phone_ai_sessions';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(PhoneCall::class, 'phone_call_id');
    }
}
