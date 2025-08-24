<?php
declare(strict_types=1);

namespace App\Domain\Vouchers;

class Voucher
{
    public function __construct(
        public ?int $voucher_id,
        public string $code,
        public float $discount_amount,
        public string $discount_type, // 'percent' or 'amount'
        public ?int $max_usage,
        public ?int $usage_per_user,
        public string $start_date,
        public string $end_date,
        public int $version = 0,
        public float $min_order_total = 0.0,
        public string $status = 'active',
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            voucher_id: $data['voucher_id'] ?? null,
            code: $data['code'],
            discount_amount: (float) $data['discount_amount'],
            discount_type: $data['discount_type'],
            max_usage: $data['max_usage'] ?? null,
            version: (int) ($data['version'] ?? 0),
            usage_per_user: $data['usage_per_user'] ?? null,
            min_order_total: (float) ($data['min_order_total'] ?? 0.0),
            start_date: $data['start_date'],
            end_date: $data['end_date'],
            status: $data['status'] ?? 'active',
            created_at: $data['created_at'] ?? null,
            updated_at: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'voucher_id' => $this->voucher_id,
            'code' => $this->code,
            'discount_amount' => $this->discount_amount,
            'discount_type' => $this->discount_type,
            'max_usage' => $this->max_usage,
            'version' => $this->version,
            'usage_per_user' => $this->usage_per_user,
            'min_order_total' => $this->min_order_total,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    public function isActive(): bool
    {
        $now = date('Y-m-d');
        return $this->status === 'active' && 
               $this->start_date <= $now && 
               $this->end_date >= $now;
    }

    public function isExpired(): bool
    {
        return date('Y-m-d') > $this->end_date;
    }

    public function isNotStarted(): bool
    {
        return date('Y-m-d') < $this->start_date;
    }
}
