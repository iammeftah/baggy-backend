<?php

namespace App\Services;

use App\Models\User;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CartService
{
    /**
     * Get or create cart for user.
     *
     * @param User $user
     * @return Cart
     */
    public function getOrCreateCart(User $user): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->id]
        );
    }

    /**
     * Get user's cart with items.
     *
     * @param User $user
     * @return Cart
     */
    public function getCart(User $user): Cart
    {
        $cart = $this->getOrCreateCart($user);
        return $cart->load(['items.product.primaryImage']);
    }

    /**
     * Add item to cart.
     *
     * @param User $user
     * @param int $productId
     * @param int $quantity
     * @return CartItem
     * @throws \Exception
     */
    public function addToCart(User $user, int $productId, int $quantity = 1): CartItem
    {
        $product = Product::findOrFail($productId);

        // Check if product is active
        if (!$product->is_active) {
            throw new \Exception('Product is not available.');
        }

        // Check stock availability
        if (!$product->hasStock($quantity)) {
            throw new \Exception('Insufficient stock available.');
        }

        $cart = $this->getOrCreateCart($user);

        // Check if item already exists in cart
        $cartItem = $cart->items()->where('product_id', $productId)->first();

        if ($cartItem) {
            // Update quantity
            $newQuantity = $cartItem->quantity + $quantity;

            if (!$product->hasStock($newQuantity)) {
                throw new \Exception('Insufficient stock available.');
            }

            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            // Create new cart item
            $cartItem = $cart->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
        }

        return $cartItem->load('product.primaryImage');
    }

    /**
     * Update cart item quantity.
     *
     * @param User $user
     * @param int $cartItemId
     * @param int $quantity
     * @return CartItem
     * @throws \Exception
     */
    public function updateCartItem(User $user, int $cartItemId, int $quantity): CartItem
    {
        $cart = $this->getOrCreateCart($user);
        $cartItem = $cart->items()->findOrFail($cartItemId);

        if ($quantity <= 0) {
            throw new \Exception('Quantity must be at least 1.');
        }

        // Check stock availability
        if (!$cartItem->product->hasStock($quantity)) {
            throw new \Exception('Insufficient stock available.');
        }

        $cartItem->update(['quantity' => $quantity]);

        return $cartItem->load('product.primaryImage');
    }

    /**
     * Remove item from cart.
     *
     * @param User $user
     * @param int $cartItemId
     * @return bool
     */
    public function removeFromCart(User $user, int $cartItemId): bool
    {
        $cart = $this->getOrCreateCart($user);
        $cartItem = $cart->items()->findOrFail($cartItemId);

        return $cartItem->delete();
    }

    /**
     * Clear cart.
     *
     * @param User $user
     * @return void
     */
    public function clearCart(User $user): void
    {
        $cart = $this->getOrCreateCart($user);
        $cart->clear();
    }
}
