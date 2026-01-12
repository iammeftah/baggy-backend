<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\User;
use App\Models\CartItem;
use App\Repositories\Contracts\CartRepositoryInterface;

class CartRepository implements CartRepositoryInterface
{
    public function findByUser(User $user): ?Cart
    {
        return Cart::where('user_id', $user->id)
            ->with(['items.product'])
            ->first();
    }

    public function create(User $user): Cart
    {
        return Cart::create(['user_id' => $user->id]);
    }

    public function addItem(Cart $cart, int $productId, int $quantity): void
    {
        $cartItem = $cart->items()->where('product_id', $productId)->first();

        if ($cartItem) {
            $cartItem->increment('quantity', $quantity);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
        }
    }

    public function updateItem(int $cartItemId, int $quantity): void
    {
        CartItem::where('id', $cartItemId)->update(['quantity' => $quantity]);
    }

    public function removeItem(int $cartItemId): void
    {
        CartItem::destroy($cartItemId);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }
}
