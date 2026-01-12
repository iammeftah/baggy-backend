<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function all(array $filters = []): LengthAwarePaginator;

    public function findByOrderNumber(string $orderNumber): ?Order;

    public function findByUser(User $user, array $filters = []): LengthAwarePaginator;

    public function create(array $data): Order;

    public function update(Order $order, array $data): Order;

    public function updateStatus(Order $order, string $status): Order;
}
