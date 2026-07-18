<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Infrastructure\Persistence\Eloquent\EndpointTestResult */
class EndpointTestResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'request_url_redacted' => $this->request_url_redacted,
            'request_headers_redacted' => $this->request_headers_redacted_json,
            'response_status' => $this->response_status,
            'response_body_truncated' => $this->response_body_truncated,
            'transport_error_code' => $this->transport_error_code,
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
