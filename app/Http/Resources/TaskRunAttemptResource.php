<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskRunAttempt */
class TaskRunAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'attempt_number' => $this->attempt_number,
            'attempt_state' => $this->attempt_state->value,
            'started_at' => $this->started_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'finished_at' => $this->finished_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration_ms' => $this->duration_ms,
            'request_url_redacted' => $this->request_url_redacted,
            'request_headers_redacted' => $this->request_headers_redacted_json,
            'request_body_redacted' => $this->request_body_redacted,
            'response_status' => $this->response_status,
            'response_headers' => $this->response_headers_json,
            'response_body_truncated' => $this->response_body_truncated,
            'transport_error_code' => $this->transport_error_code,
            'transport_error_message' => $this->transport_error_message,
            'next_retry_at' => $this->next_retry_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
