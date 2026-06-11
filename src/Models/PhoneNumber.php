<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneNumber extends Model
{
    protected $table = 'phone_numbers';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'voice_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'mms_enabled' => 'boolean',
            'business_hours' => 'array',
            'metadata' => 'array',
        ];
    }

    public function threads(): HasMany
    {
        return $this->hasMany(PhoneThread::class, 'phone_number_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PhoneMessage::class, 'phone_number_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(PhoneCall::class, 'phone_number_id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(PhoneRecording::class, 'phone_number_id');
    }

    public function voicemails(): HasMany
    {
        return $this->hasMany(PhoneVoicemail::class, 'phone_number_id');
    }
}
