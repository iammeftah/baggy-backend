<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class ChatbotService
{
    protected $groqApiKey;
    protected $groqApiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->groqApiKey = config('services.groq.api_key');
    }

    /**
     * Process chatbot message and return response with recommendations.
     *
     * @param string $message
     * @return array
     */
    public function processMessage(string $message): array
    {
        // Extract product criteria from message using Groq AI
        $criteria = $this->extractCriteria($message);

        // Search for matching products
        $products = $this->searchProducts($criteria);

        // Generate response
        $reply = $this->generateResponse($message, $products, $criteria);

        return [
            'reply' => $reply,
            'recommended_products' => $products,
        ];
    }

    /**
     * Extract search criteria from user message.
     *
     * @param string $message
     * @return array
     */
    protected function extractCriteria(string $message): array
    {
        $systemPrompt = "You are a helpful shopping assistant for a women's bag store in Morocco.
        Extract product search criteria from the user's message.
        Return a JSON object with these fields: color, size, material, category, price_range, keywords.
        Only include fields that are mentioned in the message.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->groqApiUrl, [
                'model' => 'mixtral-8x7b-32768',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'temperature' => 0.3,
                'max_tokens' => 200,
            ]);

            $content = $response->json('choices.0.message.content', '{}');
            return json_decode($content, true) ?? [];
        } catch (\Exception $e) {
            return ['keywords' => $message];
        }
    }

    /**
     * Search products based on criteria.
     *
     * @param array $criteria
     * @return Collection
     */
    protected function searchProducts(array $criteria): Collection
    {
        $query = Product::active()->inStock()->with(['category', 'primaryImage', 'specifications']);

        // Search by keywords
        if (isset($criteria['keywords'])) {
            $query->search($criteria['keywords']);
        }

        // Filter by category
        if (isset($criteria['category'])) {
            $query->whereHas('category', function ($q) use ($criteria) {
                $q->where('name', 'like', '%' . $criteria['category'] . '%');
            });
        }

        // Filter by specifications (color, size, material)
        foreach (['color', 'size', 'material'] as $spec) {
            if (isset($criteria[$spec])) {
                $query->whereHas('specifications', function ($q) use ($spec, $criteria) {
                    $q->where('spec_key', $spec)
                      ->where('spec_value', 'like', '%' . $criteria[$spec] . '%');
                });
            }
        }

        // Filter by price range
        if (isset($criteria['price_range'])) {
            // Parse price range (e.g., "under 500", "between 300 and 600")
            // This is simplified - you can enhance it
            if (preg_match('/(\d+)/', $criteria['price_range'], $matches)) {
                $query->where('price', '<=', $matches[1]);
            }
        }

        return $query->limit(5)->get();
    }

    /**
     * Generate response message.
     *
     * @param string $userMessage
     * @param Collection $products
     * @param array $criteria
     * @return string
     */
    protected function generateResponse(string $userMessage, Collection $products, array $criteria): string
    {
        $productsList = $products->map(function ($product) {
            return $product->name . ' - ' . $product->price . ' MAD';
        })->join(', ');

        $systemPrompt = "You are a helpful shopping assistant for a women's bag store in Morocco.
        Generate a friendly response to the customer based on their request and the products found.
        Keep it concise and helpful. Speak in a warm, conversational tone.";

        $userPrompt = "Customer asked: {$userMessage}\n\n";
        $userPrompt .= "Products found: " . ($products->isEmpty() ? 'None' : $productsList);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->groqApiUrl, [
                'model' => 'mixtral-8x7b-32768',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 300,
            ]);

            return $response->json('choices.0.message.content', 'I found some great options for you!');
        } catch (\Exception $e) {
            if ($products->isEmpty()) {
                return "I couldn't find any products matching your criteria at the moment. Would you like to browse our full collection?";
            }
            return "I found {$products->count()} products that might interest you!";
        }
    }
}
