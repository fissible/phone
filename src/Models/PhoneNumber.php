<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope_key
 * @property ?string $scope_type
 * @property ?string $scope_id
 * @property string $provider
 * @property string $phone_number
 * @property ?string $friendly_name
 * @property ?string $provider_account_sid
 * @property ?string $provider_number_sid
 * @property ?string $messaging_service_sid
 * @property ?array $capabilities
 * @property bool $voice_enabled
 * @property bool $sms_enabled
 * @property bool $mms_enabled
 * @property string $routing_mode
 * @property ?string $forward_to
 * @property ?array $business_hours
 * @property ?string $voicemail_greeting
 * @property string $status
 * @property ?array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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

    public function transcriptions(): HasMany
    {
        return $this->hasMany(PhoneTranscription::class, 'phone_number_id');
    }
}
