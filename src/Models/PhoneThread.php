<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope_key
 * @property ?string $scope_type
 * @property ?string $scope_id
 * @property string $provider
 * @property int $phone_number_id
 * @property string $local_number
 * @property string $remote_number
 * @property ?string $remote_display_name
 * @property ?string $contact_type
 * @property ?string $contact_id
 * @property ?string $assigned_to_type
 * @property ?string $assigned_to_id
 * @property ?Carbon $last_message_at
 * @property ?Carbon $last_inbound_message_at
 * @property ?Carbon $last_outbound_message_at
 * @property int $unread_count
 * @property ?Carbon $opted_out_at
 * @property ?Carbon $archived_at
 * @property ?array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PhoneThread extends Model
{
    protected $table = 'phone_threads';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_message_at' => 'datetime',
            'last_outbound_message_at' => 'datetime',
            'unread_count' => 'integer',
            'opted_out_at' => 'datetime',
            'archived_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PhoneMessage::class, 'phone_thread_id');
    }
}
