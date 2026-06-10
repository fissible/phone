<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
