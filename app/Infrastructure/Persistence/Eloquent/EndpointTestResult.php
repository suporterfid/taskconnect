<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndpointTestResult extends Model
{
    use HasPublicId;

    public $timestamps = false;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'environment_id',
        'endpoint_profile_id',
        'request_url_redacted',
        'request_headers_redacted_json',
        'response_status',
        'response_body_truncated',
        'transport_error_code',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_headers_redacted_json' => 'array',
            'response_status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'etr';
    }

    public function endpointProfile(): BelongsTo
    {
        return $this->belongsTo(EndpointProfile::class);
    }
}
