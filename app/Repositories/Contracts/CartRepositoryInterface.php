<?php

namespace App\Repositories\Contracts;

use App\Models\Cart;
use App\Models\User;

interface CartRepositoryInterface
{
    public function findByUser(User $user): ?Cart;

    public function create(User $user): Cart;

    public function addItem(Cart $cart, int $productId, int $quantity): void;

    public function updateItem(int $cartItemId, int $quantity): void;

    public function removeItem(int $cartItemId): void;

    public function clear(Cart $cart): void;
}
